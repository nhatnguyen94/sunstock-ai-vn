<?php

namespace App\Frontend\Services;

use App\Frontend\Interfaces\CompanyProfileRepositoryInterface;
use App\Jobs\SyncCompanyProfileJob;
use App\Models\CompanyProfile;
use App\Support\PythonRunner;
use App\Support\SingleFlight;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CompanyProfileService
{
    /** Nine vnstock calls run concurrently inside one script; ~4s typical, VCI outages can stretch it. */
    private const PYTHON_TIMEOUT = 60;

    /** How long an "unknown symbol" answer is remembered, so typos cannot spawn Python in a loop. */
    private const MISSING_TTL = 900;

    /** Minimum gap between two manual "refresh now" clicks on the same symbol. */
    private const REFRESH_COOLDOWN = 300;

    /** One queued background refresh per symbol at a time. */
    private const QUEUE_DEDUP_TTL = 1800;

    private const EVENT_CATEGORIES = [
        'DIVIDEND'                  => 'Cổ tức & phát hành',
        'SHAREHOLDER_MEETING'       => 'Đại hội cổ đông',
        'MAJOR_SHAREHOLDER_TRADING' => 'Giao dịch nội bộ / cổ đông lớn',
        'OTHER'                     => 'Sự kiện khác',
    ];

    public function __construct(
        private readonly CompanyProfileRepositoryInterface $repo
    ) {}

    /** Uppercase + shape check. Null = not something we will ever hand to Python. */
    public static function normalizeSymbol(string $symbol): ?string
    {
        $symbol = strtoupper(trim($symbol));

        return preg_match('/^[A-Z0-9]{2,10}$/', $symbol) ? $symbol : null;
    }

    /**
     * Cached profile, if any. A stale copy is still returned immediately (the user never waits)
     * and a background refresh is queued — stale-while-revalidate.
     */
    public function find(string $symbol): ?CompanyProfile
    {
        $profile = $this->repo->find($symbol);

        if ($profile && $profile->isStale()) {
            $this->queueRefresh($symbol);
        }

        return $profile;
    }

    public function isKnownMissing(string $symbol): bool
    {
        return Cache::has($this->missingKey($symbol));
    }

    /** @return bool true when a job was actually queued (false = one is already pending) */
    public function queueRefresh(string $symbol): bool
    {
        if (! Cache::add('company-profile-refresh:' . $symbol, 1, self::QUEUE_DEDUP_TTL)) {
            return false;
        }

        SyncCompanyProfileJob::dispatch($symbol);

        return true;
    }

    /**
     * Fetch now (used by the page's AJAX loader for a first visit, and by the "refresh" button).
     * Concurrent callers for the same symbol share one Python run.
     *
     * @return array{profile:CompanyProfile}|array{error:string, not_found?:bool, cooldown?:bool, busy?:bool}
     */
    public function load(string $symbol, bool $force = false): array
    {
        if ($force) {
            if (! Cache::add('company-profile-cooldown:' . $symbol, 1, self::REFRESH_COOLDOWN)) {
                return ['error' => 'Vừa cập nhật gần đây, vui lòng thử lại sau vài phút.', 'cooldown' => true];
            }

            return SingleFlight::run(
                'company-profile:' . $symbol,
                fn () => null, // force = ignore the cached copy
                fn () => $this->sync($symbol),
                self::PYTHON_TIMEOUT + 30
            );
        }

        if ($this->isKnownMissing($symbol)) {
            return ['error' => 'Không tìm thấy thông tin công ty cho mã ' . $symbol . '.', 'not_found' => true];
        }

        return SingleFlight::run(
            'company-profile:' . $symbol,
            fn () => ($p = $this->repo->find($symbol)) ? ['profile' => $p] : null,
            fn () => $this->sync($symbol),
            self::PYTHON_TIMEOUT + 30
        );
    }

    /**
     * Force-fetch from Python and persist. Used by load(), SyncCompanyProfileJob and sync:company-profiles.
     *
     * @return array{profile:CompanyProfile}|array{error:string, not_found?:bool}
     */
    public function sync(string $symbol): array
    {
        $result = $this->fetchFromPython($symbol);

        if (isset($result['error'])) {
            // A real "no such company" answer is remembered briefly; a timeout / data-source
            // outage must not be, or one bad minute would hide a valid symbol for 15.
            $notFound = ! ($result['transient'] ?? false);
            if ($notFound) {
                Cache::put($this->missingKey($symbol), true, self::MISSING_TTL);
            }

            return ['error' => $result['error'], 'not_found' => $notFound];
        }

        Cache::forget($this->missingKey($symbol));

        return ['profile' => $this->repo->upsert($symbol, $result)];
    }

    /** @return array<string, mixed> decoded profile, or ['error' => string, 'transient' => bool] */
    private function fetchFromPython(string $symbol): array
    {
        $decoded = $this->runScript($symbol);

        if ($decoded === null) {
            Log::warning('CompanyProfileService: no JSON from Python', ['symbol' => $symbol]);

            return ['error' => 'Không lấy được dữ liệu công ty (nguồn dữ liệu chậm hoặc lỗi kết nối).', 'transient' => true];
        }

        return $decoded;
    }

    /** Protected so tests can stub the subprocess. */
    protected function runScript(string $symbol): ?array
    {
        return PythonRunner::runAndDecodeJson(base_path('py/get_company_profile.py'), [$symbol], self::PYTHON_TIMEOUT);
    }

    // ── Presentation ────────────────────────────────────────────────────────

    /**
     * Shape the stored JSON for the view: grouped officers, event buckets, chart series.
     *
     * @return array<string, mixed>
     */
    public function present(CompanyProfile $profile): array
    {
        $d = $profile->data;

        $events = $d['events'] ?? [];
        $today  = now()->toDateString();

        $upcoming = array_values(array_filter($events, function (array $e) use ($today) {
            return ($e['category'] ?? '') !== 'MAJOR_SHAREHOLDER_TRADING'
                && $this->eventKeyDate($e) >= $today;
        }));
        usort($upcoming, fn ($a, $b) => strcmp($this->eventKeyDate($a), $this->eventKeyDate($b)));

        $byCategory = fn (string $c) => array_values(array_filter($events, fn ($e) => ($e['category'] ?? '') === $c));

        $insider = array_map(function (array $e) {
            $e['side'] = preg_match('/\b(Mua|Bán)\b/u', $e['title'] ?? '', $m) ? $m[1] : null;

            return $e;
        }, $byCategory('MAJOR_SHAREHOLDER_TRADING'));

        $officers = ['board' => [], 'executive' => [], 'supervisory' => []];
        foreach ($d['officers'] ?? [] as $o) {
            $officers[$o['group'] ?? 'executive'][] = $o;
        }

        return [
            'symbol'         => $profile->symbol,
            'synced_at'      => $profile->synced_at,
            'stale'          => $profile->isStale(),
            'overview'       => $d['overview'] ?? [],
            'ownership'      => $d['ownership'] ?? [],
            'shareholders'   => $d['shareholders'] ?? [],
            'officers'       => $officers,
            'subsidiaries'   => $d['subsidiaries'] ?? [],
            'affiliates'     => $d['affiliates'] ?? [],
            'upcoming'       => $upcoming,
            'dividends'      => $byCategory('DIVIDEND'),
            'meetings'       => $byCategory('SHAREHOLDER_MEETING'),
            'other_events'   => $byCategory('OTHER'),
            'insider_trades' => $insider,
            'ownership_chart' => $this->ownershipChart($d['ownership'] ?? []),
            'holders_chart'   => $this->holdersChart($d['shareholders'] ?? []),
            'errors'         => $d['errors'] ?? [],
            'event_labels'   => self::EVENT_CATEGORIES,
        ];
    }

    /**
     * Upcoming dividends / shareholder meetings for a set of symbols (a portfolio's holdings), soonest first.
     * Symbols whose profile is not cached yet get one background load queued, so the next visit has them.
     *
     * @param  string[] $symbols
     * @return array<int, array<string, mixed>> events + `symbol` + `key_date`
     */
    public function upcomingEventsFor(array $symbols, int $limit = 8): array
    {
        $profiles = $this->repo->findMany($symbols)->keyBy('symbol');
        $today = now()->toDateString();
        $out = [];

        foreach (array_unique($symbols) as $symbol) {
            $profile = $profiles->get($symbol);
            if (! $profile) {
                $this->queueRefresh($symbol);

                continue;
            }

            foreach ($profile->data['events'] ?? [] as $e) {
                if (! in_array($e['category'] ?? '', ['DIVIDEND', 'SHAREHOLDER_MEETING'], true)) {
                    continue;
                }
                $date = $this->eventKeyDate($e);
                if ($date >= $today) {
                    $out[] = ['symbol' => $symbol, 'key_date' => $date] + $e;
                }
            }
        }

        usort($out, fn ($a, $b) => strcmp($a['key_date'], $b['key_date']));

        return array_slice($out, 0, $limit);
    }

    /** The date an event "matters" on: ex-rights, else record, else issue, else announcement. */
    private function eventKeyDate(array $e): string
    {
        return $e['exright_date'] ?? $e['record_date'] ?? $e['issue_date'] ?? $e['public_date'] ?? '0000-00-00';
    }

    /** @return array{labels:string[], series:float[]} */
    private function ownershipChart(array $ownership): array
    {
        return [
            'labels' => array_column($ownership, 'type'),
            'series' => array_map('floatval', array_column($ownership, 'percent')),
        ];
    }

    /**
     * Top 10 holders + an "Others" slice so the donut always sums to 100%.
     * If the source data already exceeds 100% (overlapping/duplicated holders) no filler is added.
     *
     * @return array{labels:string[], series:float[]}
     */
    private function holdersChart(array $holders): array
    {
        $top    = array_slice($holders, 0, 10);
        $labels = array_column($top, 'name');
        $series = array_map('floatval', array_column($top, 'percent'));

        $rest = round(100 - array_sum($series), 2);
        if ($rest >= 0.5 && $series !== []) {
            $labels[] = 'Cổ đông khác';
            $series[] = $rest;
        }

        return ['labels' => $labels, 'series' => $series];
    }

    private function missingKey(string $symbol): string
    {
        return 'company-profile-missing:' . $symbol;
    }
}

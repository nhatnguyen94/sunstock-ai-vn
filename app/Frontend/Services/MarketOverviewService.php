<?php

namespace App\Frontend\Services;

use App\Frontend\Interfaces\MarketSnapshotRepositoryInterface;
use App\Jobs\SyncMarketOverviewJob;
use App\Support\PythonRunner;
use App\Support\SingleFlight;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Market overview for the home page, the ticker tape and the watchlist: indices, breadth, liquidity, top
 * movers and a quote for every symbol — all from ONE ~4 s script (py/get_market_overview.py, KBS source).
 *
 * DB-first with stale-while-revalidate: pages read the stored snapshot; a snapshot older than the threshold
 * queues one deduplicated background refresh. The very first visit (empty table) loads live once.
 */
class MarketOverviewService
{
    private const PYTHON_TIMEOUT = 60;

    /** During the session a snapshot older than this is refreshed by the next page view. */
    public const STALE_OPEN_MINUTES = 5;

    /** Outside the session the numbers do not move; refresh only when it is this old. */
    public const STALE_CLOSED_HOURS = 6;

    private const MANUAL_COOLDOWN = 60;

    private const VN_TZ = 'Asia/Ho_Chi_Minh';

    /** Sparkline drawing box (viewBox 0 0 100 32). */
    private const SPARK_W = 100;
    private const SPARK_H = 32;

    public function __construct(private readonly MarketSnapshotRepositoryInterface $repo) {}

    // ── Market clock ────────────────────────────────────────────────────────

    /** HOSE/HNX/UPCoM trade Mon–Fri 9:00–15:00 Vietnam time (continuous matching + closing auction). */
    public function isMarketOpen(?Carbon $at = null): bool
    {
        $t = ($at ?? Carbon::now())->copy()->setTimezone(self::VN_TZ);

        return $t->isWeekday() && $t->format('H:i') >= '09:00' && $t->format('H:i') < '15:00';
    }

    // ── Page data ───────────────────────────────────────────────────────────

    /**
     * Everything the home page's market section needs.
     *
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $error = null;
        if ($this->repo->latest() === null) {
            $result = SingleFlight::run(
                'market-sync',
                fn () => $this->repo->latest() !== null ? ['ok' => true] : null,
                fn () => $this->sync(),
                self::PYTHON_TIMEOUT + 30
            );
            $error = $result['error'] ?? null;
        }

        $snapshot = $this->repo->latest();
        if ($snapshot === null) {
            return ['has_data' => false, 'error' => $error, 'market_open' => $this->isMarketOpen()];
        }

        $open = $this->isMarketOpen();
        $stale = $this->isStale($snapshot->synced_at, $open);
        if ($stale) {
            $this->queueRefresh();
        }

        $data = $snapshot->data;
        $indices = array_map(fn (array $i) => $i + ['spark' => $this->sparkPoints(array_column($i['series'] ?? [], 1))], $data['indices'] ?? []);

        $exchanges = $data['exchanges'] ?? [];
        $breadth = ['advancers' => 0, 'decliners' => 0, 'unchanged' => 0, 'ceiling' => 0, 'floor' => 0];
        $value = 0;
        foreach ($exchanges as $e) {
            foreach ($breadth as $k => $_) {
                $breadth[$k] += (int) ($e[$k] ?? 0);
            }
            $value += (int) ($e['value'] ?? 0);
        }
        $breadth['total'] = $breadth['advancers'] + $breadth['decliners'] + $breadth['unchanged'];

        // Compare with the previous session only once this one is complete (intraday numbers are partial)
        $previous = $this->repo->previous($snapshot->trade_date);
        $previousValue = $previous ? array_sum(array_column($previous->data['exchanges'] ?? [], 'value')) : 0;
        $sessionComplete = ! $open || $snapshot->trade_date->lt(Carbon::now(self::VN_TZ)->startOfDay());

        $vnindex = collect($indices)->firstWhere('code', 'VNINDEX');

        return [
            'has_data' => true,
            'error' => $error,
            'market_open' => $open,
            'stale' => $stale,
            'trade_date' => $snapshot->trade_date,
            'synced_at' => $snapshot->synced_at,
            'indices' => $indices,
            'exchanges' => $exchanges,
            'breadth' => $breadth,
            'liquidity' => [
                'value' => $value,
                'previous' => $previousValue ?: null,
                'change_percent' => ($sessionComplete && $previousValue > 0) ? round(($value / $previousValue - 1) * 100, 1) : null,
            ],
            'movers' => $data['movers'] ?? [],
            'vnindex_series' => $vnindex['series'] ?? [],
        ];
    }

    /**
     * Quotes for the given symbols from the newest snapshot (whole VND).
     *
     * @param  string[] $symbols
     * @return array<string, array{price: int, reference: int, change: int, percent: float, volume: int, value: int, ceiling: ?int, floor: ?int}>
     */
    public function quotes(array $symbols): array
    {
        if ($symbols === []) {
            return [];
        }

        $snapshot = $this->repo->latest(true);
        $map = $snapshot?->quotes ?? [];
        $out = [];

        foreach ($symbols as $symbol) {
            if (! isset($map[$symbol])) {
                continue;
            }
            [$price, $ref, $pct, $vol, $value, $ceil, $floor] = $map[$symbol] + [null, null, null, null, null, null, null];
            $out[$symbol] = [
                'price' => (int) $price, 'reference' => (int) $ref, 'change' => (int) $price - (int) $ref,
                'percent' => (float) $pct, 'volume' => (int) $vol, 'value' => (int) $value,
                'ceiling' => $ceil !== null ? (int) $ceil : null, 'floor' => $floor !== null ? (int) $floor : null,
            ];
        }

        return $out;
    }

    /** Session date the quotes belong to, when there is a snapshot. */
    public function tradeDate(): ?Carbon
    {
        return $this->repo->latest()?->trade_date;
    }

    /**
     * Items for the ticker tape in the site header: three indices + the most traded stocks. Cheap (60 s cache) and
     * never throws — the header must render even when the table is empty or missing.
     *
     * @return array<int, array{label: string, value: ?float, percent: float, kind: string}>
     */
    public function ticker(): array
    {
        try {
            return Cache::remember('market:ticker', 60, function () {
                $snapshot = $this->repo->latest();
                if (! $snapshot) {
                    return [];
                }

                $items = [];
                foreach ($snapshot->data['indices'] ?? [] as $i) {
                    if (in_array($i['code'], ['VNINDEX', 'VN30', 'HNXINDEX'], true)) {
                        $items[] = ['label' => strtoupper($i['name']), 'value' => (float) $i['close'], 'percent' => (float) $i['percent'], 'kind' => 'index'];
                    }
                }
                foreach (array_slice($snapshot->data['movers']['ALL']['value'] ?? [], 0, 8) as $m) {
                    $items[] = ['label' => $m['symbol'], 'value' => null, 'percent' => (float) $m['percent'], 'kind' => 'stock'];
                }

                return $items;
            });
        } catch (\Throwable) {
            return [];
        }
    }

    // ── Sync ────────────────────────────────────────────────────────────────

    /**
     * Fetch the market and store the snapshot.
     *
     * @return array{trade_date: string, symbols: int, warnings: string[]}|array{error: string}
     */
    public function sync(): array
    {
        $data = $this->runScript();

        if ($data === null) {
            Log::warning('MarketOverviewService: no JSON from get_market_overview.py');

            return ['error' => 'Không lấy được dữ liệu thị trường (nguồn dữ liệu chậm hoặc lỗi kết nối).'];
        }
        if (isset($data['error'])) {
            return ['error' => (string) $data['error']];
        }
        if (empty($data['quotes']) || empty($data['indices'])) {
            // A half-empty payload would overwrite a good snapshot of the same session
            $why = $data['errors']['board'] ?? ($data['errors'] ? implode('; ', $data['errors']) : 'thiếu dữ liệu');

            return ['error' => 'Dữ liệu thị trường chưa đầy đủ: ' . $why];
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($data['trade_date'] ?? ''))) {
            return ['error' => 'Không xác định được ngày giao dịch.'];
        }

        $this->repo->save($data);
        Cache::forget('market:ticker');

        return ['trade_date' => $data['trade_date'], 'symbols' => count($data['quotes']), 'warnings' => $data['warnings'] ?? []];
    }

    /** Manual/forced refresh: rate-limited, concurrent callers share one Python run. */
    public function refresh(): array
    {
        if (! Cache::add('market-refresh-cooldown', 1, self::MANUAL_COOLDOWN)) {
            return ['error' => 'Vừa cập nhật gần đây, vui lòng thử lại sau ít phút.', 'cooldown' => true];
        }

        return SingleFlight::run('market-sync', fn () => null, fn () => $this->sync(), self::PYTHON_TIMEOUT + 30);
    }

    /** @return bool true when a job was queued (false = one is already pending) */
    public function queueRefresh(): bool
    {
        if (! Cache::add('market-refresh-queued', 1, 120)) {
            return false;
        }

        SyncMarketOverviewJob::dispatch();

        return true;
    }

    public function isStale(?Carbon $syncedAt, bool $marketOpen): bool
    {
        if ($syncedAt === null) {
            return true;
        }

        return $marketOpen
            ? $syncedAt->diffInMinutes(now()) >= self::STALE_OPEN_MINUTES
            : $syncedAt->diffInHours(now()) >= self::STALE_CLOSED_HOURS;
    }

    /** Protected so tests can stub the subprocess. */
    protected function runScript(): ?array
    {
        return PythonRunner::runAndDecodeJson(base_path('py/get_market_overview.py'), [], self::PYTHON_TIMEOUT);
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    /**
     * Polyline points ("x,y x,y ...") for a sparkline in a 100×32 box; empty string with fewer than 2 points.
     *
     * @param array<int, int|float> $values
     */
    public function sparkPoints(array $values): string
    {
        $values = array_values(array_map('floatval', $values));
        $n = count($values);
        if ($n < 2) {
            return '';
        }

        $min = min($values);
        $max = max($values);
        $span = ($max - $min) ?: 1.0;
        $pad = 2;
        $points = [];

        foreach ($values as $i => $v) {
            $x = round($i / ($n - 1) * self::SPARK_W, 2);
            $y = round($pad + (1 - ($v - $min) / $span) * (self::SPARK_H - 2 * $pad), 2);
            $points[] = $x . ',' . $y;
        }

        return implode(' ', $points);
    }
}

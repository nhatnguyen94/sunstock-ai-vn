<?php

namespace App\Frontend\Services;

use App\Frontend\Interfaces\FundRepositoryInterface;
use App\Frontend\Repositories\FundRepository;
use App\Models\Fund;
use App\Support\FundMetrics;
use App\Support\PythonRunner;
use App\Support\SingleFlight;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FundService
{
    /** One Fmarket listing call (~0.5s) + Python start-up. */
    private const LIST_TIMEOUT = 60;

    /** 4 parallel Fmarket calls inside one script + Python start-up. */
    private const DETAIL_TIMEOUT = 60;

    /** NAV moves once a day, so a fund's detail (NAV history, holdings) is good for hours. */
    private const DETAIL_TTL = 21600;

    public const MAX_COMPARE = 4;

    public function __construct(
        private readonly FundRepositoryInterface $repo
    ) {}

    // ── Catalog ─────────────────────────────────────────────────────────────

    /**
     * Everything the catalog page needs. First ever visit (empty table) loads the
     * listing live once; afterwards it is DB-only and kept fresh by sync:funds.
     *
     * @param  array<string, mixed> $input raw query-string values
     * @return array<string, mixed>
     */
    public function catalog(array $input): array
    {
        $error = null;
        if ($this->repo->count() === 0) {
            $sync = SingleFlight::run(
                'fund-listing',
                fn () => $this->repo->count() > 0 ? ['count' => $this->repo->count()] : null,
                fn () => $this->syncAll(),
                self::LIST_TIMEOUT + 30
            );
            $error = $sync['error'] ?? null;
        }

        $filters = $this->normalizeFilters($input);

        return [
            'funds'      => $this->repo->search($filters),
            'filters'    => $filters,
            'counts'     => $this->repo->typeCounts(),
            'type_stats' => $this->repo->typeStats(),
            'owners'     => $this->repo->owners(),
            'total'      => $this->repo->count(),
            'synced_at'  => $this->repo->lastSyncedAt(),
            'error'      => $error,
        ];
    }

    /**
     * Only whitelisted, well-formed values survive; everything else falls back to a default.
     *
     * @param  array<string, mixed> $input
     * @return array{type:?string, q:?string, owner:?string, sort:string, dir:string}
     */
    public function normalizeFilters(array $input): array
    {
        $type = strtoupper((string) ($input['type'] ?? ''));
        $sort = (string) ($input['sort'] ?? '');
        $q    = trim((string) ($input['q'] ?? ''));
        $owner = trim((string) ($input['owner'] ?? ''));

        return [
            'type'  => isset(Fund::TYPE_LABELS[$type]) ? $type : null,
            'q'     => $q !== '' ? mb_substr($q, 0, 60) : null,
            'owner' => $owner !== '' ? mb_substr($owner, 0, 200) : null,
            'sort'  => in_array($sort, FundRepository::SORTABLE, true) ? $sort : 'nav_change_12m',
            'dir'   => ($input['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc',
        ];
    }

    // ── Sync ────────────────────────────────────────────────────────────────

    /**
     * Pull the whole Fmarket listing (one call) and upsert it.
     *
     * @return array{count:int}|array{error:string}
     */
    public function syncAll(): array
    {
        $decoded = $this->runListScript();

        if ($decoded === null) {
            Log::warning('FundService: no JSON from get_fund_list.py');

            return ['error' => 'Không lấy được danh sách quỹ từ Fmarket (timeout hoặc lỗi kết nối).'];
        }
        if (isset($decoded['error'])) {
            return ['error' => (string) $decoded['error']];
        }

        $rows = array_map(fn (array $f) => $this->mapListingRow($f), $decoded['funds'] ?? []);
        $rows = array_values(array_filter($rows));

        if ($rows === []) {
            return ['error' => 'Fmarket không trả về quỹ nào.'];
        }

        return ['count' => $this->repo->upsertMany($rows)];
    }

    /**
     * Python column names already match the funds table; add the derived type_code.
     *
     * @param  array<string, mixed> $f
     * @return array<string, mixed>|null
     */
    private function mapListingRow(array $f): ?array
    {
        if (empty($f['short_name']) || empty($f['name'])) {
            return null;
        }

        $row = array_intersect_key($f, array_flip((new Fund())->getFillable()));
        $row['type_code'] = Fund::typeCodeFromLabel($f['fund_type'] ?? null);

        return $row;
    }

    // ── Detail (NAV history + holdings) ─────────────────────────────────────

    public function find(string $code): ?Fund
    {
        return $this->repo->findByShortName($code);
    }

    /**
     * NAV history, holdings and per-window stats for one fund. Cached for hours; concurrent
     * misses share ONE Python run (see SingleFlight).
     *
     * @return array{nav:array, top_holdings:array, industries:array, assets:array, as_of:?string, stats:array}|array{error:string, not_found?:bool}
     */
    public function detail(string $code): array
    {
        $fund = $this->repo->findByShortName($code);
        if (! $fund) {
            return ['error' => 'Không tìm thấy quỹ ' . $code, 'not_found' => true];
        }

        $key = 'fund-detail:' . $fund->short_name;

        return SingleFlight::run(
            $key,
            fn () => Cache::get($key),
            function () use ($fund, $key) {
                $decoded = $this->runDetailScript($fund->short_name);

                if ($decoded === null || isset($decoded['error'])) {
                    Log::warning('FundService: detail fetch failed', [
                        'fund' => $fund->short_name, 'error' => $decoded['error'] ?? 'no output',
                    ]);

                    return ['error' => 'Chưa lấy được dữ liệu chi tiết của quỹ ' . $fund->short_name . ', vui lòng thử lại sau.'];
                }

                $decoded['stats'] = FundMetrics::allWindows($decoded['nav'] ?? []);
                Cache::put($key, $decoded, self::DETAIL_TTL);

                return $decoded;
            },
            self::DETAIL_TIMEOUT + 30
        );
    }

    // ── Python boundary (protected so tests can stub the subprocess) ─────────

    protected function runListScript(): ?array
    {
        return PythonRunner::runAndDecodeJson(base_path('py/get_fund_list.py'), [], self::LIST_TIMEOUT);
    }

    protected function runDetailScript(string $shortName): ?array
    {
        return PythonRunner::runAndDecodeJson(base_path('py/get_fund_detail.py'), [$shortName], self::DETAIL_TIMEOUT);
    }

    // ── Compare ─────────────────────────────────────────────────────────────

    /**
     * "DCDS, vcbf-bcf,DCDS" -> ["DCDS", "vcbf-bcf"]: keeps only well-formed codes, dedupes
     * case-insensitively and caps at MAX_COMPARE. Codes that match no stored fund are
     * dropped later by compareFunds().
     *
     * @return string[]
     */
    public function parseCodes(?string $csv): array
    {
        $seen = [];
        foreach (explode(',', (string) $csv) as $raw) {
            $code = trim($raw);
            if ($code !== '' && preg_match('/^[A-Za-z0-9._-]{2,40}$/', $code) && ! isset($seen[strtoupper($code)])) {
                $seen[strtoupper($code)] = $code;
            }
        }

        return array_slice(array_values($seen), 0, self::MAX_COMPARE);
    }

    /**
     * Every fund as a lightweight row for the compare page's "add a fund" dropdown.
     *
     * @return array<int, array{code:string, name:string, type:string}>
     */
    public function pickerList(): array
    {
        return $this->repo->search(['sort' => 'short_name', 'dir' => 'asc'])
            ->map(fn (Fund $f) => ['code' => $f->short_name, 'name' => $f->name, 'type' => $f->type_label])
            ->all();
    }

    /**
     * Resolve requested codes (case-insensitively) to stored funds, keeping request order.
     *
     * @param  string[] $codes
     * @return Collection<int, Fund>
     */
    public function compareFunds(array $codes): Collection
    {
        $all = $this->repo->search(['sort' => 'short_name', 'dir' => 'asc'])->keyBy(fn (Fund $f) => strtoupper($f->short_name));

        return new Collection(
            collect($codes)->map(fn ($c) => $all->get(strtoupper($c)))->filter()->values()->all()
        );
    }
}

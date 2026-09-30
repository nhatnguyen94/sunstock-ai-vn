<?php

namespace App\Frontend\Services;

use App\Frontend\Interfaces\EtfRepositoryInterface;
use App\Models\Etf;
use App\Support\EtfMeta;
use App\Support\EtfMetrics;
use App\Support\PriceUnit;
use App\Support\PythonRunner;
use App\Support\SingleFlight;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * ETF and listed-fund pages. The roster (symbol, name) comes from KBS through `py/get_etf_list.py`; everything
 * else — price, returns, liquidity — is computed from the stored daily prices and the market snapshot, because
 * the feed has no NAV, holdings or fee for these funds.
 */
class EtfService
{
    private const LIST_TIMEOUT = 60;

    /** Enough history for the 3-year window plus its coverage slack. */
    private const HISTORY_DAYS = 1110;

    /** Prices only move once a day in the database, so the per-fund series can be reused for a few minutes. */
    private const SERIES_TTL = 600;

    public const MAX_COMPARE = 4;

    /** Sort key => row key. Whitelist: nothing user-supplied is ever used as an array key otherwise. */
    public const SORTABLE = [
        'symbol' => 'symbol', 'price' => 'price', 'percent' => 'percent', 'value' => 'value', 'avg_value' => 'avg_value',
        'r1m' => 'r1m', 'r3m' => 'r3m', 'r6m' => 'r6m', 'ytd' => 'ytd', 'r1y' => 'r1y', 'r3y' => 'r3y',
    ];

    public function __construct(
        private readonly EtfRepositoryInterface $repo,
        private readonly MarketOverviewService $market
    ) {}

    // ── Catalog ─────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed> $input raw query-string values
     * @return array<string, mixed>
     */
    public function catalog(array $input): array
    {
        $error = $this->ensureRoster();
        $filters = $this->normalizeFilters($input);

        $all = $this->rows();
        $rows = array_values(array_filter($all, fn (array $r) => $this->matches($r, $filters)));
        $rows = $this->sortRows($rows, $filters['sort'], $filters['dir']);

        $indices = array_values(array_unique(array_filter(array_column($all, 'index'))));
        sort($indices);

        return [
            'rows'      => $rows,
            'filters'   => $filters,
            'total'     => count($all),
            'counts'    => [
                Etf::KIND_ETF    => count(array_filter($all, fn ($r) => $r['kind'] === Etf::KIND_ETF)),
                Etf::KIND_CLOSED => count(array_filter($all, fn ($r) => $r['kind'] === Etf::KIND_CLOSED)),
            ],
            'indices'   => $indices,
            'synced_at' => $this->repo->lastSyncedAt(),
            'error'     => $error,
        ];
    }

    /**
     * Only well-formed, whitelisted values survive.
     *
     * @param  array<string, mixed> $input
     * @return array{kind:?string, index:?string, q:?string, sort:string, dir:string}
     */
    public function normalizeFilters(array $input): array
    {
        $kind = $input['kind'] ?? null;
        $index = is_string($input['index'] ?? null) && preg_match('/^[A-Za-z0-9 ]{2,20}$/', $input['index']) ? $input['index'] : null;
        $q = is_string($input['q'] ?? null) ? trim(mb_substr($input['q'], 0, 40)) : '';
        $sort = $input['sort'] ?? null;

        return [
            'kind'  => in_array($kind, [Etf::KIND_ETF, Etf::KIND_CLOSED], true) ? $kind : null,
            'index' => $index,
            'q'     => $q !== '' ? $q : null,
            'sort'  => is_string($sort) && isset(self::SORTABLE[$sort]) ? $sort : 'avg_value',
            'dir'   => ($input['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc',
        ];
    }

    // ── Detail ──────────────────────────────────────────────────────────────

    public function isValidSymbol(string $symbol): bool
    {
        return (bool) preg_match('/^[A-Z0-9]{3,12}\z/', $symbol);
    }

    /**
     * Everything the detail page needs, or null for an unknown symbol.
     *
     * @return array<string, mixed>|null
     */
    public function detail(string $symbol): ?array
    {
        $symbol = strtoupper($symbol);
        if (! $this->isValidSymbol($symbol)) {
            return null;
        }
        $this->ensureRoster();
        $etf = $this->repo->find($symbol);
        if (! $etf) {
            return null;
        }

        $series = $this->seriesFor([$symbol])[$symbol] ?? [];
        $row = $this->buildRow($etf, $series, $this->market->quotes([$symbol])[$symbol] ?? null);

        $windows = [];
        foreach (EtfMetrics::WINDOWS as $w) {
            $windows[$w] = EtfMetrics::stats($series, $w);
        }

        $peers = [];
        if ($row['index'] !== null) {
            $peers = array_values(array_filter($this->rows(), fn (array $r) => $r['index'] === $row['index'] && $r['symbol'] !== $symbol));
            $peers = $this->sortRows($peers, 'avg_value', 'desc');
        }

        return [
            'etf'        => $etf,
            'row'        => $row,
            'windows'    => $windows,
            'ytd'        => EtfMetrics::ytd($series),
            'range'      => EtfMetrics::range52w($series),
            'peers'      => $peers,
            'index_note' => EtfMeta::indexNote($row['index']),
            'series'     => $series,
        ];
    }

    // ── Sync ────────────────────────────────────────────────────────────────

    /** @return array{count?:int, pruned?:int, error?:string} */
    public function syncAll(): array
    {
        $decoded = $this->runListScript();

        if ($decoded === null) {
            Log::warning('EtfService: no JSON from get_etf_list.py');

            return ['error' => 'Không lấy được danh sách quỹ ETF (timeout hoặc lỗi kết nối).'];
        }
        if (isset($decoded['error'])) {
            return ['error' => (string) $decoded['error']];
        }

        $rows = [];
        foreach ($decoded['etfs'] ?? [] as $e) {
            if (empty($e['symbol']) || ! preg_match('/^[A-Z0-9]{3,12}\z/', (string) $e['symbol'])) {
                continue;
            }
            $rows[] = [
                'symbol'   => $e['symbol'],
                'name'     => $e['name'] ?? null,
                'name_en'  => $e['name_en'] ?? null,
                'exchange' => $e['exchange'] ?? null,
                'kind'     => EtfMeta::kind($e['name'] ?? null),
            ];
        }

        if ($rows === []) {
            return ['error' => 'Nguồn dữ liệu không trả về quỹ nào.'];
        }

        $count = $this->repo->upsertMany($rows);
        // A funds list that really answered is complete: anything missing from it is no longer listed.
        $pruned = $this->repo->deleteNotIn(array_column($rows, 'symbol'));
        Cache::forget($this->seriesKey());

        return ['count' => $count, 'pruned' => $pruned];
    }

    /** First ever visit: load the roster once, then it is DB-only (kept fresh by `sync:etfs`). */
    private function ensureRoster(): ?string
    {
        if ($this->repo->count() > 0) {
            return null;
        }

        $sync = SingleFlight::run(
            'etf-listing',
            fn () => $this->repo->count() > 0 ? ['count' => $this->repo->count()] : null,
            fn () => $this->syncAll(),
            self::LIST_TIMEOUT + 30
        );

        return $sync['error'] ?? null;
    }

    // ── Rows ────────────────────────────────────────────────────────────────

    /** @return array<int, array<string, mixed>> one row per listed fund, unsorted */
    private function rows(): array
    {
        $etfs = $this->repo->all();
        if ($etfs->isEmpty()) {
            return [];
        }

        $symbols = $etfs->pluck('symbol')->all();
        $series = $this->seriesFor($symbols);
        $quotes = $this->market->quotes($symbols);

        return $etfs->map(fn (Etf $e) => $this->buildRow($e, $series[$e->symbol] ?? [], $quotes[$e->symbol] ?? null))->all();
    }

    /** @param string[] $symbols @return array<string, array<int, array{0:string,1:float,2:int}>> */
    private function seriesFor(array $symbols): array
    {
        $all = Cache::remember($this->seriesKey(), self::SERIES_TTL, function () {
            return $this->repo->series($this->repo->all()->pluck('symbol')->all(), now()->subDays(self::HISTORY_DAYS)->toDateString());
        });

        return array_intersect_key($all, array_flip($symbols));
    }

    private function seriesKey(): string
    {
        return 'etf:series:v1';
    }

    /**
     * @param  array<int, array{0:string,1:float,2:int}> $series
     * @param  array<string, mixed>|null $quote live quote from the market snapshot (whole VND)
     * @return array<string, mixed>
     */
    private function buildRow(Etf $etf, array $series, ?array $quote): array
    {
        $row = [
            'symbol'   => $etf->symbol,
            'name'     => $etf->name ?? $etf->symbol,
            'short'    => EtfMeta::shortName($etf->name) ?? $etf->symbol,
            'kind'     => $etf->kind,
            'manager'  => $etf->manager,
            'index'    => $etf->tracked_index,
            'price'    => null, 'reference' => null, 'change' => null, 'percent' => null,
            'volume'   => null, 'value' => null, 'source' => null, 'as_of' => null,
            'avg_value' => EtfMetrics::averageValue($series),
            'ytd'      => EtfMetrics::ytd($series),
            'r1m' => null, 'r3m' => null, 'r6m' => null, 'r1y' => null, 'r3y' => null,
        ];

        foreach (['1M' => 'r1m', '3M' => 'r3m', '6M' => 'r6m', '1Y' => 'r1y', '3Y' => 'r3y'] as $window => $key) {
            $row[$key] = EtfMetrics::stats($series, $window)['return_pct'];
        }

        if ($quote !== null) {
            $row = array_merge($row, [
                'price' => $quote['price'], 'reference' => $quote['reference'], 'change' => $quote['change'],
                'percent' => $quote['percent'], 'volume' => $quote['volume'], 'value' => $quote['value'],
                'source' => 'live', 'as_of' => $this->market->tradeDate()?->toDateString(),
            ]);
        } elseif ($series !== []) {
            $last = end($series);
            $prev = count($series) > 1 ? $series[count($series) - 2] : null;
            $price = PriceUnit::toVnd($last[1]);
            $ref = $prev ? PriceUnit::toVnd($prev[1]) : null;
            $row = array_merge($row, [
                'price' => (int) round($price), 'reference' => $ref !== null ? (int) round($ref) : null,
                'change' => $ref !== null ? (int) round($price - $ref) : null,
                'percent' => ($ref !== null && $ref > 0) ? round(($price / $ref - 1) * 100, 2) : null,
                'volume' => $last[2] ?: null,
                'value' => $last[2] ? (int) round($price * $last[2]) : null,
                'source' => 'eod', 'as_of' => $last[0],
            ]);
        }

        return $row;
    }

    /** @param array<string, mixed> $r @param array<string, mixed> $f */
    private function matches(array $r, array $f): bool
    {
        if ($f['kind'] !== null && $r['kind'] !== $f['kind']) {
            return false;
        }
        if ($f['index'] !== null && $r['index'] !== $f['index']) {
            return false;
        }
        if ($f['q'] !== null && ! str_contains(mb_strtolower($r['symbol'] . ' ' . $r['name'] . ' ' . $r['manager']), mb_strtolower($f['q']))) {
            return false;
        }

        return true;
    }

    /**
     * Rows without a value for the sort column always go last, whatever the direction; ties fall back to the symbol.
     *
     * @param  array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function sortRows(array $rows, string $sort, string $dir): array
    {
        $key = self::SORTABLE[$sort] ?? 'avg_value';
        $factor = $dir === 'asc' ? 1 : -1;

        usort($rows, function (array $a, array $b) use ($key, $factor) {
            $x = $a[$key];
            $y = $b[$key];
            if ($x === null && $y === null) {
                return strcmp($a['symbol'], $b['symbol']);
            }
            if ($x === null) {
                return 1;
            }
            if ($y === null) {
                return -1;
            }

            return ($x <=> $y) * $factor ?: strcmp($a['symbol'], $b['symbol']);
        });

        return $rows;
    }

    // ── Python boundary (protected so tests can stub the subprocess) ─────────

    protected function runListScript(): ?array
    {
        return PythonRunner::runAndDecodeJson(base_path('py/get_etf_list.py'), [], self::LIST_TIMEOUT);
    }
}

<?php

namespace App\Frontend\Services;

use App\Jobs\RefreshStockPricesJob;
use App\Models\Stock;
use App\Models\StockPrice;
use App\Models\StockSymbol;
use App\Support\SingleFlight;
use App\Support\TradingCalendar;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Keeps the price history behind a stock page usable WITHOUT making the visitor wait for a data provider.
 *
 * Before: any symbol whose newest stored bar was older than "today" ran a one-year Python fetch inside the web
 * request (~10 s, up to ~100 s when the provider was failing) — and then threw the answer away, because that path
 * read a `date` field the script never produced. Every visit after the 1-hour cache flag expired paid it again.
 *
 * Now, per symbol:
 *   - never synced        → ONE short synchronous fetch (KBS direct, ~1 s) so the first view has a chart;
 *   - behind the last completed session → serve what we have, queue ONE deduplicated incremental job (only the
 *                           missing sessions) and paint the newest session from the market snapshot;
 *   - up to date          → nothing at all.
 * The newest (possibly still running) session never needs Python: the market snapshot already holds every symbol's
 * open/high/low/close/volume, see liveBar().
 */
class StockPriceFreshness
{
    /** Ceiling for the one synchronous fetch a web request may make. */
    public const SYNC_TIMEOUT = 25;

    /** Background incremental refresh of a stale symbol: at most one queued per symbol per this many seconds. */
    private const QUEUE_LOCK = 600;

    /** After a failed first fetch, do not make the next visitors wait for the same failure again. */
    private const FAILURE_MEMORY = 300;

    /** Index codes the history script can serve although they are not in stock_symbols. */
    public const INDICES = ['VNINDEX', 'VN30', 'HNXINDEX', 'HNX30', 'UPCOMINDEX', 'VN100'];

    public function __construct(
        private readonly StockService $stocks,
        private readonly MarketOverviewService $market
    ) {}

    public static function isValidSymbol(string $symbol): bool
    {
        // \z, not $: "FPT\n" must not pass (it would reach a shell argument)
        return (bool) preg_match('/^[A-Z0-9]{2,20}\z/', $symbol);
    }

    /** Can we plausibly fetch history for this code? (listed symbol, index, or something we already track) */
    public function isKnown(string $symbol): bool
    {
        return in_array($symbol, self::INDICES, true)
            || StockSymbol::where('symbol', $symbol)->exists()
            || Stock::where('symbol', $symbol)->exists();
    }

    /**
     * Make sure each symbol has a Stock row and — if it has never been synced — some history.
     *
     * @param  string[] $symbols normalised (upper case, valid)
     * @return array<string, array{status: string, latest: ?string, error?: string}>
     *         status: fresh | queued | synced | failed | unknown
     */
    public function ensureMany(array $symbols): array
    {
        $expected = TradingCalendar::lastCompletedSession()->toDateString();
        $out = [];
        $needFirst = [];

        foreach (array_unique($symbols) as $symbol) {
            if (! $this->isKnown($symbol)) {
                $out[$symbol] = ['status' => 'unknown', 'latest' => null];
                continue;
            }

            $stock = Stock::firstOrCreate(['symbol' => $symbol]);
            $latest = $this->latestDate($stock);

            if ($latest === null) {
                if (Cache::has($this->failKey($symbol))) {
                    $out[$symbol] = ['status' => 'failed', 'latest' => null, 'error' => 'Nguồn dữ liệu chưa trả lời, hệ thống sẽ thử lại sau ít phút.'];
                } else {
                    $needFirst[] = $symbol;
                }
                continue;
            }

            if ($latest < $expected) {
                $this->queueIncremental($symbol, $latest);
                $out[$symbol] = ['status' => 'queued', 'latest' => $latest];
                continue;
            }

            $out[$symbol] = ['status' => 'fresh', 'latest' => $latest];
        }

        if ($needFirst !== []) {
            $out += $this->firstLoad($needFirst);
        }

        return $out;
    }

    /** @return array{status: string, latest: ?string, error?: string} */
    public function ensure(string $symbol): array
    {
        return $this->ensureMany([$symbol])[$symbol];
    }

    /**
     * Append the newest session, painted from the market snapshot, when the stored history stops before it.
     *
     * @param  array<int, array<string, mixed>> $rows StockRepository::getStockPrice() rows (time in ms, thousands of VND)
     * @return array{rows: array<int, array<string, mixed>>, live: ?array{date: string, running: bool}}
     */
    public function withLiveBar(array $rows, string $symbol): array
    {
        $latestMs = $rows ? end($rows)['time'] : null;
        $latest = $latestMs ? Carbon::createFromTimestampMs($latestMs)->toDateString() : null;

        $bar = $this->liveBar($symbol, $latest);
        if ($bar === null) {
            return ['rows' => $rows, 'live' => null];
        }

        $date = Carbon::createFromTimestampMs($bar['time'])->toDateString();
        $rows[] = $bar;

        return [
            'rows' => $rows,
            'live' => ['date' => $date, 'running' => TradingCalendar::isSessionOpen() && $date === Carbon::now(TradingCalendar::TZ)->toDateString()],
        ];
    }

    /**
     * One bar (feed units: thousands of VND) for the snapshot's session, or null when the stored history already has
     * it, the symbol is not in the snapshot, or it did not trade.
     *
     * @return array{time: int, open: float, high: float, low: float, close: float, volume: int}|null
     */
    public function liveBar(string $symbol, ?string $latestStoredDate): ?array
    {
        $quote = $this->market->quotes([$symbol])[$symbol] ?? null;
        $date = $this->market->tradeDate()?->toDateString();

        if (! $quote || ! $date || ($latestStoredDate !== null && $date <= $latestStoredDate) || $quote['volume'] <= 0) {
            return null;
        }

        $close = $quote['price'] / 1000;

        return [
            // same construction as StockRepository::getStockPrice() so the candle lines up with the stored ones
            'time' => Carbon::parse($date)->timestamp * 1000,
            'open' => ($quote['open'] ?? $quote['price']) / 1000,
            'high' => ($quote['high'] ?? $quote['price']) / 1000,
            'low' => ($quote['low'] ?? $quote['price']) / 1000,
            'close' => $close,
            'volume' => $quote['volume'],
        ];
    }

    // ── internals ───────────────────────────────────────────────────────────

    /** @param string[] $symbols */
    private function firstLoad(array $symbols): array
    {
        sort($symbols);

        // One Python run for all of them; concurrent visitors of the same symbol wait for it instead of starting their own
        $result = SingleFlight::run(
            'stock-first-load:' . implode(',', $symbols),
            fn () => $this->allHaveData($symbols) ? ['ok' => true] : null,
            function () use ($symbols) {
                $r = $this->stocks->refreshPrices($symbols, null, self::SYNC_TIMEOUT);

                return $r['stored'] > 0 ? ['ok' => true] : ['error' => 'no data'] + $r;
            },
            self::SYNC_TIMEOUT + 15
        );

        $out = [];
        foreach ($symbols as $symbol) {
            $latest = $this->latestDate(Stock::where('symbol', $symbol)->first());
            if ($latest !== null) {
                $out[$symbol] = ['status' => 'synced', 'latest' => $latest];
            } else {
                Cache::put($this->failKey($symbol), true, self::FAILURE_MEMORY);
                $out[$symbol] = ['status' => 'failed', 'latest' => null, 'error' => 'Chưa tải được dữ liệu giá cho ' . $symbol . ' — nguồn dữ liệu chưa trả lời hoặc mã này chưa có dữ liệu.'];
            }
        }

        return $out;
    }

    private function queueIncremental(string $symbol, string $latest): void
    {
        if (! Cache::add('stock-refresh-queued:' . $symbol, 1, self::QUEUE_LOCK)) {
            return;
        }

        // Overlap a few sessions: the last stored bar may have been an intraday snapshot, and it also covers holidays
        RefreshStockPricesJob::dispatch([$symbol], Carbon::parse($latest)->subDays(5)->toDateString());
    }

    /** @param string[] $symbols */
    private function allHaveData(array $symbols): bool
    {
        foreach ($symbols as $symbol) {
            if ($this->latestDate(Stock::where('symbol', $symbol)->first()) === null) {
                return false;
            }
        }

        return true;
    }

    private function latestDate(?Stock $stock): ?string
    {
        $date = $stock ? StockPrice::where('stock_id', $stock->id)->max('date') : null;

        return $date ? substr((string) $date, 0, 10) : null;
    }

    private function failKey(string $symbol): string
    {
        return 'stock-first-load-failed:' . $symbol;
    }
}

<?php

/**
 * Author: Sun Nguyen
 * Email: nhat.nguyenminh94@gmail.com
 * Github: https://github.com/nhatnguyen94
 */

namespace App\Frontend\Repositories;

use App\Frontend\Interfaces\StockRepositoryInterface;
use App\Frontend\Services\StockService;
use App\Models\Stock;
use App\Models\StockPrice;
use App\Models\StockSymbol;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class StockRepository implements StockRepositoryInterface
{
    protected $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Get featured stocks with overview and latest price.
     *
     * @return mixed
     */
    public function getFeaturedStocks(array $symbols): array
    {
        $result = [];
        foreach ($symbols as $symbol) {
            $stock = Stock::firstOrCreate(['symbol' => $symbol]);
            $latestPrice = StockPrice::where('stock_id', $stock->id)
                ->orderByDesc('date')->first();

            $overview = StockSymbol::where('symbol', $symbol)->first();

            $result[] = [
                'symbol' => $symbol,
                'name' => $overview->name ?? $symbol,
                'price' => $latestPrice->close ?? null,
                'change' => $latestPrice ? (($latestPrice->close - $latestPrice->open) / max($latestPrice->open, 1) * 100) : null,
                'exchange' => $overview->exchange ?? '',
                'industry' => $overview->industry ?? '',
            ];
        }

        return $result;
    }

    /**
     * Get historical prices for a stock symbol.
     *
     * @return mixed
     */
    public function getStockPrice(string $symbol): ?array
    {
        // Read-only: a lookup of a code nobody tracks must not create a Stock row (that is StockPriceFreshness' job,
        // and only for codes it knows)
        $stock = Stock::where('symbol', $symbol)->first();
        if (! $stock) {
            return [];
        }

        $prices = StockPrice::where('stock_id', $stock->id)
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'time' => Carbon::parse($item->date)->timestamp * 1000,
                    'open' => $item->open,
                    'high' => $item->high,
                    'low' => $item->low,
                    'close' => $item->close,
                    'volume' => $item->volume,
                ];
            })->toArray();

        return $prices;
    }

    /**
     * Update stock price from Python script.
     */
    // public function updateStockPriceFromPython(string $symbol): void
    // {
    //     $stock = Stock::firstOrCreate(['symbol' => $symbol]);
    //     $data = $this->stockService->fetchStockDataFromPython($symbol);
    //     if (is_array($data) && ! isset($data['error'])) {
    //         foreach ($data as $item) {
    //             $date = Carbon::createFromTimestampMs($item['time'])->toDateString();
    //             StockPrice::updateOrCreate(
    //                 ['stock_id' => $stock->id, 'date' => $date],
    //                 [
    //                     'open' => $item['open'],
    //                     'high' => $item['high'],
    //                     'low' => $item['low'],
    //                     'close' => $item['close'],
    //                     'volume' => $item['volume'],
    //                 ]
    //             );
    //         }
    //     }
    // }
    public function updateStockPriceFromPython(string $symbol): void
    {
        Stock::firstOrCreate(['symbol' => $symbol]);

        // One code path for storing bars (this method used to read a `date` field the script never produced, so a
        // ~10 s fetch stored nothing). 25 s ceiling: it may run inside a web request.
        $this->stockService->refreshPrices([$symbol], null, 25);
    }

    /**
     * Get overview information for a stock symbol.
     *
     * @return mixed
     */
    public function getOverview(string $symbol): ?array
    {
        $overview = StockSymbol::where('symbol', $symbol)->first();

        return $overview ? $overview->toArray() : null;
    }

    /**
     * Update stock symbol list from Python script if needed.
     */
    public function getOrUpdateSymbols(): void
    {
        $lastUpdate = Cache::get('stock_symbols_last_update');
        if (! $lastUpdate || now()->diffInHours($lastUpdate) > 24) {
            $symbols = $this->stockService->fetchStockListFromPython();
            foreach ($symbols as $symbol) {
                StockSymbol::updateOrCreate(
                    ['symbol' => $symbol['symbol']],
                    ['organ_name' => $symbol['organ_name'] ?? null]
                );
            }
            Cache::put('stock_symbols_last_update', now(), 86400);
        }
    }

    /**
     * Search stock symbols by query string.
     *
     * @return mixed
     */
    public function searchSymbols(string $query): array
    {
        // Only update symbols if DB is completely empty
        if (StockSymbol::count() === 0) {
            $this->getOrUpdateSymbols();
        }

        $query = trim($query);
        // Escape LIKE wildcards so "%" / "_" typed by a user are matched literally
        $like = addcslashes($query, '%_\\');

        return StockSymbol::select(['symbol', 'name', 'exchange'])
            ->when($query !== '', function ($qBuilder) use ($like) {
                $qBuilder->where(function ($q) use ($like) {
                    $q->where('symbol', 'like', "%{$like}%")
                        ->orWhere('name', 'like', "%{$like}%");
                });
            })
            // Relevance: exact ticker, then ticker prefix, then ticker contains, then name-only matches
            ->when($query !== '', function ($qBuilder) use ($query, $like) {
                $qBuilder->orderByRaw(
                    'CASE WHEN symbol = ? THEN 0 WHEN symbol LIKE ? THEN 1 WHEN symbol LIKE ? THEN 2 ELSE 3 END',
                    [$query, "{$like}%", "%{$like}%"]
                );
            })
            ->orderBy('symbol')
            ->limit(20)
            ->get()
            ->toArray();
    }

    public function getLatestPrices(array $symbols): array
    {
        if (empty($symbols)) {
            return [];
        }

        $prices = [];

        Stock::whereIn('symbol', $symbols)
            ->with('latestPrice')
            ->get()
            ->each(function (Stock $stock) use (&$prices) {
                if ($stock->latestPrice) {
                    $prices[$stock->symbol] = (float) $stock->latestPrice->close;
                }
            });

        return $prices;
    }

    public function getLatestQuotes(array $symbols): array
    {
        if (empty($symbols)) {
            return [];
        }

        $quotes = [];

        foreach (Stock::whereIn('symbol', $symbols)->get(['id', 'symbol']) as $stock) {
            $rows = StockPrice::where('stock_id', $stock->id)
                ->whereNotNull('close')
                ->orderByDesc('date')
                ->limit(2)
                ->get(['date', 'close']);

            if ($rows->isEmpty()) {
                continue;
            }

            $quotes[$stock->symbol] = [
                'close' => (float) $rows[0]->close,
                'prev_close' => isset($rows[1]) ? (float) $rows[1]->close : null,
                'date' => Carbon::parse($rows[0]->date)->toDateString(),
            ];
        }

        return $quotes;
    }

    public function getCloseHistory(array $symbols, string $from): array
    {
        if (empty($symbols)) {
            return [];
        }

        $history = [];

        StockPrice::join('stocks', 'stocks.id', '=', 'stock_prices.stock_id')
            ->whereIn('stocks.symbol', $symbols)
            ->where('stock_prices.date', '>=', $from)
            ->whereNotNull('stock_prices.close')
            ->orderBy('stock_prices.date')
            ->get(['stocks.symbol', 'stock_prices.date', 'stock_prices.close'])
            ->each(function ($row) use (&$history) {
                $history[$row->symbol][Carbon::parse($row->date)->toDateString()] = (float) $row->close;
            });

        return $history;
    }

    public function ensureTracked(array $symbols): array
    {
        $missing = [];

        foreach (array_unique($symbols) as $symbol) {
            $stock = Stock::firstOrCreate(['symbol' => $symbol]);

            if (! StockPrice::where('stock_id', $stock->id)->exists()) {
                $missing[] = $symbol;
            }
        }

        return $missing;
    }
}

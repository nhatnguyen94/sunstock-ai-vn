<?php
/**
 * Author: Sun Nguyen
 * Email: nhat.nguyenminh94@gmail.com
 * Github: https://github.com/nhatnguyen94
 */
namespace App\Repositories;

use App\Models\Stock;
use App\Models\StockPrice;
use App\Models\StockSymbol;
use Carbon\Carbon;
use App\Services\StockService;

class StockRepository implements StockRepositoryInterface
{
    /**
     * Get featured stocks with overview and latest price.
     *
     * @param array $symbols
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
                'change' => $latestPrice ? (($latestPrice->close - $latestPrice->open) / max($latestPrice->open,1) * 100) : null,
                'exchange' => $overview->exchange ?? '',
                'industry' => $overview->industry ?? '',
            ];
        }
        return $result;
    }

    /**
     * Get historical prices for a stock symbol.
     *
     * @param string $symbol
     * @return mixed
     */
    public function getStockPrice(string $symbol): ?array
    {
        $stock = Stock::firstOrCreate(['symbol' => $symbol]);
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
     *
     * @param string $symbol
     * @return void
     */
    public function updateStockPriceFromPython(string $symbol): void
    {
        $stock = Stock::firstOrCreate(['symbol' => $symbol]);
        $data = app(StockService::class)->fetchStockDataFromPython($symbol);
        if (is_array($data) && !isset($data['error'])) {
            foreach ($data as $item) {
                $date = Carbon::createFromTimestampMs($item['time'])->toDateString();
                StockPrice::updateOrCreate(
                    ['stock_id' => $stock->id, 'date' => $date],
                    [
                        'open' => $item['open'],
                        'high' => $item['high'],
                        'low' => $item['low'],
                        'close' => $item['close'],
                        'volume' => $item['volume'],
                    ]
                );
            }
        }
    }

    /**
     * Get overview information for a stock symbol.
     *
     * @param string $symbol
     * @return mixed
     */
    public function getOverview(string $symbol): ?array
    {
        $overview = StockSymbol::where('symbol', $symbol)->first();
        return $overview ? $overview->toArray() : null;
    }

    /**
     * Update stock symbol list from Python script if needed.
     *
     * @return void
     */
    public function getOrUpdateSymbols(): void
    {
        $latest = StockSymbol::orderByDesc('updated_at')->first();
        $needUpdate = !$latest || now()->diffInHours($latest->updated_at) > 24;

        if ($needUpdate) {
            $symbols = app(StockService::class)->fetchStockListFromPython();
            if (is_array($symbols)) {
                StockSymbol::truncate();
                foreach ($symbols as $item) {
                    StockSymbol::create([
                        'symbol' => $item['symbol'],
                        'name' => $item['organ_name'] ?? '',
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Search stock symbols by query string.
     *
     * @param string $query
     * @return mixed
     */
    public function searchSymbols(string $query): array
    {
        $this->getOrUpdateSymbols();
        return StockSymbol::when($query, function ($qBuilder) use ($query) {
                $qBuilder->where('symbol', 'like', "%$query%")
                        ->orWhere('name', 'like', "%$query%");
            })
            ->limit(20)
            ->get()
            ->toArray();
    }
}
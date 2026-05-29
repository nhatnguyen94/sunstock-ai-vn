<?php

namespace App\Console\Commands;

use App\Models\StockPrice;
use App\Models\StockPriceSummary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Generate monthly OHLCV summaries from daily stock_prices.
 *
 * Run after daily price sync:
 *   php artisan generate:price-summaries
 *
 * Summary logic per month:
 *   open   = open price of the FIRST trading day
 *   high   = MAX(high) across all trading days
 *   low    = MIN(low)  across all trading days
 *   close  = close price of the LAST trading day
 *   volume = SUM(volume) across all trading days
 */
class GeneratePriceSummaries extends Command
{
    protected $signature = 'generate:price-summaries {--stock_id= : Only regenerate for a specific stock_id}';

    protected $description = 'Generate monthly OHLCV summaries from daily stock prices';

    public function handle(): int
    {
        $this->info('Generating monthly price summaries...');
        Log::info('generate:price-summaries started');

        $stockIdOption = $this->option('stock_id');

        $query = StockPrice::query()->distinct();
        if ($stockIdOption) {
            $query->where('stock_id', $stockIdOption);
        }
        $stockIds = $query->pluck('stock_id');

        if ($stockIds->isEmpty()) {
            $this->warn('No stock price data found.');
            return 0;
        }

        $bar = $this->output->createProgressBar($stockIds->count());
        $bar->start();

        foreach ($stockIds as $stockId) {
            // Load all daily prices for this stock, ordered by date (index scan — fast)
            $prices = StockPrice::where('stock_id', $stockId)
                ->orderBy('date')
                ->get(['date', 'open', 'high', 'low', 'close', 'volume']);

            if ($prices->isEmpty()) {
                $bar->advance();
                continue;
            }

            // Group by YYYY-MM in PHP (no extra DB round-trip)
            $monthlyGroups = $prices->groupBy(fn ($p) => substr((string) $p->date, 0, 7));

            $upserts = [];
            foreach ($monthlyGroups as $ym => $monthPrices) {
                $upserts[] = [
                    'stock_id'     => $stockId,
                    'period_start' => $ym . '-01',
                    'open'         => $monthPrices->first()->open,
                    'high'         => $monthPrices->max('high'),
                    'low'          => $monthPrices->min('low'),
                    'close'        => $monthPrices->last()->close,
                    'volume'       => $monthPrices->sum('volume'),
                ];
            }

            // Single bulk upsert per stock (~12 rows) — much faster than updateOrCreate loop
            StockPriceSummary::upsert(
                $upserts,
                ['stock_id', 'period_start'],
                ['open', 'high', 'low', 'close', 'volume']
            );

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Monthly summaries generated for {$stockIds->count()} stocks.");
        Log::info("generate:price-summaries completed for {$stockIds->count()} stocks");

        return 0;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Stock;
use App\Models\StockPrice;
use App\Jobs\ProcessStockPriceSync;
use Illuminate\Support\Facades\Log;

class SyncStockPrices extends Command
{
    protected $signature = 'sync:stock-prices {--symbols=} {--chunk-size=20} {--force : Sync tat ca, bo qua check da co data hom nay}';

    protected $description = 'Dispatch jobs to sync historical stock prices. Use --symbols=AAA,BBB to sync specific symbols.';

    public function handle()
    {
        $this->info('Dispatching jobs for stock price sync...');
        Log::info('sync:stock-prices command started.');

        $symbolsOption = $this->option('symbols');
        $chunkSize     = (int) $this->option('chunk-size');
        $force         = (bool) $this->option('force');

        if ($symbolsOption) {
            $stocks = Stock::whereIn('symbol', explode(',', $symbolsOption))->get(['id', 'symbol'])->toArray();
        } else {
            $stocks = Stock::all(['id', 'symbol'])->toArray();
        }

        if (empty($stocks)) {
            $this->warn('No symbols found to sync.');
            return 0;
        }

        // Skip symbols that already have today's price data (unless --force)
        if (! $force) {
            $today = now()->toDateString();

            // Get stock_ids that already have a price record for today
            $alreadySyncedIds = StockPrice::whereIn('stock_id', array_column($stocks, 'id'))
                ->where('date', $today)
                ->pluck('stock_id')
                ->flip()
                ->toArray();

            $originalCount = count($stocks);
            $stocks = array_values(array_filter($stocks, fn ($s) => ! isset($alreadySyncedIds[$s['id']])));
            $skippedCount = $originalCount - count($stocks);

            if ($skippedCount > 0) {
                $this->line("Skipped {$skippedCount} symbols already synced today ({$today}). Use --force to override.");
            }
        }

        if (empty($stocks)) {
            $this->info('All symbols already have today\'s data. Nothing to sync.');
            return 0;
        }

        $chunks   = array_chunk($stocks, $chunkSize);
        $totalJobs = count($chunks);
        $this->info('Syncing ' . count($stocks) . " symbols → dispatching {$totalJobs} jobs (chunk-size={$chunkSize}).");

        $bar = $this->output->createProgressBar($totalJobs);
        $bar->start();

        foreach ($chunks as $chunk) {
            ProcessStockPriceSync::dispatch($chunk);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("All {$totalJobs} jobs dispatched.");
        Log::info('sync:stock-prices dispatched', ['jobs' => $totalJobs, 'symbols' => count($stocks)]);

        return 0;
    }
}

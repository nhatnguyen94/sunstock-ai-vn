<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Stock;
use App\Jobs\ProcessStockPriceSync;
use Illuminate\Support\Facades\Log;

class SyncStockPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:stock-prices {--symbols=} {--chunk-size=20}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch jobs to sync historical stock prices. Use --symbols=AAA,BBB to sync specific symbols.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Dispatching jobs for stock price sync...');
        Log::info('sync:stock-prices command started to dispatch jobs.');

        $symbolsOption = $this->option('symbols');
        $chunkSize = (int)$this->option('chunk-size');

        if ($symbolsOption) {
            $symbols = Stock::whereIn('symbol', explode(',', $symbolsOption))->get(['symbol'])->toArray();
        } else {
            $symbols = Stock::all(['symbol'])->toArray();
        }

        if (empty($symbols)) {
            $this->warn('No symbols found to sync.');
            return 0;
        }

        $chunks = array_chunk($symbols, $chunkSize);
        $totalJobs = count($chunks);

        $this->info("Found " . count($symbols) . " symbols. Dispatching {$totalJobs} jobs with a chunk size of {$chunkSize}...");

        $bar = $this->output->createProgressBar($totalJobs);
        $bar->start();

        foreach ($chunks as $chunk) {
            ProcessStockPriceSync::dispatch($chunk);
            $bar->advance();
        }

        $bar->finish();
        $this->info("\nAll {$totalJobs} jobs have been dispatched to the queue.");
        $this->info("Run 'php artisan queue:work' to start processing the jobs.");

        return 0;
    }
}

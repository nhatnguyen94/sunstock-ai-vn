<?php

namespace App\Console\Commands;

use App\Jobs\BackfillStockPriceChunk;
use App\Models\Stock;
use App\Models\StockPrice;
use App\Frontend\Services\StockService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Backfill historical stock prices from a given start date.
 *
 * Usage:
 *   php artisan backfill:stock-prices                          (all stocks, from 2015-01-01)
 *   php artisan backfill:stock-prices --start=2018-01-01      (all stocks, custom start)
 *   php artisan backfill:stock-prices --symbols=FPT,VCB       (specific stocks)
 *   php artisan backfill:stock-prices --symbols=FPT --start=2010-01-01
 */
class BackfillStockPrices extends Command
{
    protected $signature = 'backfill:stock-prices
                            {--start=2015-01-01 : Start date (YYYY-MM-DD)}
                            {--symbols=         : Comma-separated symbols. Omit for all stocks.}
                            {--chunk-size=5     : Symbols per Python call (lower = safer for long history)}
                            {--dispatch         : Push all chunks to queue instead of running inline (use with queue workers)}
                            {--force            : Dispatch/run all symbols even if they already have historical data}';

    protected $description = 'Backfill historical stock prices from a given start date';

    public function __construct(private StockService $stockService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $startDate  = $this->option('start');
        $end        = Carbon::today()->toDateString();
        $chunkSize  = (int) $this->option('chunk-size');
        $useQueue   = $this->option('dispatch');
        $force      = (bool) $this->option('force');

        // Validate date format
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $this->error('Invalid --start date format. Use YYYY-MM-DD.');
            return 1;
        }

        $symbolsOption = $this->option('symbols');
        if ($symbolsOption) {
            $stocks = Stock::whereIn('symbol', explode(',', $symbolsOption))->get(['id', 'symbol']);
        } else {
            $stocks = Stock::all(['id', 'symbol']);
        }

        if ($stocks->isEmpty()) {
            $this->warn('No stocks found.');
            return 0;
        }

        // Skip stocks already backfilled: if a stock has any data older than 2 years,
        // it has historical data and doesn't need a full backfill again.
        // Non-backfilled stocks only have recent daily-sync data (last 1-2 years).
        // Use --force to override and rebackfill everything.
        if (! $force) {
            $oldDataThreshold = Carbon::now()->subYears(2)->toDateString();
            $alreadyBackfilledIds = StockPrice::whereIn('stock_id', $stocks->pluck('id')->toArray())
                ->where('date', '<=', $oldDataThreshold)
                ->distinct()
                ->pluck('stock_id')
                ->flip()
                ->toArray();

            $originalCount = $stocks->count();
            $stocks = $stocks->filter(fn ($s) => ! isset($alreadyBackfilledIds[$s->id]));
            $skippedCount = $originalCount - $stocks->count();

            if ($skippedCount > 0) {
                $this->line("Skipped {$skippedCount} stocks already have historical data (data before {$oldDataThreshold}). Use --force to rebackfill.");
            }

            if ($stocks->isEmpty()) {
                $this->info('All stocks already have historical data. Nothing to backfill.');
                return 0;
            }
        }

        $chunks = $stocks->chunk($chunkSize);

        // ── QUEUE MODE: dispatch all chunks, let workers process in parallel ──
        if ($useQueue) {
            $count = 0;
            foreach ($chunks as $chunk) {
                BackfillStockPriceChunk::dispatch(
                    $chunk->map(fn($s) => ['id' => $s->id, 'symbol' => $s->symbol])->values()->toArray(),
                    $startDate,
                    $end,
                );
                $count++;
            }
            $this->info("Dispatched {$count} jobs ({$stocks->count()} stocks, chunk-size={$chunkSize}).");
            $this->info("Range: {$startDate} → {$end}");
            $this->info("Run 'php artisan queue:work --timeout=600' workers to process them.");
            Log::info("backfill:stock-prices dispatched {$count} queue jobs", ['start' => $startDate]);
            return 0;
        }

        // ── INLINE MODE: run directly in this terminal (sequential) ──
        $this->info("Backfilling {$stocks->count()} stocks from {$startDate} to {$end} (chunk-size={$chunkSize})...");
        Log::info("backfill:stock-prices started inline", ['start' => $startDate, 'count' => $stocks->count()]);

        $bar        = $this->output->createProgressBar($chunks->count());
        $pythonPath = config('services.python.path', 'python');
        $scriptPath = base_path('py/get_stock.py');
        $bar->start();

        foreach ($chunks as $chunk) {
            $symbolList = $chunk->pluck('symbol')->implode(',');
            $command    = escapeshellarg($pythonPath)
                . ' ' . escapeshellarg($scriptPath)
                . ' ' . escapeshellarg($symbolList)
                . ' ' . escapeshellarg($startDate)
                . ' ' . escapeshellarg($end);

            exec($command, $output, $returnVar);

            // Find last JSON line in output (vnstock may print banners above it)
            $jsonStr = '';
            for ($i = count($output) - 1; $i >= 0; $i--) {
                $line = trim($output[$i]);
                if (str_starts_with($line, '{') || str_starts_with($line, '[')) {
                    $jsonStr = $line;
                    break;
                }
            }
            $output = []; // reset for next exec call

            $result = $jsonStr ? json_decode($jsonStr, true) : null;
            if (! is_array($result) || ! isset($result['data'])) {
                Log::warning("backfill: no data for chunk", ['symbols' => $symbolList]);
                $bar->advance();
                continue;
            }

            $symbolToId = $chunk->pluck('id', 'symbol')->toArray();
            $rows = [];

            foreach ($result['data'] as $symbol => $prices) {
                if (empty($prices) || ! isset($symbolToId[$symbol])) continue;
                $stockId = $symbolToId[$symbol];

                foreach ($prices as $item) {
                    // Normalise date: Python returns 'date' key for backfill, 'time' (ms) for default
                    if (! empty($item['date'])) {
                        $date = $item['date'];
                    } elseif (! empty($item['time'])) {
                        $date = Carbon::createFromTimestampMs($item['time'])->toDateString();
                    } else {
                        continue;
                    }

                    $rows[] = [
                        'stock_id' => $stockId,
                        'date'     => $date,
                        'open'     => $item['open']   ?? null,
                        'high'     => $item['high']   ?? null,
                        'low'      => $item['low']    ?? null,
                        'close'    => $item['close']  ?? null,
                        'volume'   => $item['volume'] ?? null,
                    ];
                }
            }

            if (! empty($rows)) {
                StockPrice::upsert($rows, ['stock_id', 'date'], ['open', 'high', 'low', 'close', 'volume']);
            }

            $bar->advance();
            sleep(1); // respect VCI rate limit between chunks
        }

        $bar->finish();
        $this->newLine();
        $this->info("Backfill complete. Run 'php artisan generate:price-summaries' to rebuild monthly summaries.");
        Log::info("backfill:stock-prices completed inline");

        return 0;
    }
}


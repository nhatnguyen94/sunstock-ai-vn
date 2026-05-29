<?php

namespace App\Jobs;

use App\Models\StockPrice;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queue job that backfills historical stock prices for a chunk of symbols.
 * Dispatched by: php artisan backfill:stock-prices --dispatch
 * Processed by:  php artisan queue:work --timeout=600
 */
class BackfillStockPriceChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int seconds before job times out (10 min — long history fetch) */
    public int $timeout = 600;

    /** @var int max attempts */
    public int $tries = 2;

    /** @var int seconds to wait between retries */
    public int $backoff = 120;

    /**
     * @param array  $chunk     [['id' => ..., 'symbol' => ...], ...]
     * @param string $startDate Y-m-d
     * @param string $endDate   Y-m-d
     */
    public function __construct(
        private array  $chunk,
        private string $startDate,
        private string $endDate,
    ) {}

    public function handle(): void
    {
        $symbolList = implode(',', array_column($this->chunk, 'symbol'));
        Log::info("BackfillStockPriceChunk: start [{$symbolList}] {$this->startDate}→{$this->endDate}");

        $pythonPath = config('services.python.path', 'python');
        $scriptPath = base_path('py/get_stock.py');

        $cmd = escapeshellarg($pythonPath)
            . ' ' . escapeshellarg($scriptPath)
            . ' ' . escapeshellarg($symbolList)
            . ' ' . escapeshellarg($this->startDate)
            . ' ' . escapeshellarg($this->endDate);

        exec($cmd, $output, $exitCode);

        // vnstock may print version banners before JSON — scan from last line upward
        $jsonStr = '';
        for ($i = count($output) - 1; $i >= 0; $i--) {
            $line = trim($output[$i]);
            if (str_starts_with($line, '{') || str_starts_with($line, '[')) {
                $jsonStr = $line;
                break;
            }
        }

        $result = $jsonStr ? json_decode($jsonStr, true) : null;

        if (! is_array($result) || ! isset($result['data'])) {
            Log::warning("BackfillStockPriceChunk: no data for [{$symbolList}]", ['exit' => $exitCode]);
            return;
        }

        $symbolToId = array_column($this->chunk, 'id', 'symbol'); // ['FPT' => 42, ...]
        $rows = [];

        foreach ($result['data'] as $symbol => $prices) {
            if (empty($prices) || ! isset($symbolToId[$symbol])) {
                continue;
            }
            $stockId = $symbolToId[$symbol];

            foreach ($prices as $item) {
                // vnstock may return 'date' (string) or 'time' (ms timestamp)
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
            Log::info("BackfillStockPriceChunk: upserted " . count($rows) . " rows for [{$symbolList}]");
        } else {
            Log::warning("BackfillStockPriceChunk: 0 rows for [{$symbolList}]");
        }
    }

    public function failed(\Throwable $e): void
    {
        $symbolList = implode(',', array_column($this->chunk, 'symbol'));
        Log::error("BackfillStockPriceChunk FAILED [{$symbolList}]: " . $e->getMessage());
    }
}

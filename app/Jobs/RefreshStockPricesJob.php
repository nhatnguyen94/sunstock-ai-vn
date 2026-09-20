<?php

namespace App\Jobs;

use App\Frontend\Services\StockService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Incremental price refresh of a few symbols, queued by StockPriceFreshness when a stock page finds its history
 * behind the last completed session. Only the missing sessions are fetched (`$from`), so it is a ~1 s job.
 */
class RefreshStockPricesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 90;
    public int $tries   = 2;
    public int $backoff = 60;

    /**
     * @param string[] $symbols
     * @param ?string  $from    Y-m-d of the first session to fetch (null = one year)
     */
    public function __construct(private readonly array $symbols, private readonly ?string $from = null) {}

    /** Human-readable identifier for Admin > Giám sát Queue — see App\Support\QueueJobLogger. */
    public function queueSummary(): string
    {
        return 'cập nhật giá ' . implode(', ', array_slice($this->symbols, 0, 3)) . ($this->from ? " từ {$this->from}" : '');
    }

    public function handle(StockService $stocks): void
    {
        $result = $stocks->refreshPrices($this->symbols, $this->from, 60);

        // Nothing stored is not an error when the source simply had no new session; only a hard failure is retried
        if ($result['stored'] === 0 && isset($result['errors']['*'])) {
            Log::warning('RefreshStockPricesJob failed', ['symbols' => $this->symbols, 'error' => $result['errors']['*']]);

            throw new \RuntimeException((string) $result['errors']['*']);
        }
    }
}

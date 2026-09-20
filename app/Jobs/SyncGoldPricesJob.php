<?php

namespace App\Jobs;

use App\Frontend\Services\GoldPriceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Background refresh of the gold price table.
 * Dispatched by GoldPriceService::queueRefresh() (a stale page was just viewed).
 */
class SyncGoldPricesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** PythonRunner ceiling is 60s; leave room for the DB writes. */
    public int $timeout = 90;
    public int $tries   = 2;
    public int $backoff = 60;

    /** Human-readable identifier for Admin > Giám sát Queue — see App\Support\QueueJobLogger. */
    public function queueSummary(): string
    {
        return 'giá vàng SJC + BTMC';
    }

    public function handle(GoldPriceService $service): void
    {
        $result = $service->sync();

        if (isset($result['error'])) {
            Log::warning('SyncGoldPricesJob failed', ['error' => $result['error']]);

            throw new \RuntimeException($result['error']);   // let the queue retry
        }
    }
}

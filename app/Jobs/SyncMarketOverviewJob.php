<?php

namespace App\Jobs;

use App\Frontend\Services\MarketOverviewService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Background refresh of the market overview (indices, breadth, movers, quotes).
 * Dispatched by MarketOverviewService::queueRefresh() when a page finds the snapshot stale.
 */
class SyncMarketOverviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 90;
    public int $tries   = 2;
    public int $backoff = 30;

    /** Human-readable identifier for Admin > Giám sát Queue — see App\Support\QueueJobLogger. */
    public function queueSummary(): string
    {
        return 'tổng quan thị trường (VN-Index, top tăng/giảm)';
    }

    public function handle(MarketOverviewService $service): void
    {
        $result = $service->sync();

        if (isset($result['error'])) {
            Log::warning('SyncMarketOverviewJob failed', ['error' => $result['error']]);

            throw new \RuntimeException($result['error']);   // let the queue retry
        }
    }
}

<?php

namespace App\Jobs;

use App\Frontend\Services\WorldMarketService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/** Background refresh of the world indices strip; dispatched by WorldMarketService::queueRefresh() when a page finds it missing or old. */
class SyncWorldMarketsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 90;

    public int $tries = 2;

    public int $backoff = 60;

    /** Human-readable identifier for Admin > Giám sát Queue — see App\Support\QueueJobLogger. */
    public function queueSummary(): string
    {
        return 'chỉ số thế giới (S&P 500, Nasdaq, Nikkei…)';
    }

    public function handle(WorldMarketService $service): void
    {
        $result = $service->sync();

        if (isset($result['error'])) {
            Log::warning('SyncWorldMarketsJob failed', ['error' => $result['error']]);

            throw new RuntimeException($result['error']);   // let the queue retry
        }
    }
}

<?php

namespace App\Jobs;

use App\Frontend\Services\StockSignalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Background build of the "Tín hiệu" tab (breakouts, volume spikes, RSI extremes); dispatched by StockSignalService::queueBuild(). */
class BuildStockSignalsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 120;

    public int $tries = 2;

    public int $backoff = 30;

    /** Human-readable identifier for Admin > Giám sát Queue — see App\Support\QueueJobLogger. */
    public function queueSummary(): string
    {
        return 'tín hiệu cổ phiếu (vượt đỉnh, khối lượng đột biến, RSI)';
    }

    public function handle(StockSignalService $service): void
    {
        $service->build();
    }
}

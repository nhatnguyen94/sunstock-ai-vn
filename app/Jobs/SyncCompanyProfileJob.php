<?php

namespace App\Jobs;

use App\Frontend\Services\CompanyProfileService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Refreshes one company's cached profile in the background.
 * Dispatched by: CompanyProfileService::queueRefresh() (a stale profile was just viewed)
 *                and `php artisan sync:company-profiles --dispatch`.
 */
class SyncCompanyProfileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** PythonRunner ceiling is 60s; leave room for the DB write and logging. */
    public int $timeout = 90;
    public int $tries   = 2;
    public int $backoff = 120;

    public function __construct(private string $symbol) {}

    /** Human-readable identifier for Admin > Giám sát Queue — see App\Support\QueueJobLogger. */
    public function queueSummary(): string
    {
        return $this->symbol;
    }

    public function handle(CompanyProfileService $service): void
    {
        $result = $service->sync($this->symbol);

        if (isset($result['error'])) {
            Log::warning('SyncCompanyProfileJob failed', ['symbol' => $this->symbol, 'error' => $result['error']]);

            // A transient failure (data source down) is retried by the queue (tries/backoff);
            // "no such company" is a final answer, retrying would just repeat it.
            if (! ($result['not_found'] ?? false)) {
                throw new \RuntimeException($result['error']);
            }
        }
    }
}

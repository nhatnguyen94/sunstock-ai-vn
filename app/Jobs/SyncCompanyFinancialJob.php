<?php

namespace App\Jobs;

use App\Frontend\Interfaces\CompanyFinancialRepositoryInterface;
use App\Frontend\Services\CompanyFinancialService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queue job that syncs all financial report types/periods for one stock symbol.
 * Dispatched by: php artisan sync:company-financials --dispatch
 * Processed by:  php artisan queue:work --timeout=300
 */
class SyncCompanyFinancialJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Each job: up to 8 Python calls × ~5s each = ~40s max */
    public int $timeout = 180;
    public int $tries   = 2;
    public int $backoff = 60;

    private const TYPES   = ['income', 'balance', 'cashflow', 'ratio'];
    private const PERIODS = ['quarter', 'year'];

    public function __construct(
        private string $symbol,
        private array  $types   = self::TYPES,
        private array  $periods = self::PERIODS,
        private bool   $stale   = false,
    ) {}

    public function handle(
        CompanyFinancialService $service,
        CompanyFinancialRepositoryInterface $repo
    ): void {
        $synced = $skipped = $unavailable = $failed = 0;

        foreach ($this->types as $type) {
            foreach ($this->periods as $period) {
                // Skip fresh records when stale-mode is on
                if ($this->stale) {
                    $existing = $repo->find($this->symbol, $type, $period);
                    if ($existing && ! $existing->isStale()) {
                        $skipped++;
                        continue;
                    }
                }

                $result = $service->syncSymbol($this->symbol, $type, $period);

                if (isset($result['error'])) {
                    Log::warning('SyncCompanyFinancialJob error', [
                        'symbol' => $this->symbol, 'type' => $type, 'period' => $period,
                        'error'  => $result['error'],
                    ]);
                    $failed++;
                    continue;
                }

                if (empty($result['data'])) {
                    $unavailable++;
                    continue;
                }

                $synced++;
            }
        }

        Log::info("SyncCompanyFinancialJob done [{$this->symbol}]", compact('synced', 'skipped', 'unavailable', 'failed'));
    }
}

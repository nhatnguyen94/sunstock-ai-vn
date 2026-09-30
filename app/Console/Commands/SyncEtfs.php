<?php

namespace App\Console\Commands;

use App\Frontend\Services\EtfService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncEtfs extends Command
{
    protected $signature = 'sync:etfs';

    protected $description = 'Refresh the list of ETFs and listed funds (symbol + name) from KBS via vnstock; prices come from the regular price sync.';

    public function __construct(private readonly EtfService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->service->syncAll();

        if (isset($result['error'])) {
            $this->error($result['error']);
            Log::warning('sync:etfs failed', ['error' => $result['error']]);

            return 1;
        }

        $this->info("Synced {$result['count']} ETFs/funds" . (($result['pruned'] ?? 0) ? " ({$result['pruned']} no longer listed removed)." : '.'));
        Log::info('sync:etfs complete', $result);

        return 0;
    }
}

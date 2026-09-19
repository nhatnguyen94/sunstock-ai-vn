<?php

namespace App\Console\Commands;

use App\Frontend\Services\FundService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncFunds extends Command
{
    protected $signature = 'sync:funds';

    protected $description = 'Refresh the open-ended fund catalog (NAV + returns) from Fmarket via vnstock. One call updates every fund.';

    public function __construct(private readonly FundService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->service->syncAll();

        if (isset($result['error'])) {
            $this->error($result['error']);
            Log::warning('sync:funds failed', ['error' => $result['error']]);

            return 1;
        }

        $this->info("Synced {$result['count']} funds.");
        Log::info('sync:funds complete', ['count' => $result['count']]);

        return 0;
    }
}

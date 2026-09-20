<?php

namespace App\Console\Commands;

use App\Frontend\Services\GoldPriceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncGoldPrices extends Command
{
    protected $signature = 'sync:gold-prices';

    protected $description = 'Fetch SJC + BTMC gold/silver prices and the world gold price via vnstock and store new quotes (every 15 min in trading hours).';

    public function __construct(private readonly GoldPriceService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->service->sync();

        if (isset($result['error'])) {
            $this->error($result['error']);
            Log::warning('sync:gold-prices failed', ['error' => $result['error']]);

            return 1;
        }

        foreach ($result['warnings'] as $w) {
            $this->warn($w);
        }
        $this->info("Stored {$result['count']} new gold/silver quotes.");
        Log::info('sync:gold-prices complete', ['count' => $result['count']]);

        return 0;
    }
}

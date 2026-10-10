<?php

namespace App\Console\Commands;

use App\Frontend\Services\WorldMarketService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncWorldMarkets extends Command
{
    protected $signature = 'sync:world-markets';

    protected $description = 'Fetch the world stock indices (S&P 500, Nasdaq, Dow, FTSE, DAX, Nikkei, Hang Seng, Shanghai) for the home page strip — every 30 minutes.';

    public function __construct(private readonly WorldMarketService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->service->sync();

        if (isset($result['error'])) {
            $this->error($result['error']);
            Log::warning('sync:world-markets failed', ['error' => $result['error']]);

            return 1;
        }

        foreach ($result['errors'] as $code => $why) {
            $this->warn("{$code}: {$why}");
        }
        $this->info("World markets stored ({$result['markets']} markets).");

        return 0;
    }
}

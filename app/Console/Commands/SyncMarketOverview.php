<?php

namespace App\Console\Commands;

use App\Frontend\Services\MarketOverviewService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncMarketOverview extends Command
{
    protected $signature = 'sync:market-overview';

    protected $description = 'Fetch indices, breadth, liquidity, top movers and a quote per symbol from KBS (via vnstock) — every 5 min during the session.';

    public function __construct(private readonly MarketOverviewService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->service->sync();

        if (isset($result['error'])) {
            $this->error($result['error']);
            Log::warning('sync:market-overview failed', ['error' => $result['error']]);

            return 1;
        }

        foreach ($result['warnings'] as $w) {
            $this->warn($w);
        }
        $this->info("Market snapshot for {$result['trade_date']} stored ({$result['symbols']} quotes).");
        Log::info('sync:market-overview complete', $result);

        return 0;
    }
}

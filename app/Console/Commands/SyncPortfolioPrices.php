<?php

namespace App\Console\Commands;

use App\Frontend\Services\PortfolioService;
use Illuminate\Console\Command;

class SyncPortfolioPrices extends Command
{
    protected $signature = 'sync:portfolio-prices';

    protected $description = 'Refresh current_price for all portfolio items from the latest synced stock prices, and send target/stop-loss email alerts.';

    public function handle(PortfolioService $portfolioService)
    {
        $this->info('Refreshing portfolio prices and checking alerts...');

        $updated = $portfolioService->refreshAllPortfolioPrices();

        $this->info("Done. Refreshed {$updated} portfolio(s).");

        return self::SUCCESS;
    }
}

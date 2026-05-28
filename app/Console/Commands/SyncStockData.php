<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Frontend\Services\StockService;

class SyncStockData extends Command
{
    protected $signature = 'sync:stock-data';
    protected $description = 'Sync all stock data from Python scripts to database';

    protected $stockService;

    public function __construct(StockService $stockService)
    {
        parent::__construct();
        $this->stockService = $stockService;
    }

    public function handle()
    {
        $this->info('Starting stock symbol and details sync...');
        $result = $this->stockService->syncStockSymbolsAndDetails();

        if ($result['success']) {
            $this->info('Stock symbol and details sync completed successfully. ' . $result['message']);
        } else {
            $this->error('Stock symbol and details sync failed: ' . $result['message']);
        }
        return $result['success'] ? 0 : 1;
    }
}

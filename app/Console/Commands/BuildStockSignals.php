<?php

namespace App\Console\Commands;

use App\Frontend\Services\StockSignalService;
use Illuminate\Console\Command;

class BuildStockSignals extends Command
{
    protected $signature = 'signals:build';

    protected $description = 'Build the home page "Tín hiệu" lists (52-week breakouts/breakdowns, volume spikes, RSI extremes) from today\'s quotes and the stored history.';

    public function __construct(private readonly StockSignalService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->service->build();

        if ($result === null) {
            $this->warn('No market snapshot yet: run sync:market-overview first.');

            return 1;
        }

        $this->info("Signals for {$result['trade_date']}: {$result['universe']} stocks evaluated, "
            ."{$result['breakout_total']} breakouts, {$result['breakdown_total']} breakdowns, {$result['volume_total']} volume spikes, "
            ."{$result['overbought_total']} overbought, {$result['oversold_total']} oversold.");

        return 0;
    }
}

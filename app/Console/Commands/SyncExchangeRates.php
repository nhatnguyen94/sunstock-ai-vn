<?php

namespace App\Console\Commands;

use App\Frontend\Services\ExchangeRateService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncExchangeRates extends Command
{
    protected $signature = 'sync:exchange-rates {--days=1 : Số ngày cần lấy (mặc định: hôm nay)}';
    protected $description = 'Fetch exchange rates from Python/Vietcombank and store in database';

    public function __construct(protected ExchangeRateService $exchangeService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $this->info("Fetching exchange rates for last {$days} day(s)...");

        try {
            if ($days <= 1) {
                $date = Carbon::now()->format('Y-m-d');
                $this->info("Fetching for date: {$date}");
                $result = $this->exchangeService->fetchRatesFromPython($date);
            } else {
                $result = $this->exchangeService->fetchRatesFromPython($days);
            }

            if (empty($result)) {
                $this->warn('No exchange rate data returned (market may be closed today).');
                return 0;
            }

            // fetchRatesFromPython returns raw data; persist via the repository through the service
            // Re-use existing saveRate logic by calling getRatesByDate which triggers save
            foreach ($result as $date => $items) {
                foreach ($items as $item) {
                    app(\App\Frontend\Interfaces\ExchangeRateRepositoryInterface::class)->saveRate($item);
                }
                // Bust date-specific cache
                Cache::forget("exchange_rates_{$date}");
            }

            // Bust homepage cache key
            Cache::forget('exchange_rates_home_' . Carbon::now()->format('Y-m-d'));

            $total = array_sum(array_map('count', $result));
            $this->info("Synced {$total} exchange rate records across " . count($result) . " date(s).");
            Log::info('sync:exchange-rates: synced', ['dates' => array_keys($result), 'total' => $total]);

            return 0;
        } catch (\Throwable $e) {
            $this->error('Failed: ' . $e->getMessage());
            Log::error('sync:exchange-rates error', ['msg' => $e->getMessage()]);
            return 1;
        }
    }
}

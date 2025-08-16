<?php
namespace App\Repositories;

use App\Models\ExchangeRate;
use App\Services\ExchangeRateService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ExchangeRateRepository implements ExchangeRateRepositoryInterface
{
    protected $exchangeRateService;

    public function __construct(ExchangeRateService $exchangeRateService)
    {
        $this->exchangeRateService = $exchangeRateService;
    }

    public function getLatestRates(int $days = 3): array
    {
        $dates = [];
        for ($i = 0; $i < $days; $i++) {
            $dates[] = Carbon::today()->subDays($i)->toDateString();
        }
        return ExchangeRate::whereIn('date', $dates)
            ->orderByDesc('date')
            ->get()
            ->groupBy('date')
            ->toArray();
    }

    public function updateRatesFromPython(): void
    {
        $rates = $this->exchangeRateService->fetchRatesFromPython();
        $bulk = [];
        foreach ($rates as $dayRates) {
            foreach ($dayRates as $rate) {
                if (isset($rate['error'])) continue;
                $bulk[] = [
                    'date' => $rate['date'],
                    'currency_code' => $rate['currency_code'],
                    'currency_name' => $rate['currency_name'],
                    'buy_cash' => $rate['buy _cash'] !== '-' ? $rate['buy _cash'] : null,
                    'buy_transfer' => $rate['buy _transfer'] !== '-' ? $rate['buy _transfer'] : null,
                    'sell' => $rate['sell'] !== '-' ? $rate['sell'] : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        DB::table('exchange_rates')->upsert(
            $bulk,
            ['date', 'currency_code'],
            ['buy_cash', 'buy_transfer', 'sell', 'currency_name', 'updated_at']
        );
        Cache::put('exchange_rates_last_update', now(), 3600);
    }
}
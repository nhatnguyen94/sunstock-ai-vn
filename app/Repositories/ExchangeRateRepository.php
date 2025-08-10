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
        foreach ($rates as $rate) {
            $bulk[] = [
                'date' => $rate['date'],
                'currency' => $rate['currency'],
                'buy' => $rate['buy'],
                'sell' => $rate['sell'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('exchange_rates')->upsert($bulk, ['date', 'currency'], ['buy', 'sell', 'updated_at']);
        Cache::put('exchange_rates_last_update', now(), 3600);
    }
}
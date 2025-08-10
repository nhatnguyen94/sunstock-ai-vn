<?php
namespace App\Repositories;

use App\Models\ExchangeRate;
use App\Services\ExchangeRateService;
use Carbon\Carbon;

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
        $data = $this->exchangeRateService->fetchRatesFromPython();
        foreach ($data as $dayRates) {
            if (is_array($dayRates)) {
                foreach ($dayRates as $item) {
                    ExchangeRate::updateOrCreate(
                        [
                            'currency_code' => $item['currency_code'],
                            'date' => $item['date']
                        ],
                        [
                            'currency_name' => $item['currency_name'],
                            'buy_cash' => $item['buy _cash'] ?? null,
                            'buy_transfer' => $item['buy _transfer'] ?? null,
                            'sell' => $item['sell'] ?? null,
                        ]
                    );
                }
            }
        }
    }
}
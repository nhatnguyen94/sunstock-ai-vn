<?php
namespace App\Repositories;

use App\Models\ExchangeRate;
use App\Interfaces\ExchangeRateRepositoryInterface;

class ExchangeRateRepository implements ExchangeRateRepositoryInterface
{
    public function getLatestRates($days = 3)
    {
        return ExchangeRate::where('date', '>=', now()->subDays($days)->format('Y-m-d'))
            ->orderBy('date', 'desc')
            ->get()
            ->groupBy('date')
            ->map(function ($group) {
                return $group->map(function ($item) {
                    return $item->toArray();
                })->toArray();
            })
            ->toArray();
    }

    public function getRatesByDate($date)
    {
        return ExchangeRate::where('date', $date)->get()->map(function ($item) {
            return $item->toArray();
        })->toArray();
    }
}
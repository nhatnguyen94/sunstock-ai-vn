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

    public function saveRate($item)
    {
        // Chuẩn hóa key cho đúng với model
        $data = [
            'currency_code'   => $item['currency_code'] ?? null,
            'currency_name'   => $item['currency_name'] ?? null,
            'buy_cash'        => $item['buy_cash'] ?? $item['buy _cash'] ?? null,
            'buy_transfer'    => $item['buy_transfer'] ?? $item['buy _transfer'] ?? null,
            'sell'            => $item['sell'] ?? null,
            'date'            => $item['date'] ?? null,
        ];
        // Lưu hoặc cập nhật theo mã và ngày
        return ExchangeRate::updateOrCreate(
            [
                'currency_code' => $data['currency_code'],
                'date' => $data['date'],
            ],
            $data
        );
    }
}
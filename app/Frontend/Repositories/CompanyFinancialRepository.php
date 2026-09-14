<?php

namespace App\Frontend\Repositories;

use App\Frontend\Interfaces\CompanyFinancialRepositoryInterface;
use App\Models\CompanyFinancial;
use Illuminate\Database\Eloquent\Collection;

class CompanyFinancialRepository implements CompanyFinancialRepositoryInterface
{
    public function find(string $symbol, string $type, string $period): ?CompanyFinancial
    {
        return CompanyFinancial::where('symbol', $symbol)
            ->where('type', $type)
            ->where('period', $period)
            ->first();
    }

    public function upsert(string $symbol, string $type, string $period, array $rawData): void
    {
        CompanyFinancial::updateOrCreate(
            ['symbol' => $symbol, 'type' => $type, 'period' => $period],
            ['raw_data' => $rawData, 'synced_at' => now()]
        );
    }

    public function getAllRatiosByPeriod(string $period): Collection
    {
        return CompanyFinancial::where('type', 'ratio')
            ->where('period', $period)
            ->get(['symbol', 'raw_data', 'synced_at']);
    }
}

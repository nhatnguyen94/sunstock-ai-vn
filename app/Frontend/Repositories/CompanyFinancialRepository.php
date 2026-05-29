<?php

namespace App\Frontend\Repositories;

use App\Frontend\Interfaces\CompanyFinancialRepositoryInterface;
use App\Models\CompanyFinancial;

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
}

<?php

namespace App\Frontend\Interfaces;

use App\Models\CompanyFinancial;
use Illuminate\Database\Eloquent\Collection;

interface CompanyFinancialRepositoryInterface
{
    /**
     * Find a cached financial record from DB.
     */
    public function find(string $symbol, string $type, string $period): ?CompanyFinancial;

    /**
     * Persist (insert or update) a fetched financial record.
     */
    public function upsert(string $symbol, string $type, string $period, array $rawData): void;

    /**
     * All cached ratio records for a given period ("year" or "quarter") — used by the stock screener.
     */
    public function getAllRatiosByPeriod(string $period): Collection;
}

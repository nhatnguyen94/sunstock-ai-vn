<?php

namespace App\Frontend\Interfaces;

use App\Models\CompanyFinancial;

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
}

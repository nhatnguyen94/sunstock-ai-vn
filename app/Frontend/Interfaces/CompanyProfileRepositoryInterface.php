<?php

namespace App\Frontend\Interfaces;

use App\Models\CompanyProfile;

interface CompanyProfileRepositoryInterface
{
    public function find(string $symbol): ?CompanyProfile;

    /** Insert or update the cached profile for a symbol and stamp synced_at. */
    public function upsert(string $symbol, array $data): CompanyProfile;

    /**
     * Symbols whose cached profile is older than CompanyProfile::STALE_DAYS, oldest first.
     *
     * @return string[]
     */
    public function staleSymbols(int $limit): array;

    /**
     * Every symbol that already has a cached profile.
     *
     * @return string[]
     */
    public function allSymbols(): array;
}

<?php

namespace App\Frontend\Repositories;

use App\Frontend\Interfaces\CompanyProfileRepositoryInterface;
use App\Models\CompanyProfile;

class CompanyProfileRepository implements CompanyProfileRepositoryInterface
{
    public function find(string $symbol): ?CompanyProfile
    {
        return CompanyProfile::where('symbol', $symbol)->first();
    }

    public function findMany(array $symbols): \Illuminate\Database\Eloquent\Collection
    {
        return CompanyProfile::whereIn('symbol', $symbols)->get();
    }

    public function upsert(string $symbol, array $data): CompanyProfile
    {
        return CompanyProfile::updateOrCreate(
            ['symbol' => $symbol],
            ['data' => $data, 'synced_at' => now()]
        );
    }

    public function staleSymbols(int $limit): array
    {
        return CompanyProfile::where('synced_at', '<', now()->subDays(CompanyProfile::STALE_DAYS))
            ->orWhereNull('synced_at')
            ->orderBy('synced_at')
            ->limit($limit)
            ->pluck('symbol')
            ->all();
    }

    public function allSymbols(): array
    {
        return CompanyProfile::pluck('symbol')->all();
    }
}

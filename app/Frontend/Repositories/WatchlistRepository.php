<?php

namespace App\Frontend\Repositories;

use App\Frontend\Interfaces\WatchlistRepositoryInterface;
use App\Models\WatchlistItem;

class WatchlistRepository implements WatchlistRepositoryInterface
{
    public function symbols(int $userId): array
    {
        return WatchlistItem::where('user_id', $userId)->orderByDesc('id')->pluck('symbol')->all();
    }

    public function has(int $userId, string $symbol): bool
    {
        return WatchlistItem::where(['user_id' => $userId, 'symbol' => $symbol])->exists();
    }

    public function count(int $userId): int
    {
        return WatchlistItem::where('user_id', $userId)->count();
    }

    public function add(int $userId, string $symbol): bool
    {
        return WatchlistItem::firstOrCreate(['user_id' => $userId, 'symbol' => $symbol])->wasRecentlyCreated;
    }

    public function remove(int $userId, string $symbol): bool
    {
        return WatchlistItem::where(['user_id' => $userId, 'symbol' => $symbol])->delete() > 0;
    }
}

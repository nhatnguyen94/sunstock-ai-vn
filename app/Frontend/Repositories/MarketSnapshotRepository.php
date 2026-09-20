<?php

namespace App\Frontend\Repositories;

use App\Frontend\Interfaces\MarketSnapshotRepositoryInterface;
use App\Models\MarketSnapshot;
use Illuminate\Support\Carbon;

class MarketSnapshotRepository implements MarketSnapshotRepositoryInterface
{
    public function latest(bool $withQuotes = false): ?MarketSnapshot
    {
        return MarketSnapshot::query()
            ->when(! $withQuotes, fn ($q) => $q->select(['id', 'trade_date', 'data', 'fetched_at', 'synced_at']))
            ->orderByDesc('trade_date')
            ->first();
    }

    public function previous(Carbon $before): ?MarketSnapshot
    {
        return MarketSnapshot::query()
            ->select(['id', 'trade_date', 'data', 'fetched_at', 'synced_at'])
            ->where('trade_date', '<', $before->toDateString())
            ->orderByDesc('trade_date')
            ->first();
    }

    public function save(array $payload): MarketSnapshot
    {
        $quotes = $payload['quotes'] ?? [];
        $tradeDate = $payload['trade_date'];

        $data = $payload;
        unset($data['quotes']);

        $snapshot = MarketSnapshot::updateOrCreate(
            ['trade_date' => $tradeDate],
            [
                'data' => $data,
                'quotes' => $quotes ?: null,
                'fetched_at' => $payload['fetched_at'] ?? now(),
                'synced_at' => now(),
            ]
        );

        // Only the newest session keeps its 1,500+ quotes; older sessions keep the overview (liquidity history)
        MarketSnapshot::where('trade_date', '<', $tradeDate)->whereNotNull('quotes')->update(['quotes' => null]);

        return $snapshot;
    }
}

<?php

namespace App\Frontend\Services;

use App\Frontend\Interfaces\MarketSnapshotRepositoryInterface;
use App\Frontend\Interfaces\StockRepositoryInterface;
use App\Support\MarketHeatmap;
use Illuminate\Support\Facades\Cache;

/**
 * Data for the home page heat map: the busiest stocks of the newest market snapshot with their industry. Reads the
 * stored snapshot only (no Python, no network); the shaped result is cached per snapshot, so the 60-second poll of
 * many visitors costs one build per refresh.
 */
class MarketHeatmapService
{
    /** Tiles drawn: enough to read as a whole market, few enough for a phone. */
    public const LIMIT = 150;

    public function __construct(
        private readonly MarketSnapshotRepositoryInterface $snapshots,
        private readonly StockRepositoryInterface $stocks
    ) {}

    /** @return array<string, mixed>|null null while there is no snapshot (or it holds no quotes) */
    public function heatmap(): ?array
    {
        $snapshot = $this->snapshots->latest(true);
        $quotes = $snapshot?->quotes;
        if (empty($quotes)) {
            return null;
        }

        $key = 'market:heatmap:v1:'.$snapshot->trade_date->toDateString().':'.($snapshot->synced_at?->timestamp ?? 0);

        return Cache::remember($key, 600, function () use ($quotes, $snapshot) {
            $top = MarketHeatmap::topSymbols($quotes, self::LIMIT);

            return MarketHeatmap::build(array_intersect_key($quotes, array_flip($top)), $this->stocks->symbolInfo($top), self::LIMIT)
                + ['trade_date' => $snapshot->trade_date->toDateString(), 'as_of' => $snapshot->synced_at?->toIso8601String()];
        });
    }
}

<?php

namespace App\Frontend\Services;

use App\Frontend\Interfaces\MarketSnapshotRepositoryInterface;
use App\Frontend\Interfaces\StockRepositoryInterface;
use App\Jobs\BuildStockSignalsJob;
use App\Support\StockSignals;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Feeds the home page "Tín hiệu" tab. Building the signals reads about 300 000 stored daily bars (≈ 1.3 s), so a page view NEVER does it:
 * pages only read the cached result, and a missing or old one queues one deduplicated background build (`BuildStockSignalsJob`, also run by the
 * scheduler through `signals:build`). Until the first build exists the tab says so instead of waiting.
 */
class StockSignalService
{
    public const CACHE_KEY = 'market:signals:v1';

    /** During the session a cached result older than this is rebuilt; outside it the numbers do not move, so it can age much longer. */
    public const STALE_OPEN_MINUTES = 30;

    public const STALE_CLOSED_HOURS = 6;

    /** How far back the highs/lows look, and how many days of bars feed the volume average and the RSI. */
    private const WINDOW_DAYS = 370;

    private const BAR_DAYS = 60;

    public function __construct(
        private readonly MarketSnapshotRepositoryInterface $snapshots,
        private readonly StockRepositoryInterface $stocks,
        private readonly MarketOverviewService $market
    ) {}

    /** @return array<string, mixed>|null null until the first build has finished */
    public function cached(): ?array
    {
        $data = Cache::get(self::CACHE_KEY);
        $builtAt = isset($data['built_at']) ? Carbon::parse($data['built_at']) : null;

        $stale = $builtAt === null || ($this->market->isMarketOpen()
            ? $builtAt->diffInMinutes(now()) >= self::STALE_OPEN_MINUTES
            : $builtAt->diffInHours(now()) >= self::STALE_CLOSED_HOURS);
        if ($stale) {
            $this->queueBuild();
        }

        return $data;
    }

    /**
     * Compute the signals from the newest snapshot and the stored history and cache them. The heavy part: call it from the job/command only.
     *
     * @return array<string, mixed>|null null without a snapshot
     */
    public function build(): ?array
    {
        $snapshot = $this->snapshots->latest(true);
        if ($snapshot === null || empty($snapshot->quotes)) {
            return null;
        }

        $today = $snapshot->trade_date->toDateString();
        $from = $snapshot->trade_date->copy()->subDays(self::WINDOW_DAYS)->toDateString();
        $barsFrom = $snapshot->trade_date->copy()->subDays(self::BAR_DAYS)->toDateString();

        $result = StockSignals::evaluate(
            $snapshot->quotes,
            $this->stocks->priceExtremes($from, $today),
            $this->stocks->recentBars($barsFrom, $today),
            $today
        ) + [
            'trade_date' => $today,
            'as_of' => $snapshot->synced_at?->toIso8601String(),
            'built_at' => now()->toIso8601String(),
        ];

        Cache::put(self::CACHE_KEY, $result, now()->addDay());

        return $result;
    }

    /** @return bool true when a job was queued (false = one is already pending) */
    public function queueBuild(): bool
    {
        if (! Cache::add('market:signals:queued', 1, 300)) {
            return false;
        }

        BuildStockSignalsJob::dispatch();

        return true;
    }
}

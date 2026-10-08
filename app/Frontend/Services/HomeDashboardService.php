<?php

namespace App\Frontend\Services;

use App\Support\MarketSentiment;
use Throwable;

/**
 * Everything the home page shows beyond the market snapshot itself, gathered in one place so `StockController::home` stays small:
 * the sentiment reading, the world indices strip, the "Vàng · Tỷ giá · Quỹ" card, the signals and the events calendar.
 *
 * Every part is an extra: it is computed (or read from a cache) independently, and a failure in one only removes that part (null) — it can never
 * take the page down. No part waits for Python or for a heavy query.
 */
class HomeDashboardService
{
    public function __construct(
        private readonly MarketPulseService $pulse,
        private readonly WorldMarketService $world,
        private readonly StockSignalService $signals,
        private readonly HomeEventsService $events,
        private readonly WatchlistService $watchlist,
        private readonly PortfolioService $portfolio
    ) {}

    /**
     * @param  array<string, mixed>  $market  MarketOverviewService::overview()
     * @return array{pulse: ?array, world: ?array, sentiment: ?array, signals: ?array, events: ?array}
     */
    public function build(array $market, ?int $userId): array
    {
        return [
            'pulse' => $this->safely(fn () => $this->pulse->build()),
            'world' => $this->safely(fn () => $this->world->snapshot()),
            'sentiment' => $this->safely(fn () => ($market['has_data'] ?? false) ? MarketSentiment::compute($market) : null),
            'signals' => $this->safely(fn () => $this->signals->cached()),
            'events' => $this->safely(fn () => $this->events->forHome($this->mySymbols($userId))),
        ];
    }

    /** The signed-in visitor's own symbols: watchlist first, then what they hold. @return string[] */
    private function mySymbols(?int $userId): array
    {
        if ($userId === null) {
            return [];
        }

        $symbols = $this->watchlist->symbols($userId);
        foreach ($this->portfolio->getUserPortfolios($userId, true) as $portfolio) {
            foreach ($portfolio->items as $item) {
                $symbols[] = $item->stock_symbol;
            }
        }

        return array_values(array_unique($symbols));
    }

    private function safely(callable $part): mixed
    {
        try {
            return $part();
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}

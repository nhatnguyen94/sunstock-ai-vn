<?php

namespace App\Frontend\Services;

use App\Frontend\Interfaces\PortfolioRepositoryInterface;
use App\Frontend\Interfaces\StockRepositoryInterface;
use App\Models\Portfolio;
use App\Models\PortfolioItem;
use App\Jobs\ProcessStockPriceSync;
use App\Notifications\PortfolioAlertNotification;
use App\Support\PortfolioPerformance;
use App\Support\PriceUnit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PortfolioService
{
    public function __construct(
        private PortfolioRepositoryInterface $portfolioRepository,
        private StockRepositoryInterface $stockRepository,
        private ?CompanyProfileService $companyProfiles = null
    ) {}

    /**
     * Portfolio Management
     */
    public function getUserPortfolios(int $userId, bool $activeOnly = false): Collection
    {
        return $activeOnly
            ? $this->portfolioRepository->getActiveByUser($userId)
            : $this->portfolioRepository->getAllByUser($userId);
    }

    public function getPortfoliosPaginated(int $userId, int $perPage = 10): LengthAwarePaginator
    {
        return $this->portfolioRepository->paginate($userId, $perPage);
    }

    public function createPortfolio(int $userId, array $data): Portfolio
    {
        $portfolioData = [
            'user_id' => $userId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'total_invested' => 0,
            'current_value' => 0,
            'is_active' => true,
        ];

        return $this->portfolioRepository->create($portfolioData);
    }

    public function updatePortfolio(int $portfolioId, int $userId, array $data): ?Portfolio
    {
        $portfolio = $this->portfolioRepository->findByIdAndUser($portfolioId, $userId);

        if (! $portfolio) {
            return null;
        }

        $updateData = [
            'name' => $data['name'] ?? $portfolio->name,
            'description' => $data['description'] ?? $portfolio->description,
            'is_active' => $data['is_active'] ?? $portfolio->is_active,
        ];

        $this->portfolioRepository->update($portfolio, $updateData);

        return $portfolio->refresh();
    }

    public function deletePortfolio(int $portfolioId, int $userId): bool
    {
        $portfolio = $this->portfolioRepository->findByIdAndUser($portfolioId, $userId);

        if (! $portfolio) {
            return false;
        }

        return $this->portfolioRepository->delete($portfolio);
    }

    public function getPortfolioById(int $portfolioId, int $userId): ?Portfolio
    {
        return $this->portfolioRepository->findByIdAndUser($portfolioId, $userId);
    }

    /**
     * Portfolio Items Management
     */
    public function addStockToPortfolio(int $portfolioId, int $userId, array $stockData): ?PortfolioItem
    {
        $portfolio = $this->portfolioRepository->findByIdAndUser($portfolioId, $userId);

        if (! $portfolio) {
            return null;
        }

        $symbol = strtoupper(trim($stockData['stock_symbol']));
        $stockData['stock_symbol'] = $symbol;

        // The user only has to pick the ticker; the company name comes from our symbol list
        if (empty($stockData['stock_name'])) {
            $stockData['stock_name'] = $this->stockRepository->getOverview($symbol)['name'] ?? $symbol;
        }

        // Value the holding at the real market price straight away, not at what it was bought for
        $quote = $this->fetchQuotes([$symbol])[$symbol] ?? null;
        $stockData['current_price'] = $quote['price'] ?? $stockData['buy_price'];
        $stockData['previous_price'] = $quote['prev'] ?? null;
        $stockData['price_date'] = $quote['date'] ?? null;

        // Check if stock already exists in portfolio
        $existingItem = $this->portfolioRepository->findItemByPortfolioAndSymbol($portfolioId, $symbol);

        if ($existingItem) {
            // If exists, update quantity and average price
            return $this->updateExistingPosition($existingItem, $stockData);
        }

        // Create new item
        $itemData = [
            'portfolio_id' => $portfolioId,
            'stock_symbol' => $symbol,
            'stock_name' => $stockData['stock_name'],
            'quantity' => $stockData['quantity'],
            'buy_price' => $stockData['buy_price'],
            'current_price' => $stockData['current_price'],
            'previous_price' => $stockData['previous_price'],
            'price_date' => $stockData['price_date'],
            'buy_date' => $stockData['buy_date'],
            'target_price' => $stockData['target_price'] ?? null,
            'stop_loss_price' => $stockData['stop_loss_price'] ?? null,
            'notes' => $stockData['notes'] ?? null,
        ];

        // A symbol nobody has price data for yet would sit at its buy price forever: queue its history
        $this->requestPriceSync($quote ? [] : [$symbol]);

        return $this->portfolioRepository->createItem($itemData);
    }

    public function updatePortfolioItem(int $itemId, int $userId, array $data): ?PortfolioItem
    {
        $item = $this->portfolioRepository->findItemById($itemId);

        if (! $item || $item->portfolio->user_id !== $userId) {
            return null;
        }

        $this->portfolioRepository->updateItem($item, $data);

        return $item->refresh();
    }

    /** A holding, only if it belongs to one of this user's portfolios. */
    public function findItemForUser(int $itemId, int $userId): ?PortfolioItem
    {
        $item = $this->portfolioRepository->findItemById($itemId);

        return ($item && $item->portfolio && $item->portfolio->user_id === $userId) ? $item : null;
    }

    public function removeStockFromPortfolio(int $itemId, int $userId): bool
    {
        $item = $this->portfolioRepository->findItemById($itemId);

        if (! $item || $item->portfolio->user_id !== $userId) {
            return false;
        }

        return $this->portfolioRepository->deleteItem($item);
    }

    /**
     * Portfolio Analytics
     *
     * Prices are refreshed from the synced quotes on every view (one cheap DB lookup) — the user never has
     * to remember to press "update" to see today's numbers.
     */
    public function getPortfolioAnalytics(int $portfolioId, int $userId): ?array
    {
        $portfolio = $this->portfolioRepository->findByIdAndUser($portfolioId, $userId);

        if (! $portfolio) {
            return null;
        }

        $refresh = $this->refreshPortfolio($portfolio, queueMissing: true);

        $stats = $this->portfolioRepository->calculatePortfolioStats($portfolio);
        $allocation = $this->portfolioRepository->getPortfolioAllocation($portfolio);
        $alerts = $this->getPortfolioAlerts($portfolio);
        $holdings = $this->buildHoldings($portfolio);

        $dayChange = array_sum(array_column($holdings, 'day_change_value'));
        $prevValue = $stats['current_value'] - $dayChange;

        $ranked = array_values(array_filter($holdings, fn ($h) => $h['invested'] > 0));
        usort($ranked, fn ($a, $b) => $b['pnl_percent'] <=> $a['pnl_percent']);

        return [
            'portfolio' => $portfolio,
            'stats' => $stats,
            'allocation' => $allocation,
            'alerts' => $alerts,
            'holdings' => $holdings,
            'today' => [
                'value' => $dayChange,
                'percent' => $prevValue > 0 ? ($dayChange / $prevValue) * 100 : 0,
                'is_positive' => $dayChange >= 0,
            ],
            'best' => $ranked[0] ?? null,
            'worst' => count($ranked) > 1 ? end($ranked) : null,
            'performance' => $this->performanceFor($portfolio),
            'events' => $this->companyProfiles?->upcomingEventsFor($portfolio->items->pluck('stock_symbol')->all()) ?? [],
            'suggestions' => $this->concentrationSuggestions($allocation),
            'price_info' => $refresh,
        ];
    }

    /**
     * One row per holding with everything the table needs already computed (VND).
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildHoldings(Portfolio $portfolio): array
    {
        $total = (float) $portfolio->current_value;

        return $portfolio->items->map(function (PortfolioItem $i) use ($total) {
            $value = $i->current_value;
            $invested = $i->total_invested;

            return [
                'id' => $i->id,
                'symbol' => $i->stock_symbol,
                'name' => $i->stock_name,
                'quantity' => $i->quantity,
                'buy_price' => (float) $i->buy_price,
                'current_price' => (float) $i->current_price,
                'previous_price' => $i->previous_price !== null ? (float) $i->previous_price : null,
                'price_date' => $i->price_date?->toDateString(),
                'value' => $value,
                'invested' => $invested,
                'pnl' => $i->profit_loss,
                'pnl_percent' => $i->profit_loss_percent,
                'day_change_value' => $i->day_change_value,
                'day_change_percent' => $i->day_change_percent,
                'weight' => $total > 0 ? ($value / $total) * 100 : 0,
                'target_price' => $i->target_price !== null ? (float) $i->target_price : null,
                'stop_loss_price' => $i->stop_loss_price !== null ? (float) $i->stop_loss_price : null,
                'at_target' => (bool) $i->is_at_target,
                'at_stop_loss' => (bool) $i->is_at_stop_loss,
                'notes' => $i->notes,
                'buy_date' => $i->buy_date?->toDateString(),
            ];
        })->sortByDesc('value')->values()->all();
    }

    /**
     * Value-over-time series for the performance chart, cached briefly per portfolio state.
     *
     * @return array<int, array{date: string, value: float, invested: float}>
     */
    private function performanceFor(Portfolio $portfolio): array
    {
        if ($portfolio->items->isEmpty()) {
            return [];
        }

        $signature = md5($portfolio->items->map(fn ($i) => $i->id . ':' . $i->quantity . ':' . $i->buy_price . ':' . $i->buy_date?->toDateString())->implode('|'));

        $series = Cache::remember("portfolio-performance:{$portfolio->id}:{$signature}:" . now()->format('YmdH'), 1800, function () use ($portfolio) {
            $holdings = $portfolio->items->map(fn ($i) => [
                'symbol' => $i->stock_symbol,
                'quantity' => $i->quantity,
                'buy_price' => (float) $i->buy_price,
                'buy_date' => $i->buy_date->toDateString(),
            ])->all();

            $closes = $this->stockRepository->getCloseHistory(
                array_values(array_unique(array_column($holdings, 'symbol'))),
                min(array_column($holdings, 'buy_date'))
            );

            return PortfolioPerformance::series($holdings, $closes);
        });

        // The reconstruction only knows trading days that have a synced close. Finish the line on today's real
        // totals, so holdings bought after the last synced session (or before their first close arrives)
        // still show up instead of the chart ending short of the numbers on the KPI cards.
        $lastDate = $series ? end($series)['date'] : null;
        $latestBuy = max($portfolio->items->map(fn ($i) => $i->buy_date->toDateString())->all());
        $endDate = max(array_filter([$lastDate, $latestBuy]));
        $current = ['date' => $endDate, 'value' => round((float) $portfolio->current_value, 2), 'invested' => round((float) $portfolio->total_invested, 2)];

        if ($lastDate === $endDate) {
            $series[count($series) - 1] = $current;
        } else {
            $series[] = $current;
        }

        return $series;
    }

    /**
     * @param  array<int, array{symbol: string, percent: float|int}> $allocation
     * @return array<int, array<string, mixed>>
     */
    private function concentrationSuggestions(array $allocation): array
    {
        $suggestions = [];

        foreach ($allocation as $holding) {
            if ($holding['percent'] > 30) {
                $suggestions[] = [
                    'type' => 'reduce',
                    'symbol' => $holding['symbol'],
                    'current_percent' => round($holding['percent'], 1),
                    'suggested_percent' => 25,
                    'reason' => 'Tỷ trọng quá cao, nên giảm để đa dạng hóa rủi ro',
                ];
            }
        }

        return $suggestions;
    }

    public function getPortfolioAlerts(Portfolio $portfolio): array
    {
        $atTarget = $this->portfolioRepository->getItemsAtTarget($portfolio);
        $atStopLoss = $this->portfolioRepository->getItemsAtStopLoss($portfolio);

        return [
            'targets_reached' => $atTarget->count(),
            'stop_losses_hit' => $atStopLoss->count(),
            'target_items' => $atTarget->toArray(),
            'stop_loss_items' => $atStopLoss->toArray(),
        ];
    }

    /**
     * Price Updates
     */
    public function updatePortfolioPrices(int $portfolioId, int $userId): bool
    {
        try {
            $result = $this->refreshPrices($portfolioId, $userId);

            return $result !== null && $result['ok'];
        } catch (\Exception $e) {
            Log::error('Failed to update portfolio prices', [
                'portfolio_id' => $portfolioId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Refresh a user's portfolio from the synced quotes and report what happened, so the UI can say
     * "updated 3 of 4, 1 is being fetched" instead of a silent success.
     *
     * @return array{ok: bool, updated: int, missing: string[], queued: string[], as_of: ?string}|null null = not found / not yours
     */
    public function refreshPrices(int $portfolioId, int $userId, bool $queueMissing = false): ?array
    {
        $portfolio = $this->portfolioRepository->findByIdAndUser($portfolioId, $userId);

        return $portfolio ? $this->refreshPortfolio($portfolio, $queueMissing) : null;
    }

    /** @return array{ok: bool, updated: int, missing: string[], queued: string[], as_of: ?string} */
    private function refreshPortfolio(Portfolio $portfolio, bool $queueMissing): array
    {
        $symbols = $portfolio->items->pluck('stock_symbol')->unique()->values()->all();
        $quotes = $this->fetchQuotes($symbols);

        $ok = $this->portfolioRepository->updateItemsPrices($portfolio, $quotes);

        if ($ok) {
            $this->checkPriceAlerts($portfolio);
        }

        $missing = array_values(array_diff($symbols, array_keys($quotes)));
        $dates = array_filter(array_column($quotes, 'date'));

        return [
            'ok' => $ok,
            'updated' => count($quotes),
            'missing' => $missing,
            'queued' => $queueMissing ? $this->requestPriceSync($missing) : [],
            'as_of' => $dates ? max($dates) : null,
        ];
    }

    /**
     * Symbols with no synced price at all get their history fetched in the background (deduplicated for
     * 10 minutes so repeated page views / clicks do not stack jobs).
     *
     * @param  string[] $symbols
     * @return string[] symbols actually queued
     */
    public function requestPriceSync(array $symbols): array
    {
        if ($symbols === []) {
            return [];
        }

        $needSync = array_filter(
            $this->stockRepository->ensureTracked($symbols),
            fn ($s) => Cache::add('portfolio-price-sync:' . $s, 1, 600)
        );

        if ($needSync !== []) {
            ProcessStockPriceSync::dispatch(array_map(fn ($s) => ['symbol' => $s], array_values($needSync)))->onQueue('high');
        }

        return array_values($needSync);
    }

    /**
     * Latest quote for one symbol (VND) — used to pre-fill the add-stock form.
     *
     * @return array{symbol: string, name: string, exchange: string, price: ?float, previous: ?float, change_percent: ?float, date: ?string}|null null = unknown symbol
     */
    public function getQuote(string $symbol): ?array
    {
        $symbol = strtoupper(trim($symbol));
        $overview = $this->stockRepository->getOverview($symbol);

        if (! $overview) {
            return null;
        }

        $quote = $this->fetchQuotes([$symbol])[$symbol] ?? null;

        return [
            'symbol' => $symbol,
            'name' => $overview['name'] ?? $symbol,
            'exchange' => $overview['exchange'] ?? '',
            'price' => $quote['price'] ?? null,
            'previous' => $quote['prev'] ?? null,
            'change_percent' => ($quote && $quote['prev']) ? (($quote['price'] / $quote['prev']) - 1) * 100 : null,
            'date' => $quote['date'] ?? null,
        ];
    }

    public function updateAllUserPortfolioPrices(int $userId): int
    {
        $portfolios = $this->portfolioRepository->getActiveByUser($userId);
        $updated = 0;

        foreach ($portfolios as $portfolio) {
            if ($this->updatePortfolioPrices($portfolio->id, $userId)) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Refresh prices + fire target/stop-loss alerts for every active portfolio
     * across all users. Intended to run from the scheduler after sync:stock-prices.
     */
    public function refreshAllPortfolioPrices(): int
    {
        $updated = 0;

        foreach ($this->portfolioRepository->getAllActivePortfolios() as $portfolio) {
            if ($this->updatePortfolioPrices($portfolio->id, $portfolio->user_id)) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Notify the owner once when an item crosses its target/stop-loss price,
     * and reset the alert flag once the price moves back past the threshold
     * so a future crossing notifies again.
     */
    private function checkPriceAlerts(Portfolio $portfolio): void
    {
        foreach ($portfolio->items as $item) {
            $this->checkSingleAlert($portfolio, $item, 'target', $item->is_at_target, $item->target_alerted_at);
            $this->checkSingleAlert($portfolio, $item, 'stop_loss', $item->is_at_stop_loss, $item->stop_loss_alerted_at);
        }
    }

    private function checkSingleAlert(Portfolio $portfolio, PortfolioItem $item, string $type, bool $isTriggered, $alertedAt): void
    {
        $column = $type === 'target' ? 'target_alerted_at' : 'stop_loss_alerted_at';

        if ($isTriggered && ! $alertedAt) {
            if ($portfolio->user) {
                $portfolio->user->notify((new PortfolioAlertNotification($item, $type))->onQueue('high'));
            }
            $this->portfolioRepository->setAlertFlag($item, $column, now());
        } elseif (! $isTriggered && $alertedAt) {
            $this->portfolioRepository->setAlertFlag($item, $column, null);
        }
    }

    /**
     * Portfolio Suggestions & AI Integration
     */
    public function getRebalanceSuggestions(int $portfolioId, int $userId): ?array
    {
        $portfolio = $this->portfolioRepository->findByIdAndUser($portfolioId, $userId);

        if (! $portfolio) {
            return null;
        }

        return $this->concentrationSuggestions($this->portfolioRepository->getPortfolioAllocation($portfolio));
    }

    /**
     * Private Helper Methods
     */
    private function updateExistingPosition(PortfolioItem $existingItem, array $newData): PortfolioItem
    {
        // Calculate new average price and quantity
        $oldQuantity = $existingItem->quantity;
        $oldPrice = $existingItem->buy_price;
        $newQuantity = $newData['quantity'];
        $newPrice = $newData['buy_price'];

        $totalQuantity = $oldQuantity + $newQuantity;
        $averagePrice = (($oldQuantity * $oldPrice) + ($newQuantity * $newPrice)) / $totalQuantity;

        $updateData = [
            'quantity' => $totalQuantity,
            'buy_price' => $averagePrice,
            'current_price' => $newData['current_price'] ?? $existingItem->current_price,
            'previous_price' => $newData['previous_price'] ?? $existingItem->previous_price,
            'price_date' => $newData['price_date'] ?? $existingItem->price_date,
        ];

        $this->portfolioRepository->updateItem($existingItem, $updateData);

        return $existingItem->refresh();
    }

    /**
     * Latest synced quotes converted from the feed's thousands-of-VND to whole VND.
     *
     * @param  string[] $symbols
     * @return array<string, array{price: float, prev: ?float, date: string}>
     */
    private function fetchQuotes(array $symbols): array
    {
        if (empty($symbols)) {
            return [];
        }

        $quotes = [];
        foreach ($this->stockRepository->getLatestQuotes($symbols) as $symbol => $q) {
            $quotes[$symbol] = [
                'price' => PriceUnit::toVnd($q['close']),
                'prev' => PriceUnit::toVnd($q['prev_close'] ?? null),
                'date' => $q['date'],
            ];
        }

        return $quotes;
    }
}

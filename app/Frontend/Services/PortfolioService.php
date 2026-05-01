<?php

namespace App\Frontend\Services;

use App\Frontend\Interfaces\PortfolioRepositoryInterface;
use App\Models\Portfolio;
use App\Models\PortfolioItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class PortfolioService
{
    public function __construct(
        private PortfolioRepositoryInterface $portfolioRepository
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

        // Check if stock already exists in portfolio
        $existingItem = $this->portfolioRepository->findItemByPortfolioAndSymbol(
            $portfolioId,
            $stockData['stock_symbol']
        );

        if ($existingItem) {
            // If exists, update quantity and average price
            return $this->updateExistingPosition($existingItem, $stockData);
        }

        // Create new item
        $itemData = [
            'portfolio_id' => $portfolioId,
            'stock_symbol' => $stockData['stock_symbol'],
            'stock_name' => $stockData['stock_name'],
            'quantity' => $stockData['quantity'],
            'buy_price' => $stockData['buy_price'],
            'current_price' => $stockData['current_price'] ?? $stockData['buy_price'],
            'buy_date' => $stockData['buy_date'],
            'target_price' => $stockData['target_price'] ?? null,
            'stop_loss_price' => $stockData['stop_loss_price'] ?? null,
            'notes' => $stockData['notes'] ?? null,
        ];

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
     */
    public function getPortfolioAnalytics(int $portfolioId, int $userId): ?array
    {
        $portfolio = $this->portfolioRepository->findByIdAndUser($portfolioId, $userId);

        if (! $portfolio) {
            return null;
        }

        $stats = $this->portfolioRepository->calculatePortfolioStats($portfolio);
        $allocation = $this->portfolioRepository->getPortfolioAllocation($portfolio);
        $alerts = $this->getPortfolioAlerts($portfolio);

        return [
            'portfolio' => $portfolio,
            'stats' => $stats,
            'allocation' => $allocation,
            'alerts' => $alerts,
        ];
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
        $portfolio = $this->portfolioRepository->findByIdAndUser($portfolioId, $userId);

        if (! $portfolio) {
            return false;
        }

        try {
            // Get current prices from external API or database
            $symbols = $portfolio->items->pluck('stock_symbol')->toArray();
            $priceData = $this->fetchCurrentPrices($symbols);

            return $this->portfolioRepository->updateItemsPrices($portfolio, $priceData);
        } catch (\Exception $e) {
            Log::error('Failed to update portfolio prices', [
                'portfolio_id' => $portfolioId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
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
     * Portfolio Suggestions & AI Integration
     */
    public function getRebalanceSuggestions(int $portfolioId, int $userId): ?array
    {
        $portfolio = $this->portfolioRepository->findByIdAndUser($portfolioId, $userId);

        if (! $portfolio) {
            return null;
        }

        $allocation = $this->portfolioRepository->getPortfolioAllocation($portfolio);

        // Simple rebalancing logic (can be enhanced with AI)
        $suggestions = [];

        foreach ($allocation as $holding) {
            if ($holding['percent'] > 30) {
                $suggestions[] = [
                    'type' => 'reduce',
                    'symbol' => $holding['symbol'],
                    'current_percent' => $holding['percent'],
                    'suggested_percent' => 25,
                    'reason' => 'Tỷ trọng quá cao, nên giảm để đa dạng hóa rủi ro',
                ];
            }
        }

        return $suggestions;
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
            'current_price' => $newData['current_price'] ?? $newPrice,
        ];

        $this->portfolioRepository->updateItem($existingItem, $updateData);

        return $existingItem->refresh();
    }

    private function fetchCurrentPrices(array $symbols): array
    {
        // Mock implementation - integrate with actual stock API
        $prices = [];

        foreach ($symbols as $symbol) {
            // Simulate price fetch from external API or database
            $prices[$symbol] = rand(10000, 50000) / 100; // Random price for demo
        }

        return $prices;
    }
}

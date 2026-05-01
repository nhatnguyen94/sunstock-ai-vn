<?php

namespace App\Frontend\Repositories;

use App\Frontend\Interfaces\PortfolioRepositoryInterface;
use App\Models\Portfolio;
use App\Models\PortfolioItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class PortfolioRepository implements PortfolioRepositoryInterface
{
    /**
     * Portfolio methods
     */
    public function getAllByUser(int $userId): Collection
    {
        return Portfolio::forUser($userId)
            ->with('items')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getActiveByUser(int $userId): Collection
    {
        return Portfolio::forUser($userId)
            ->active()
            ->with('items')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findById(int $id): ?Portfolio
    {
        return Portfolio::with('items.stock')->find($id);
    }

    public function findByIdAndUser(int $id, int $userId): ?Portfolio
    {
        return Portfolio::forUser($userId)
            ->with('items.stock')
            ->find($id);
    }

    public function create(array $data): Portfolio
    {
        return Portfolio::create($data);
    }

    public function update(Portfolio $portfolio, array $data): bool
    {
        return $portfolio->update($data);
    }

    public function delete(Portfolio $portfolio): bool
    {
        // Delete all items first
        $portfolio->items()->delete();

        return $portfolio->delete();
    }

    public function paginate(int $userId, int $perPage = 10): LengthAwarePaginator
    {
        return Portfolio::forUser($userId)
            ->with('items')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Portfolio Item methods
     */
    public function findItemById(int $id): ?PortfolioItem
    {
        return PortfolioItem::with(['portfolio', 'stock'])->find($id);
    }

    public function getItemsByPortfolio(int $portfolioId): Collection
    {
        return PortfolioItem::forPortfolio($portfolioId)
            ->with('stock')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function createItem(array $data): PortfolioItem
    {
        $item = PortfolioItem::create($data);

        // Update portfolio total invested and current value
        $portfolio = $item->portfolio;
        $portfolio->total_invested += $item->getTotalInvestedAttribute();
        $portfolio->current_value += $item->getCurrentValueAttribute();
        $portfolio->save();

        return $item;
    }

    public function updateItem(PortfolioItem $item, array $data): bool
    {
        $oldInvested = $item->getTotalInvestedAttribute();
        $oldValue = $item->getCurrentValueAttribute();

        $updated = $item->update($data);

        if ($updated) {
            // Recalculate portfolio values
            $portfolio = $item->portfolio;
            $portfolio->total_invested = $portfolio->total_invested - $oldInvested + $item->getTotalInvestedAttribute();
            $portfolio->current_value = $portfolio->current_value - $oldValue + $item->getCurrentValueAttribute();
            $portfolio->save();
        }

        return $updated;
    }

    public function deleteItem(PortfolioItem $item): bool
    {
        $portfolio = $item->portfolio;

        // Update portfolio values
        $portfolio->total_invested -= $item->getTotalInvestedAttribute();
        $portfolio->current_value -= $item->getCurrentValueAttribute();
        $portfolio->save();

        return $item->delete();
    }

    public function findItemByPortfolioAndSymbol(int $portfolioId, string $symbol): ?PortfolioItem
    {
        return PortfolioItem::forPortfolio($portfolioId)
            ->bySymbol($symbol)
            ->first();
    }

    /**
     * Statistics & Analytics
     */
    public function calculatePortfolioStats(Portfolio $portfolio): array
    {
        $totalInvested = $portfolio->total_invested;
        $currentValue = $portfolio->current_value;
        $profitLoss = $currentValue - $totalInvested;
        $profitLossPercent = $totalInvested > 0 ? ($profitLoss / $totalInvested) * 100 : 0;

        return [
            'total_invested' => $totalInvested,
            'current_value' => $currentValue,
            'profit_loss' => $profitLoss,
            'profit_loss_percent' => $profitLossPercent,
            'is_positive' => $profitLoss >= 0,
            'total_items' => $portfolio->items->count(),
        ];
    }

    public function getPortfolioAllocation(Portfolio $portfolio): array
    {
        return $portfolio->items->map(function ($item) use ($portfolio) {
            return [
                'symbol' => $item->stock_symbol,
                'name' => $item->stock_name,
                'current_value' => $item->getCurrentValueAttribute(),
                'percent' => $portfolio->current_value > 0
                    ? ($item->getCurrentValueAttribute() / $portfolio->current_value) * 100
                    : 0,
            ];
        })->sortByDesc('current_value')->values()->toArray();
    }

    public function getItemsAtTarget(Portfolio $portfolio): Collection
    {
        return $portfolio->items()->atTarget()->get();
    }

    public function getItemsAtStopLoss(Portfolio $portfolio): Collection
    {
        return $portfolio->items()->atStopLoss()->get();
    }

    public function updateItemsPrices(Portfolio $portfolio, array $priceData): bool
    {
        try {
            foreach ($portfolio->items as $item) {
                if (isset($priceData[$item->stock_symbol])) {
                    $item->updateCurrentPrice($priceData[$item->stock_symbol]);
                }
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}

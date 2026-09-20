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

    public function getAllActivePortfolios(): Collection
    {
        return Portfolio::active()->get(['id', 'user_id']);
    }

    public function setAlertFlag(PortfolioItem $item, string $column, ?\DateTimeInterface $value): void
    {
        $item->{$column} = $value;
        $item->saveQuietly();
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

        // Recompute from the actual items rather than incrementally
        // adjusting running totals — see Portfolio::recalculateTotals().
        // Fetch a FRESH Portfolio rather than $item->portfolio: if the
        // caller already touched that relation earlier, its cached `items`
        // collection would predate the item we just created.
        Portfolio::find($item->portfolio_id)?->recalculateTotals();

        return $item;
    }

    public function updateItem(PortfolioItem $item, array $data): bool
    {
        $updated = $item->update($data);

        if ($updated) {
            Portfolio::find($item->portfolio_id)?->recalculateTotals();
        }

        return $updated;
    }

    public function deleteItem(PortfolioItem $item): bool
    {
        $portfolioId = $item->portfolio_id;
        $deleted = $item->delete();

        if ($deleted) {
            Portfolio::find($portfolioId)?->recalculateTotals();
        }

        return $deleted;
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

    /**
     * @param array<string, array{price: float, prev?: ?float, date?: ?string}> $priceData quotes in VND, by symbol
     */
    public function updateItemsPrices(Portfolio $portfolio, array $priceData): bool
    {
        try {
            foreach ($portfolio->items as $item) {
                $quote = $priceData[$item->stock_symbol] ?? null;
                if ($quote !== null) {
                    $item->applyQuote((float) $quote['price'], $quote['prev'] ?? null, $quote['date'] ?? null);
                }
            }

            // Recompute from the DB once, then mirror the totals onto the caller's instance so it does
            // not have to reload (and lose its already-loaded, already-updated `items`).
            $fresh = Portfolio::find($portfolio->id);
            if ($fresh) {
                $fresh->recalculateTotals();
                $portfolio->total_invested = $fresh->total_invested;
                $portfolio->current_value = $fresh->current_value;
                $portfolio->syncOriginal();
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}

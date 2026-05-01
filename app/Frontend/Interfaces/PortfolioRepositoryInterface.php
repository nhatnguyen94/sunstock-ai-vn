<?php

namespace App\Frontend\Interfaces;

use App\Models\Portfolio;
use App\Models\PortfolioItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface PortfolioRepositoryInterface
{
    /**
     * Portfolio methods
     */
    public function getAllByUser(int $userId): Collection;

    public function getActiveByUser(int $userId): Collection;

    public function findById(int $id): ?Portfolio;

    public function findByIdAndUser(int $id, int $userId): ?Portfolio;

    public function create(array $data): Portfolio;

    public function update(Portfolio $portfolio, array $data): bool;

    public function delete(Portfolio $portfolio): bool;

    public function paginate(int $userId, int $perPage = 10): LengthAwarePaginator;

    /**
     * Portfolio Item methods
     */
    public function findItemById(int $id): ?PortfolioItem;

    public function getItemsByPortfolio(int $portfolioId): Collection;

    public function createItem(array $data): PortfolioItem;

    public function updateItem(PortfolioItem $item, array $data): bool;

    public function deleteItem(PortfolioItem $item): bool;

    public function findItemByPortfolioAndSymbol(int $portfolioId, string $symbol): ?PortfolioItem;

    /**
     * Statistics & Analytics
     */
    public function calculatePortfolioStats(Portfolio $portfolio): array;

    public function getPortfolioAllocation(Portfolio $portfolio): array;

    public function getItemsAtTarget(Portfolio $portfolio): Collection;

    public function getItemsAtStopLoss(Portfolio $portfolio): Collection;

    public function updateItemsPrices(Portfolio $portfolio, array $priceData): bool;
}

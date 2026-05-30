<?php

namespace App\Backend\Interfaces;

use App\Models\Stock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Contract for the admin stock management service.
 *
 * The service layer owns all business logic: data normalization,
 * orchestration, cache invalidation strategy, and job dispatching.
 * DB access is always delegated to StockRepositoryInterface.
 */
interface StockServiceInterface
{
    /**
     * Return a paginated list of stocks applying search/exchange/status filters.
     *
     * @param array{search?: string, exchange?: string, status?: string} $filters
     * @param int $perPage
     */
    public function listStocks(array $filters, int $perPage = 20): LengthAwarePaginator;

    /**
     * Return distinct exchange values for the filter dropdown.
     */
    public function getExchanges(): Collection;

    /**
     * Create a new stock record after normalizing input data.
     *
     * @param array{symbol: string, name: string, is_active: bool} $data
     */
    public function createStock(array $data): Stock;

    /**
     * Update an existing stock record after normalizing input data.
     *
     * @param array{symbol: string, name: string, is_active: bool} $data
     */
    public function updateStock(Stock $stock, array $data): void;

    /**
     * Permanently delete a stock record.
     */
    public function deleteStock(Stock $stock): void;

    /**
     * Trigger a background price-update job for all active stocks.
     *
     * Returns true if the job was dispatched, false if prerequisites failed.
     */
    public function triggerPriceUpdate(): bool;
}

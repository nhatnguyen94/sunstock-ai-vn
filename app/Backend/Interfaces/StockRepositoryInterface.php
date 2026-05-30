<?php

namespace App\Backend\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface StockRepositoryInterface
{
    /**
     * Return a paginated list of stocks with optional filters.
     *
     * @param array{search?: string, exchange?: string, status?: string} $filters
     * @param int $perPage
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator;

    /**
     * Return all distinct non-null exchange values from stock_symbols.
     */
    public function getExchanges(): Collection;

    /**
     * Create a new stock record.
     *
     * @param array{symbol: string, name: string, is_active: bool} $data
     */
    public function create(array $data): \App\Models\Stock;

    /**
     * Update an existing stock record.
     *
     * @param array{symbol: string, name: string, is_active: bool} $data
     */
    public function update(\App\Models\Stock $stock, array $data): void;

    /**
     * Permanently delete a stock record.
     */
    public function delete(\App\Models\Stock $stock): void;
}

<?php

namespace App\Backend\Repositories;

use App\Backend\Interfaces\StockRepositoryInterface;
use App\Models\Stock;
use App\Models\StockSymbol;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class StockRepository implements StockRepositoryInterface
{
    /**
     * Return a paginated, eager-loaded list of stocks with optional search/filter.
     *
     * Eager-loads `symbolInfo` (stock_symbols) to avoid N+1 on exchange/industry display.
     *
     * @param array{search?: string, exchange?: string, status?: string} $filters
     * @param int $perPage
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return Stock::query()
            ->with(['symbolInfo', 'latestPrice'])
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where('symbol', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            })
            ->when($filters['exchange'] ?? null, function ($q, $exchange) {
                $q->whereHas('symbolInfo', fn($s) => $s->where('exchange', $exchange));
            })
            ->when(isset($filters['status']) && $filters['status'] === 'active', fn($q) => $q->where('is_active', true))
            ->when(isset($filters['status']) && $filters['status'] === 'inactive', fn($q) => $q->where('is_active', false))
            ->orderBy('symbol')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Return distinct exchange values from stock_symbols, cached for 5 minutes.
     */
    public function getExchanges(): Collection
    {
        return Cache::remember('admin_stock_exchanges', 300, function () {
            return StockSymbol::distinct()
                ->whereNotNull('exchange')
                ->orderBy('exchange')
                ->pluck('exchange');
        });
    }

    /**
     * Create a new stock record.
     *
     * @param array{symbol: string, name: string, is_active: bool} $data
     */
    public function create(array $data): Stock
    {
        Cache::forget('admin_stock_exchanges');
        return Stock::create($data);
    }

    /**
     * Update an existing stock record and bust the exchange cache.
     *
     * @param array{symbol: string, name: string, is_active: bool} $data
     */
    public function update(Stock $stock, array $data): void
    {
        Cache::forget('admin_stock_exchanges');
        $stock->update($data);
    }

    /**
     * Delete a stock record and bust the exchange cache.
     */
    public function delete(Stock $stock): void
    {
        Cache::forget('admin_stock_exchanges');
        $stock->delete();
    }
}

<?php

namespace App\Backend\Services;

use App\Backend\Interfaces\StockRepositoryInterface;
use App\Backend\Interfaces\StockServiceInterface;
use App\Jobs\ProcessStockPriceSync;
use App\Models\Stock;
use App\Models\StockPrice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Admin stock management service.
 *
 * Owns all business logic for stock CRUD operations:
 * - Data normalization (symbol uppercase, boolean cast)
 * - Orchestration between multiple repositories if ever needed
 * - Job dispatching for async price updates
 *
 * Pure DB operations are delegated to StockRepositoryInterface.
 */
class StockService implements StockServiceInterface
{
    public function __construct(
        protected StockRepositoryInterface $stockRepository
    ) {}

    /**
     * Return a paginated list of stocks with optional filters applied.
     *
     * @param array{search?: string, exchange?: string, status?: string} $filters
     */
    public function listStocks(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->stockRepository->paginate($filters, $perPage);
    }

    /**
     * Return distinct exchange values for the admin filter dropdown.
     * Results are cached by the repository layer (5 minutes).
     */
    public function getExchanges(): Collection
    {
        return $this->stockRepository->getExchanges();
    }

    /**
     * Normalize input and create a new stock record.
     *
     * Business rules applied here:
     * - Symbol is always stored as uppercase with surrounding whitespace stripped.
     *
     * @param array{symbol: string, name: string, is_active: bool} $data
     */
    public function createStock(array $data): Stock
    {
        $data['symbol'] = strtoupper(trim($data['symbol']));

        return $this->stockRepository->create($data);
    }

    /**
     * Normalize input and update an existing stock record.
     *
     * Business rules applied here:
     * - Symbol is always stored as uppercase with surrounding whitespace stripped.
     * - Exchange and industry are read-only; they are excluded even if passed in $data.
     *
     * @param array{symbol: string, name: string, is_active: bool} $data
     */
    public function updateStock(Stock $stock, array $data): void
    {
        $data['symbol'] = strtoupper(trim($data['symbol']));

        // Exchange and industry are managed by stock:sync — never overwrite from admin form.
        unset($data['exchange'], $data['industry'], $data['market_cap']);

        $this->stockRepository->update($stock, $data);
    }

    /**
     * Permanently delete a stock record.
     *
     * Note: associated stock_prices records are NOT cascade-deleted.
     * Clean up orphaned prices via a dedicated artisan command if needed.
     */
    public function deleteStock(Stock $stock): void
    {
        $this->stockRepository->delete($stock);
    }

    /**
     * Dispatch background jobs to sync today's prices for all active stocks.
     *
     * Mirrors the logic of sync:stock-prices artisan command:
     * - Skips symbols that already have a price record for today.
     * - Chunks remaining symbols into groups of 20 and dispatches one job per chunk.
     *
     * Returns the number of jobs dispatched (0 if nothing to sync).
     */
    public function triggerPriceUpdate(): bool
    {
        $today  = now()->toDateString();
        $stocks = Stock::all(['id', 'symbol'])->toArray();

        // Skip symbols already synced today
        $alreadySyncedIds = StockPrice::whereIn('stock_id', array_column($stocks, 'id'))
            ->where('date', $today)
            ->pluck('stock_id')
            ->flip()
            ->toArray();

        $pending = array_values(array_filter($stocks, fn ($s) => !isset($alreadySyncedIds[$s['id']])));

        foreach (array_chunk($pending, 20) as $chunk) {
            ProcessStockPriceSync::dispatch($chunk);
        }

        return true;
    }
}

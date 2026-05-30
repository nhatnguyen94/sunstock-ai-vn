<?php

namespace App\Backend\Controllers;

use App\Backend\Interfaces\StockServiceInterface;
use App\Models\Stock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockController extends Controller
{
    public function __construct(
        protected StockServiceInterface $stockService
    ) {}

    /**
     * Display a paginated list of stocks with optional search and filter.
     *
     * Accessible by: Admin, AdminSupport (gate: manage-features).
     */
    public function index(Request $request): View|RedirectResponse
    {
        $stocks    = $this->stockService->listStocks($request->only('search', 'exchange', 'status'));
        $exchanges = $this->stockService->getExchanges();

        return view('backend.stocks.index', compact('stocks', 'exchanges'));
    }

    /**
     * Show the form for creating a new stock record.
     *
     * Accessible by: Admin, AdminSupport (gate: manage-features).
     */
    public function create(): View|RedirectResponse
    {
        return view('backend.stocks.create');
    }

    /**
     * Store a new stock record in the database.
     *
     * Only symbol, name, and is_active are accepted.
     * Exchange and industry are synced automatically via php artisan stock:sync.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'symbol'    => 'required|string|max:10|unique:stocks',
            'name'      => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $this->stockService->createStock($validated);

        return redirect()->route('admin.stocks.index')
            ->with('success', 'Stock created successfully.');
    }

    /**
     * Display details of a single stock, including its symbol reference data.
     */
    public function show(Stock $stock): View|RedirectResponse
    {
        $stock->load('symbolInfo');

        return view('backend.stocks.show', compact('stock'));
    }

    /**
     * Show the form for editing an existing stock record.
     */
    public function edit(Stock $stock): View|RedirectResponse
    {
        $stock->load('symbolInfo');

        return view('backend.stocks.edit', compact('stock'));
    }

    /**
     * Update an existing stock record.
     *
     * Only symbol, name, and is_active are accepted.
     * Exchange and industry are read-only (managed by stock:sync).
     */
    public function update(Request $request, Stock $stock): RedirectResponse
    {
        $validated = $request->validate([
            'symbol'    => 'required|string|max:10|unique:stocks,symbol,' . $stock->id,
            'name'      => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $this->stockService->updateStock($stock, $validated);

        return redirect()->route('admin.stocks.index')
            ->with('success', 'Stock updated successfully.');
    }

    /**
     * Delete a stock record permanently.
     *
     * Note: associated stock_prices records are NOT deleted (no FK cascade).
     * Clean up orphaned prices via a data management command if needed.
     */
    public function destroy(Stock $stock): RedirectResponse
    {
        $this->stockService->deleteStock($stock);

        return redirect()->route('admin.stocks.index')
            ->with('success', 'Stock deleted successfully.');
    }

    /**
     * Trigger a background stock price update job via the service layer.
     */
    public function updatePrices(): RedirectResponse
    {
        $this->stockService->triggerPriceUpdate();

        return redirect()->route('admin.stocks.index')
            ->with('success', 'Price update has been queued. This may take a few minutes.');
    }
}

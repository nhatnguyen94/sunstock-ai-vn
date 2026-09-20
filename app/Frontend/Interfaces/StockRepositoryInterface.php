<?php

/**
 * Author: Sun Nguyen
 * Email: nhat.nguyenminh94@gmail.com
 * Github: https://github.com/nhatnguyen94
 */

namespace App\Frontend\Interfaces;

interface StockRepositoryInterface
{
    public function getFeaturedStocks(array $symbols): array;

    public function getStockPrice(string $symbol): ?array;

    public function updateStockPriceFromPython(string $symbol): void;

    public function getOverview(string $symbol): ?array;

    public function getOrUpdateSymbols(): void;

    public function searchSymbols(string $query): array;

    /**
     * Latest close price for each symbol, as [symbol => close]. Symbols with no price yet are omitted.
     */
    public function getLatestPrices(array $symbols): array;

    /**
     * Latest session + the one before it, per symbol, in FEED units (thousands of VND — see App\Support\PriceUnit).
     * Symbols with no synced price are simply absent.
     *
     * @param  string[] $symbols
     * @return array<string, array{close: float, prev_close: ?float, date: string}>
     */
    public function getLatestQuotes(array $symbols): array;

    /**
     * Daily closes (feed units) since $from, per symbol: [symbol => [date => close]], ascending by date.
     *
     * @param  string[] $symbols
     * @return array<string, array<string, float>>
     */
    public function getCloseHistory(array $symbols, string $from): array;

    /**
     * Make sure every symbol is a tracked Stock row and return those that still have NO price rows
     * (they need a sync before they can be valued).
     *
     * @param  string[] $symbols
     * @return string[]
     */
    public function ensureTracked(array $symbols): array;
}

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
}

<?php
/**
 * Author: Sun Nguyen
 * Email: nhat.nguyenminh94@gmail.com
 * Github: https://github.com/nhatnguyen94
 */
namespace App\Repositories;

interface StockRepositoryInterface
{
    public function getFeaturedStocks(array $symbols): array;
    public function getStockPrice(string $symbol): ?array;
    public function updateStockPriceFromPython(string $symbol): void;
    public function getOverview(string $symbol): ?array;
    public function getOrUpdateSymbols(): void;
    public function searchSymbols(string $query): array;
}
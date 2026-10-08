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
     * Name, exchange and ICB industry for each symbol, as [symbol => ['name' => ?string, 'exchange' => ?string, 'industry' => ?string]].
     * Unknown symbols are absent.
     *
     * @param  string[]  $symbols
     * @return array<string, array{name: ?string, exchange: ?string, industry: ?string}>
     */
    public function symbolInfo(array $symbols): array;

    /**
     * Per symbol, over the stored daily bars with `$from <= date < $before`: how many sessions, the highest high, the lowest low and the last
     * date (feed units). The input of the "tín hiệu" breakout/breakdown rules.
     *
     * @return array<string, array{n: int, high: float, low: float, last_date: string}>
     */
    public function priceExtremes(string $from, string $before): array;

    /**
     * The stored [date, close, volume] bars with `$from <= date < $before` per symbol, oldest first (feed units): enough for an average
     * volume and an RSI without loading a whole history.
     *
     * @return array<string, array<int, array{0: string, 1: float, 2: float}>>
     */
    public function recentBars(string $from, string $before): array;

    /**
     * Latest close price for each symbol, as [symbol => close]. Symbols with no price yet are omitted.
     */
    public function getLatestPrices(array $symbols): array;

    /**
     * Latest session + the one before it, per symbol, in FEED units (thousands of VND — see App\Support\PriceUnit).
     * Symbols with no synced price are simply absent.
     *
     * @param  string[]  $symbols
     * @return array<string, array{close: float, prev_close: ?float, date: string}>
     */
    public function getLatestQuotes(array $symbols): array;

    /**
     * Daily closes (feed units) since $from, per symbol: [symbol => [date => close]], ascending by date.
     *
     * @param  string[]  $symbols
     * @return array<string, array<string, float>>
     */
    public function getCloseHistory(array $symbols, string $from): array;

    /**
     * Make sure every symbol is a tracked Stock row and return those that still have NO price rows
     * (they need a sync before they can be valued).
     *
     * @param  string[]  $symbols
     * @return string[]
     */
    public function ensureTracked(array $symbols): array;
}

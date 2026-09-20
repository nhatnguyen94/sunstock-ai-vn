<?php

namespace App\Frontend\Interfaces;

use App\Models\PortfolioTransaction;
use Illuminate\Support\Collection;

interface PortfolioTransactionRepositoryInterface
{
    public function create(array $data): PortfolioTransaction;

    public function find(int $id): ?PortfolioTransaction;

    public function delete(PortfolioTransaction $transaction): bool;

    /** Newest first (traded_at, then id). @return Collection<int, PortfolioTransaction> */
    public function forPortfolio(int $portfolioId, int $limit = 200): Collection;

    /** The most recent transaction (highest id) of one symbol in a portfolio — the only one that can be undone. */
    public function latestForSymbol(int $portfolioId, string $symbol): ?PortfolioTransaction;

    /**
     * Realised results of the closed (sold) part of the portfolio.
     *
     * @return array{
     *   realized: float, fees: float, sells: int, wins: int, losses: int, best: ?float, worst: ?float,
     *   by_symbol: array<int, array{symbol: string, realized: float, sells: int, wins: int}>
     * }
     */
    public function realizedSummary(int $portfolioId): array;
}

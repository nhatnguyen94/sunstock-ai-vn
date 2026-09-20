<?php

namespace App\Frontend\Repositories;

use App\Frontend\Interfaces\PortfolioTransactionRepositoryInterface;
use App\Models\PortfolioTransaction;
use Illuminate\Support\Collection;

class PortfolioTransactionRepository implements PortfolioTransactionRepositoryInterface
{
    public function create(array $data): PortfolioTransaction
    {
        return PortfolioTransaction::create($data);
    }

    public function find(int $id): ?PortfolioTransaction
    {
        return PortfolioTransaction::find($id);
    }

    public function delete(PortfolioTransaction $transaction): bool
    {
        return (bool) $transaction->delete();
    }

    public function forPortfolio(int $portfolioId, int $limit = 200): Collection
    {
        return PortfolioTransaction::where('portfolio_id', $portfolioId)
            ->orderByDesc('traded_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function latestForSymbol(int $portfolioId, string $symbol): ?PortfolioTransaction
    {
        return PortfolioTransaction::where(['portfolio_id' => $portfolioId, 'stock_symbol' => $symbol])
            ->orderByDesc('id')
            ->first();
    }

    public function realizedSummary(int $portfolioId): array
    {
        $sells = PortfolioTransaction::where('portfolio_id', $portfolioId)
            ->where('type', PortfolioTransaction::TYPE_SELL)
            ->get(['stock_symbol', 'realized_pl', 'fee']);

        $bySymbol = [];
        foreach ($sells->groupBy('stock_symbol') as $symbol => $rows) {
            $bySymbol[] = [
                'symbol' => (string) $symbol,
                'realized' => (float) $rows->sum('realized_pl'),
                'sells' => $rows->count(),
                'wins' => $rows->where('realized_pl', '>', 0)->count(),
            ];
        }
        usort($bySymbol, fn ($a, $b) => $b['realized'] <=> $a['realized']);

        $allFees = (float) PortfolioTransaction::where('portfolio_id', $portfolioId)->sum('fee');

        return [
            'realized' => (float) $sells->sum('realized_pl'),
            'fees' => $allFees,
            'sells' => $sells->count(),
            'wins' => $sells->where('realized_pl', '>', 0)->count(),
            'losses' => $sells->where('realized_pl', '<', 0)->count(),
            'best' => $sells->isEmpty() ? null : (float) $sells->max('realized_pl'),
            'worst' => $sells->isEmpty() ? null : (float) $sells->min('realized_pl'),
            'by_symbol' => $bySymbol,
        ];
    }
}

<?php

namespace App\Frontend\Services;

use App\Frontend\Interfaces\PortfolioRepositoryInterface;
use App\Frontend\Interfaces\PortfolioTransactionRepositoryInterface;
use App\Models\PortfolioItem;
use App\Models\PortfolioTransaction;
use App\Models\StockSymbol;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The buy/sell ledger of a portfolio.
 *
 * Holdings (`portfolio_items`) stay the "open position" — quantity and average cost per share — and every trade
 * moves them incrementally:
 *   buy  → weighted-average cost, INCLUDING the buy fee (a share bought for 85,000 with 127 ₫ of fee costs 85,127);
 *   sell → quantity goes down, the average cost of what is left does not change, and the realised P&L is frozen
 *          on the transaction: (price − average cost) × qty − fee, where the fee of a sell already contains the tax.
 * Because a sell stores its own cost basis, later buys never rewrite past results. Only the newest transaction of a
 * symbol can be undone, which keeps the weighted average exactly reversible.
 */
class PortfolioLedgerService
{
    public function __construct(
        private readonly PortfolioRepositoryInterface $portfolios,
        private readonly PortfolioTransactionRepositoryInterface $transactions
    ) {}

    /**
     * Record that a holding was added through the "add stock" form (the item itself is already written).
     * Keeps history and holdings in agreement without touching the position again.
     */
    public function logBuy(int $portfolioId, string $symbol, ?string $name, int $quantity, float $price, ?string $date, ?string $notes = null): PortfolioTransaction
    {
        return $this->transactions->create([
            'portfolio_id' => $portfolioId, 'stock_symbol' => $symbol, 'stock_name' => $name,
            'type' => PortfolioTransaction::TYPE_BUY, 'quantity' => $quantity, 'price' => $price, 'fee' => 0,
            'traded_at' => $date ?: now()->toDateString(), 'notes' => $notes,
        ]);
    }

    /**
     * Apply a buy or sell to the holding and record it.
     *
     * @param  array{type: string, stock_symbol: string, quantity: int, price: float|int, fee?: float|int|null, traded_at?: string|null, notes?: string|null} $data
     * @return array{ok: true, transaction: PortfolioTransaction, message: string}|array{ok: false, message: string, status: int}
     */
    public function trade(int $portfolioId, int $userId, array $data): array
    {
        $portfolio = $this->portfolios->findByIdAndUser($portfolioId, $userId);
        if (! $portfolio) {
            return $this->fail('Danh mục không tồn tại hoặc bạn không có quyền truy cập.', 404);
        }

        $type = $data['type'] ?? '';
        $symbol = strtoupper(trim((string) ($data['stock_symbol'] ?? '')));
        $qty = (int) ($data['quantity'] ?? 0);
        $price = (float) ($data['price'] ?? 0);
        $fee = max(0.0, (float) ($data['fee'] ?? 0));
        $date = $data['traded_at'] ?? now()->toDateString();

        if (! in_array($type, [PortfolioTransaction::TYPE_BUY, PortfolioTransaction::TYPE_SELL], true) || $symbol === '' || $qty < 1 || $price <= 0) {
            return $this->fail('Thông tin giao dịch không hợp lệ.', 422);
        }

        return DB::transaction(function () use ($portfolio, $type, $symbol, $qty, $price, $fee, $date, $data) {
            $item = PortfolioItem::where(['portfolio_id' => $portfolio->id, 'stock_symbol' => $symbol])->lockForUpdate()->first();

            if ($type === PortfolioTransaction::TYPE_BUY) {
                return $this->applyBuy($portfolio->id, $item, $symbol, $qty, $price, $fee, $date, $data['notes'] ?? null);
            }

            return $this->applySell($portfolio->id, $item, $symbol, $qty, $price, $fee, $date, $data['notes'] ?? null);
        });
    }

    /**
     * Undo the newest transaction of its symbol (the only one whose effect is exactly reversible).
     *
     * @return array{ok: true, message: string, portfolio_id: int}|array{ok: false, message: string, status: int}
     */
    public function undo(int $transactionId, int $userId): array
    {
        $tx = $this->transactions->find($transactionId);
        $portfolio = $tx ? $this->portfolios->findByIdAndUser($tx->portfolio_id, $userId) : null;
        if (! $tx || ! $portfolio) {
            return $this->fail('Không tìm thấy giao dịch hoặc bạn không có quyền xóa.', 404);
        }

        $latest = $this->transactions->latestForSymbol($tx->portfolio_id, $tx->stock_symbol);
        if (! $latest || $latest->id !== $tx->id) {
            return $this->fail("Chỉ hoàn tác được giao dịch gần nhất của {$tx->stock_symbol}. Hãy hoàn tác các giao dịch mới hơn trước.", 422);
        }

        return DB::transaction(function () use ($tx, $portfolio) {
            $item = PortfolioItem::where(['portfolio_id' => $tx->portfolio_id, 'stock_symbol' => $tx->stock_symbol])->lockForUpdate()->first();

            if ($tx->isSell()) {
                if ($item) {
                    $this->portfolios->updateItem($item, ['quantity' => $item->quantity + $tx->quantity]);
                } else {
                    $this->portfolios->createItem([
                        'portfolio_id' => $tx->portfolio_id, 'stock_symbol' => $tx->stock_symbol,
                        'stock_name' => $tx->stock_name ?: $tx->stock_symbol, 'quantity' => $tx->quantity,
                        'buy_price' => $tx->cost_basis ?? $tx->price, 'current_price' => $tx->price,
                        'buy_date' => $this->firstBuyDate($tx->portfolio_id, $tx->stock_symbol, $tx->id) ?? $tx->traded_at->toDateString(),
                    ]);
                }
            } else {
                if (! $item || $item->quantity < $tx->quantity) {
                    return $this->fail('Không thể hoàn tác: số lượng đang giữ ít hơn lượng mua của giao dịch này (đã chỉnh sửa thủ công?).', 422);
                }

                $remaining = $item->quantity - $tx->quantity;
                if ($remaining === 0) {
                    $this->portfolios->deleteItem($item);
                } else {
                    $cost = $tx->quantity * $tx->price + $tx->fee;
                    $avg = ($item->quantity * (float) $item->buy_price - $cost) / $remaining;
                    $this->portfolios->updateItem($item, ['quantity' => $remaining, 'buy_price' => round(max($avg, 0), 2)]);
                }
            }

            $this->transactions->delete($tx);

            return ['ok' => true, 'message' => "Đã hoàn tác giao dịch {$tx->stock_symbol}.", 'portfolio_id' => $tx->portfolio_id];
        });
    }

    /** @return Collection<int, PortfolioTransaction> */
    public function history(int $portfolioId, int $limit = 200): Collection
    {
        return $this->transactions->forPortfolio($portfolioId, $limit);
    }

    /**
     * Realised results + which transaction of each symbol may still be undone.
     *
     * @return array{summary: array<string, mixed>, transactions: Collection<int, PortfolioTransaction>, undoable: array<int, true>}
     */
    public function overview(int $portfolioId): array
    {
        $transactions = $this->history($portfolioId);

        // The first row seen per symbol is its newest (list is ordered newest first by date, then id) — but "newest"
        // for undo means highest id, so compute that explicitly.
        $newestId = [];
        foreach ($transactions as $t) {
            $newestId[$t->stock_symbol] = max($newestId[$t->stock_symbol] ?? 0, $t->id);
        }

        return [
            'summary' => $this->summary($portfolioId),
            'transactions' => $transactions,
            'undoable' => array_fill_keys(array_values($newestId), true),
        ];
    }

    /** @return array<string, mixed> */
    public function summary(int $portfolioId): array
    {
        $s = $this->transactions->realizedSummary($portfolioId);
        $s['win_rate'] = $s['sells'] > 0 ? round($s['wins'] / $s['sells'] * 100, 1) : null;

        return $s;
    }

    // ── internals ───────────────────────────────────────────────────────────

    private function applyBuy(int $portfolioId, ?PortfolioItem $item, string $symbol, int $qty, float $price, float $fee, string $date, ?string $notes): array
    {
        $cost = $qty * $price + $fee;
        $name = $item?->stock_name ?: (StockSymbol::where('symbol', $symbol)->value('name') ?: $symbol);

        if ($item) {
            $newQty = $item->quantity + $qty;
            $avg = ($item->quantity * (float) $item->buy_price + $cost) / $newQty;
            $earliest = $item->buy_date && $item->buy_date->toDateString() < $date ? $item->buy_date->toDateString() : $date;
            $this->portfolios->updateItem($item, ['quantity' => $newQty, 'buy_price' => round($avg, 2), 'buy_date' => $earliest]);
        } else {
            $this->portfolios->createItem([
                'portfolio_id' => $portfolioId, 'stock_symbol' => $symbol, 'stock_name' => $name,
                'quantity' => $qty, 'buy_price' => round($cost / $qty, 2), 'current_price' => $price, 'buy_date' => $date,
            ]);
        }

        $tx = $this->transactions->create([
            'portfolio_id' => $portfolioId, 'stock_symbol' => $symbol, 'stock_name' => $name,
            'type' => PortfolioTransaction::TYPE_BUY, 'quantity' => $qty, 'price' => $price, 'fee' => $fee,
            'traded_at' => $date, 'notes' => $notes,
        ]);

        return ['ok' => true, 'transaction' => $tx, 'message' => "Đã ghi nhận mua {$qty} {$symbol}."];
    }

    private function applySell(int $portfolioId, ?PortfolioItem $item, string $symbol, int $qty, float $price, float $fee, string $date, ?string $notes): array
    {
        if (! $item) {
            return $this->fail("Danh mục chưa có {$symbol} để bán.", 422);
        }
        if ($qty > $item->quantity) {
            return $this->fail("Bạn chỉ đang giữ " . number_format($item->quantity, 0, ',', '.') . " cổ phiếu {$symbol}, không thể bán {$qty}.", 422);
        }

        $costBasis = (float) $item->buy_price;
        $realized = round($qty * $price - $fee - $qty * $costBasis, 2);

        $tx = $this->transactions->create([
            'portfolio_id' => $portfolioId, 'stock_symbol' => $symbol, 'stock_name' => $item->stock_name,
            'type' => PortfolioTransaction::TYPE_SELL, 'quantity' => $qty, 'price' => $price, 'fee' => $fee,
            'traded_at' => $date, 'cost_basis' => $costBasis, 'realized_pl' => $realized, 'notes' => $notes,
        ]);

        if ($qty === $item->quantity) {
            $this->portfolios->deleteItem($item);      // fully sold: the position (and its alerts) is closed
        } else {
            $this->portfolios->updateItem($item, ['quantity' => $item->quantity - $qty]);
        }

        $sign = $realized >= 0 ? 'lãi' : 'lỗ';

        return [
            'ok' => true, 'transaction' => $tx,
            'message' => "Đã ghi nhận bán {$qty} {$symbol} — {$sign} " . number_format(abs($realized), 0, ',', '.') . ' ₫.',
        ];
    }

    private function firstBuyDate(int $portfolioId, string $symbol, int $exceptId): ?string
    {
        $first = PortfolioTransaction::where(['portfolio_id' => $portfolioId, 'stock_symbol' => $symbol, 'type' => PortfolioTransaction::TYPE_BUY])
            ->where('id', '!=', $exceptId)->orderBy('traded_at')->first(['traded_at']);

        return $first?->traded_at->toDateString();
    }

    private function fail(string $message, int $status): array
    {
        return ['ok' => false, 'message' => $message, 'status' => $status];
    }
}

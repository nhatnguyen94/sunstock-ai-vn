<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One buy or sell in a portfolio's ledger. Prices are whole VND per share. A sell freezes the average cost
 * (`cost_basis`) and the realised P&L at the moment it happened, so later buys never rewrite history.
 */
class PortfolioTransaction extends Model
{
    public const TYPE_BUY = 'buy';
    public const TYPE_SELL = 'sell';

    protected $fillable = [
        'portfolio_id', 'stock_symbol', 'stock_name', 'type', 'quantity', 'price', 'fee',
        'traded_at', 'cost_basis', 'realized_pl', 'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'float',
        'fee' => 'float',
        'cost_basis' => 'float',
        'realized_pl' => 'float',
        'traded_at' => 'date',
    ];

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    public function isSell(): bool
    {
        return $this->type === self::TYPE_SELL;
    }

    /** Gross amount of the trade (before fee). */
    public function getGrossAttribute(): float
    {
        return $this->quantity * $this->price;
    }
}

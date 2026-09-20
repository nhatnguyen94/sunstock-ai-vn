<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'portfolio_id',
        'stock_symbol',
        'stock_name',
        'quantity',
        'buy_price',
        'current_price',
        'previous_price',
        'price_date',
        'buy_date',
        'target_price',
        'stop_loss_price',
        'target_alerted_at',
        'stop_loss_alerted_at',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'buy_price' => 'decimal:2',
        'current_price' => 'decimal:2',
        'previous_price' => 'decimal:2',
        'price_date' => 'date',
        'target_price' => 'decimal:2',
        'stop_loss_price' => 'decimal:2',
        'target_alerted_at' => 'datetime',
        'stop_loss_alerted_at' => 'datetime',
        'buy_date' => 'date',
    ];

    // Relationships
    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class, 'stock_symbol', 'symbol');
    }

    // Accessors & Methods
    public function getTotalInvestedAttribute(): float
    {
        return $this->quantity * $this->buy_price;
    }

    public function getCurrentValueAttribute(): float
    {
        return $this->quantity * $this->current_price;
    }

    public function getProfitLossAttribute(): float
    {
        return $this->getCurrentValueAttribute() - $this->getTotalInvestedAttribute();
    }

    public function getProfitLossPercentAttribute(): float
    {
        if ($this->getTotalInvestedAttribute() == 0) {
            return 0;
        }

        return (($this->getCurrentValueAttribute() - $this->getTotalInvestedAttribute()) / $this->getTotalInvestedAttribute()) * 100;
    }

    public function getIsPositiveAttribute(): bool
    {
        return $this->getProfitLossAttribute() >= 0;
    }

    public function getIsAtTargetAttribute(): bool
    {
        return $this->target_price && $this->current_price >= $this->target_price;
    }

    public function getIsAtStopLossAttribute(): bool
    {
        return $this->stop_loss_price && $this->current_price <= $this->stop_loss_price;
    }

    public function getPercentOfPortfolioAttribute(): float
    {
        if (! $this->portfolio || $this->portfolio->current_value == 0) {
            return 0;
        }

        return ($this->getCurrentValueAttribute() / $this->portfolio->current_value) * 100;
    }

    // Scopes
    public function scopeForPortfolio($query, int $portfolioId)
    {
        return $query->where('portfolio_id', $portfolioId);
    }

    public function scopeBySymbol($query, string $symbol)
    {
        return $query->where('stock_symbol', $symbol);
    }

    public function scopeAtTarget($query)
    {
        return $query->whereNotNull('target_price')
            ->whereRaw('current_price >= target_price');
    }

    public function scopeAtStopLoss($query)
    {
        return $query->whereNotNull('stop_loss_price')
            ->whereRaw('current_price <= stop_loss_price');
    }

    /** Today's move for this holding in VND (0 when there is no previous close to compare with). */
    public function getDayChangeValueAttribute(): float
    {
        if ($this->previous_price === null) {
            return 0.0;
        }

        return $this->quantity * ($this->current_price - $this->previous_price);
    }

    public function getDayChangePercentAttribute(): ?float
    {
        if ($this->previous_price === null || (float) $this->previous_price <= 0) {
            return null;
        }

        return (($this->current_price / $this->previous_price) - 1) * 100;
    }

    // Methods

    /**
     * Apply a market quote (VND) to this holding. Persists only the holding — the caller recalculates the
     * portfolio totals ONCE after all holdings are updated (recomputing per holding used a stale
     * `items` collection and left the portfolio total wrong).
     */
    public function applyQuote(float $price, ?float $previous = null, ?string $date = null): void
    {
        $this->current_price = $price;
        $this->previous_price = $previous;
        $this->price_date = $date;
        $this->save();
    }
}

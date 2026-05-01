<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Portfolio extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'total_invested',
        'current_value',
        'is_active',
    ];

    protected $casts = [
        'total_invested' => 'decimal:2',
        'current_value' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PortfolioItem::class);
    }

    // Accessors & Methods
    public function getTotalProfitLossAttribute(): float
    {
        return $this->current_value - $this->total_invested;
    }

    public function getTotalProfitLossPercentAttribute(): float
    {
        if ($this->total_invested == 0) {
            return 0;
        }

        return (($this->current_value - $this->total_invested) / $this->total_invested) * 100;
    }

    public function getIsPositiveAttribute(): bool
    {
        return $this->getTotalProfitLossAttribute() >= 0;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Methods
    public function calculateCurrentValue(): float
    {
        return $this->items->sum(function ($item) {
            return $item->quantity * $item->current_price;
        });
    }

    public function updateCurrentValue(): void
    {
        $this->current_value = $this->calculateCurrentValue();
        $this->save();
    }
}

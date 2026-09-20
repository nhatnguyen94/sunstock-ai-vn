<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoldPrice extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'source', 'metal', 'product', 'branch', 'purity', 'unit', 'buy_price', 'sell_price',
        'world_price', 'quoted_at', 'synced_at',
    ];

    protected $casts = [
        'buy_price'   => 'integer',
        'sell_price'  => 'integer',
        'world_price' => 'float',
        'quoted_at'   => 'datetime',
        'synced_at'   => 'datetime',
    ];

    public const SOURCE_SJC  = 'SJC';
    public const SOURCE_BTMC = 'BTMC';

    /** 1 luong (lượng) = 37.5 g; 1 chi (chỉ) = 1/10 luong; 1 troy ounce = 31.1034768 g. */
    public const GRAMS_PER_LUONG = 37.5;
    public const GRAMS_PER_OUNCE = 31.1034768;

    /** Spread the shop keeps between buying from you and selling to you (VND per luong). */
    public function getSpreadAttribute(): ?int
    {
        return $this->sell_price !== null ? $this->sell_price - $this->buy_price : null;
    }

    /** "VÀNG MIẾNG SJC (Vàng SJC)" -> ["VÀNG MIẾNG SJC", "Vàng SJC"]. */
    public function getDisplayNameAttribute(): string
    {
        return trim(preg_replace('/\s*\([^)]*\)\s*$/u', '', $this->product)) ?: $this->product;
    }
}

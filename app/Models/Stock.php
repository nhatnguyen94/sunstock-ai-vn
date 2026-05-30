<?php

/**
 * Author: Sun Nguyen
 * Email: nhat.nguyenminh94@gmail.com
 * Github: https://github.com/nhatnguyen94
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    protected $fillable = [
        'symbol',
        'name',
        'is_active',
        'created_at',
        'updated_at',
    ];

    /**
     * Reference data (exchange, industry, organ_name) lives in stock_symbols.
     * Join via symbol string key — no FK column needed.
     */
    public function symbolInfo()
    {
        return $this->hasOne(StockSymbol::class, 'symbol', 'symbol');
    }

    public function prices()
    {
        return $this->hasMany(StockPrice::class);
    }

    /**
     * The single most recent price row for this stock.
     * Eager-load in admin listing to avoid N+1.
     */
    public function latestPrice()
    {
        return $this->hasOne(StockPrice::class)->latestOfMany('date');
    }
}

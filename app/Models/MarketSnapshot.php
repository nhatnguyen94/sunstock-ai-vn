<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One trading session's market overview (see py/get_market_overview.py). `quotes` is kept on the newest row only. */
class MarketSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = ['trade_date', 'data', 'quotes', 'fetched_at', 'synced_at'];

    protected $casts = [
        'trade_date' => 'date:Y-m-d',
        'data' => 'array',
        'quotes' => 'array',
        'fetched_at' => 'datetime',
        'synced_at' => 'datetime',
    ];
}

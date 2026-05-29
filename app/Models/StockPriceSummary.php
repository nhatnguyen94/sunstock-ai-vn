<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockPriceSummary extends Model
{
    public $timestamps = false;

    protected $fillable = ['stock_id', 'period_start', 'open', 'high', 'low', 'close', 'volume'];

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }
}

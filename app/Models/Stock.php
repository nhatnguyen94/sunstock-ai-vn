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
        'exchange',
        'industry',
        'market_cap',
        'is_active',
        'created_at',
        'updated_at',
    ];

    public function prices()
    {
        return $this->hasMany(StockPrice::class);
    }
}

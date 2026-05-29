<?php

/**
 * Author: Sun Nguyen
 * Email: nhat.nguyenminh94@gmail.com
 * Github: https://github.com/nhatnguyen94
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockPrice extends Model
{
    // created_at / updated_at dropped in partition migration (immutable market data)
    public $timestamps = false;

    protected $fillable = ['stock_id', 'date', 'open', 'high', 'low', 'close', 'volume'];

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }
}

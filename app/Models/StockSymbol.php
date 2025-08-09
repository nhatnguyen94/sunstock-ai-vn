<?php
/**
 * Author: Sun Nguyen
 * Email: nhat.nguyenminh94@gmail.com
 * Github: https://github.com/nhatnguyen94
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockSymbol extends Model
{
    protected $table = 'stock_symbols';
    protected $fillable = ['symbol', 'name', 'updated_at'];
    public $timestamps = false;
}
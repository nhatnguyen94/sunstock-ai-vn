<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $fillable = [
        'currency_code', 'currency_name', 'buy_cash', 'buy_transfer', 'sell', 'date'
    ];
}
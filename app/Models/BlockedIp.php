<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockedIp extends Model
{
    public $timestamps = false;

    protected $fillable = ['ip', 'reason', 'blocked_by', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];
}

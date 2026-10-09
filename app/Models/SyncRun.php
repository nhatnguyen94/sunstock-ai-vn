<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncRun extends Model
{
    public $timestamps = false;

    protected $fillable = ['command', 'ok', 'duration_ms', 'output', 'ran_at'];

    protected $casts = [
        'ok' => 'boolean',
        'duration_ms' => 'integer',
        'ran_at' => 'datetime',
    ];
}

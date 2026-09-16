<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueJobLog extends Model
{
    protected $fillable = [
        'job_id',
        'job_class',
        'queue',
        'summary',
        'status',
        'started_at',
        'finished_at',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}

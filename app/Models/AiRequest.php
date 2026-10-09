<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRequest extends Model
{
    public const KIND_CHAT = 'chat';

    public const KIND_PREDICT = 'predict';

    public const STATUS_OK = 'ok';

    public const STATUS_ERROR = 'error';

    public const STATUS_REFUSED = 'refused';

    public $timestamps = false;

    protected $fillable = ['user_id', 'kind', 'status', 'model', 'duration_ms', 'question', 'created_at'];

    protected $casts = [
        'duration_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

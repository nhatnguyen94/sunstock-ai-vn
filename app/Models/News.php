<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class News extends Model
{
    protected $fillable = [
        'title',
        'description',
        'url',
        'url_hash',
        'source',
        'image_url',
        'category_id',
        'published_at',
        'synced_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'synced_at'    => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class, 'category_id');
    }
}


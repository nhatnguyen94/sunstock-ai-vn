<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'user_name',
        'event_type',
        'description',
        'properties',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Map event_type to a Tabler icon name and badge color.
     */
    public static function iconConfig(): array
    {
        return [
            'user_register'    => ['icon' => 'user-plus',    'color' => 'green'],
            'user_login'       => ['icon' => 'login',        'color' => 'blue'],
            'admin_login'      => ['icon' => 'shield',       'color' => 'indigo'],
            'portfolio_created'=> ['icon' => 'briefcase',    'color' => 'blue'],
            'portfolio_deleted'=> ['icon' => 'trash',        'color' => 'red'],
            'stock_added'      => ['icon' => 'trending-up',  'color' => 'yellow'],
            'stock_removed'    => ['icon' => 'trending-down','color' => 'orange'],
            'news_sync'        => ['icon' => 'refresh',      'color' => 'purple'],
            'stock_price_sync' => ['icon' => 'database',     'color' => 'teal'],
            'admin_action'     => ['icon' => 'settings',     'color' => 'gray'],
        ];
    }

    public function getIconAttribute(): string
    {
        return self::iconConfig()[$this->event_type]['icon'] ?? 'activity';
    }

    public function getColorAttribute(): string
    {
        return self::iconConfig()[$this->event_type]['color'] ?? 'gray';
    }
}

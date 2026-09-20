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
            // icon = legacy name, ti = Tabler icon class used by the admin timeline, label = Vietnamese filter label
            'user_register'    => ['icon' => 'user-plus',    'color' => 'green',  'ti' => 'ti-user-plus',        'label' => 'Đăng ký user'],
            'user_login'       => ['icon' => 'login',        'color' => 'blue',   'ti' => 'ti-login-2',          'label' => 'Đăng nhập user'],
            'admin_login'      => ['icon' => 'shield',       'color' => 'indigo', 'ti' => 'ti-shield-check',     'label' => 'Đăng nhập admin'],
            'portfolio_created'=> ['icon' => 'briefcase',    'color' => 'blue',   'ti' => 'ti-briefcase',        'label' => 'Tạo portfolio'],
            'portfolio_deleted'=> ['icon' => 'trash',        'color' => 'red',    'ti' => 'ti-trash',            'label' => 'Xóa portfolio'],
            'portfolio_trade'  => ['icon' => 'trending-up',  'color' => 'green',  'ti' => 'ti-arrows-exchange',  'label' => 'Giao dịch mua/bán'],
            'watchlist_added'  => ['icon' => 'star',         'color' => 'yellow', 'ti' => 'ti-star',             'label' => 'Theo dõi cổ phiếu'],
            'stock_added'      => ['icon' => 'trending-up',  'color' => 'yellow', 'ti' => 'ti-trending-up',      'label' => 'Thêm cổ phiếu'],
            'stock_removed'    => ['icon' => 'trending-down','color' => 'orange', 'ti' => 'ti-trending-down',    'label' => 'Xóa cổ phiếu'],
            'news_sync'        => ['icon' => 'refresh',      'color' => 'purple', 'ti' => 'ti-news',             'label' => 'Sync news'],
            'stock_price_sync' => ['icon' => 'database',     'color' => 'teal',   'ti' => 'ti-database-import',  'label' => 'Sync giá cổ phiếu'],
            'admin_action'     => ['icon' => 'settings',     'color' => 'gray',   'ti' => 'ti-settings',         'label' => 'Hành động admin'],
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

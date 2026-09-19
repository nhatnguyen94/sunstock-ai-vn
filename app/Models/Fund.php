<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fund extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'short_name', 'name', 'fund_type', 'type_code', 'fund_owner_name', 'management_fee',
        'inception_date', 'nav', 'nav_update_at',
        'nav_change_previous', 'nav_change_1m', 'nav_change_3m', 'nav_change_6m',
        'nav_change_12m', 'nav_change_24m', 'nav_change_36m', 'nav_change_36m_annualized',
        'nav_change_last_year', 'nav_change_inception', 'fund_id_fmarket', 'synced_at',
    ];

    protected $casts = [
        'management_fee'            => 'float',
        'nav'                       => 'float',
        'nav_change_previous'       => 'float',
        'nav_change_1m'             => 'float',
        'nav_change_3m'             => 'float',
        'nav_change_6m'             => 'float',
        'nav_change_12m'            => 'float',
        'nav_change_24m'            => 'float',
        'nav_change_36m'            => 'float',
        'nav_change_36m_annualized' => 'float',
        'nav_change_last_year'      => 'float',
        'nav_change_inception'      => 'float',
        'inception_date'            => 'date',
        'nav_update_at'             => 'date',
        'synced_at'                 => 'datetime',
    ];

    public const TYPE_STOCK    = 'STOCK';
    public const TYPE_BOND     = 'BOND';
    public const TYPE_BALANCED = 'BALANCED';
    public const TYPE_MMF      = 'MMF';
    public const TYPE_OTHER    = 'OTHER';

    /** type_code => label shown in the UI. */
    public const TYPE_LABELS = [
        self::TYPE_STOCK    => 'Quỹ cổ phiếu',
        self::TYPE_BOND     => 'Quỹ trái phiếu',
        self::TYPE_BALANCED => 'Quỹ cân bằng',
        self::TYPE_MMF      => 'Quỹ tiền tệ (MMF)',
        self::TYPE_OTHER    => 'Khác',
    ];

    /** Return windows offered as sort keys / comparison columns: column => label. */
    public const RETURN_COLUMNS = [
        'nav_change_1m'             => '1 tháng',
        'nav_change_3m'             => '3 tháng',
        'nav_change_6m'             => '6 tháng',
        'nav_change_12m'            => '1 năm',
        'nav_change_24m'            => '2 năm',
        'nav_change_36m'            => '3 năm',
        'nav_change_36m_annualized' => '3 năm/năm',
        'nav_change_inception'      => 'Từ khi thành lập',
    ];

    /** Map Fmarket's Vietnamese fund_type label onto a stable code (labels may change; codes don't). */
    public static function typeCodeFromLabel(?string $label): string
    {
        $l = mb_strtolower((string) $label);

        return match (true) {
            str_contains($l, 'cổ phiếu') => self::TYPE_STOCK,
            str_contains($l, 'trái phiếu') => self::TYPE_BOND,
            str_contains($l, 'cân bằng') => self::TYPE_BALANCED,
            str_contains($l, 'mmf'), str_contains($l, 'tiền tệ') => self::TYPE_MMF,
            default => self::TYPE_OTHER,
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type_code] ?? self::TYPE_LABELS[self::TYPE_OTHER];
    }
}

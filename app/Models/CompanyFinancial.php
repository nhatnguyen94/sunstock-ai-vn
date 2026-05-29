<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyFinancial extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'symbol',
        'type',
        'period',
        'raw_data',
        'synced_at',
    ];

    protected $casts = [
        'raw_data'  => 'array',
        'synced_at' => 'datetime',
    ];

    /**
     * How old (in days) before we consider the cached data stale
     * and allow a background refresh.
     */
    public const STALE_DAYS = 30;

    /**
     * Return true if this record has never been synced or is older than STALE_DAYS.
     */
    public function isStale(): bool
    {
        if (is_null($this->synced_at)) {
            return true;
        }
        return $this->synced_at->diffInDays(now()) >= self::STALE_DAYS;
    }
}

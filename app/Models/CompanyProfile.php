<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyProfile extends Model
{
    public $timestamps = false;

    protected $fillable = ['symbol', 'data', 'synced_at'];

    protected $casts = [
        'data'      => 'array',
        'synced_at' => 'datetime',
    ];

    /**
     * Older than this, the page still serves the cached copy instantly but queues a
     * background refresh (stale-while-revalidate). Short on purpose: the valuation
     * snapshot (price, market cap, upcoming dividends) is part of the profile.
     */
    public const STALE_DAYS = 3;

    public function isStale(): bool
    {
        return $this->synced_at === null || $this->synced_at->diffInDays(now()) >= self::STALE_DAYS;
    }
}

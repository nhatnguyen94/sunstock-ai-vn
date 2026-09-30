<?php

namespace App\Models;

use App\Support\EtfMeta;
use Illuminate\Database\Eloquent\Model;

/**
 * One exchange-traded fund (or listed closed-end fund). Only the roster lives here; prices are the ordinary
 * `stocks` / `stock_prices` rows for the same symbol.
 */
class Etf extends Model
{
    public const KIND_ETF = 'etf';
    public const KIND_CLOSED = 'closed';

    protected $fillable = ['symbol', 'name', 'name_en', 'exchange', 'kind', 'synced_at'];

    protected function casts(): array
    {
        return ['synced_at' => 'datetime'];
    }

    /** "SSIAM", "DCVFM"... parsed from the name; null when unrecognised. */
    public function getManagerAttribute(): ?string
    {
        return EtfMeta::manager($this->name);
    }

    /** Index the fund tracks, e.g. "VN30"; null for closed-end funds and unrecognised names. */
    public function getTrackedIndexAttribute(): ?string
    {
        return $this->kind === self::KIND_ETF ? EtfMeta::trackedIndex($this->name) : null;
    }
}

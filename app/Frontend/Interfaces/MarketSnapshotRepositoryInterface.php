<?php

namespace App\Frontend\Interfaces;

use App\Models\MarketSnapshot;
use Illuminate\Support\Carbon;

interface MarketSnapshotRepositoryInterface
{
    /** Newest session's snapshot. `quotes` (~90 KB of JSON) is only loaded on request. */
    public function latest(bool $withQuotes = false): ?MarketSnapshot;

    /** The session before `$before` (data only), for "vs previous session" comparisons. */
    public function previous(Carbon $before): ?MarketSnapshot;

    /**
     * Upsert the snapshot of `payload['trade_date']` (the same session is overwritten as the day goes on).
     * The per-symbol quotes are kept on the newest row only.
     *
     * @param array<string, mixed> $payload output of py/get_market_overview.py
     */
    public function save(array $payload): MarketSnapshot;
}

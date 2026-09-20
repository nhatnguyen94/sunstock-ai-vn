<?php

namespace App\Frontend\Controllers;

use App\Frontend\Services\MarketOverviewService;
use App\Frontend\Services\WatchlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class MarketController extends Controller
{
    public function __construct(
        private readonly MarketOverviewService $market,
        private readonly WatchlistService $watchlist
    ) {}

    /** Polled by the home page during the session: everything the market section shows (+ the user's watchlist). */
    public function data(): JsonResponse
    {
        $o = $this->market->overview();

        if (! $o['has_data']) {
            return response()->json(['success' => false, 'message' => $o['error'] ?? 'Chưa có dữ liệu thị trường.'], 503);
        }

        return response()->json([
            'success' => true,
            'market_open' => $o['market_open'],
            'trade_date' => $o['trade_date']->toDateString(),
            'synced_at' => $o['synced_at']?->toIso8601String(),
            'indices' => $o['indices'],
            'breadth' => $o['breadth'],
            'exchanges' => $o['exchanges'],
            'liquidity' => $o['liquidity'],
            'movers' => $o['movers'],
            'watchlist' => Auth::check() ? $this->watchlist->rows(Auth::id(), 8) : null,
        ]);
    }
}

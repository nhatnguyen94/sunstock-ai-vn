<?php

namespace App\Frontend\Controllers;

use App\Frontend\Services\MarketOverviewService;
use App\Frontend\Services\WatchlistService;
use App\Support\ActivityLogger;
use App\Support\TransformerResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WatchlistController extends Controller
{
    public function __construct(
        private readonly WatchlistService $watchlist,
        private readonly MarketOverviewService $market
    ) {}

    public function index(): View
    {
        // overview() makes sure a snapshot exists / is refreshed in the background before the list is priced
        $market = $this->market->overview();

        return view('watchlist.index', [
            'rows' => $this->watchlist->rows(Auth::id()),
            'market_open' => $market['market_open'] ?? false,
            'trade_date' => $market['trade_date'] ?? null,
            'max_items' => WatchlistService::MAX_ITEMS,
        ]);
    }

    /** Polled by the page while the market is open. */
    public function data(): JsonResponse
    {
        return TransformerResponse::success(extra: [
            'market_open' => $this->market->isMarketOpen(),
            'rows' => $this->watchlist->rows(Auth::id()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $result = $this->watchlist->add(Auth::id(), (string) $request->input('symbol'));

        if (! $result['ok']) {
            return TransformerResponse::failed($result['message'], $result['status']);
        }

        if ($result['added']) {
            ActivityLogger::log('watchlist_added', "Theo dõi {$result['symbol']}", ['symbol' => $result['symbol']]);
        }

        return TransformerResponse::success(
            $result['added'] ? "Đã thêm {$result['symbol']} vào danh sách theo dõi." : "{$result['symbol']} đã có trong danh sách theo dõi.",
            extra: ['watched' => true, 'symbol' => $result['symbol']],
        );
    }

    public function destroy(string $symbol): JsonResponse
    {
        $this->watchlist->remove(Auth::id(), $symbol);

        return TransformerResponse::success('Đã bỏ theo dõi '.strtoupper($symbol).'.', extra: ['watched' => false, 'symbol' => strtoupper($symbol)]);
    }
}

<?php

namespace App\Frontend\Controllers;

use App\Frontend\Services\EtfService;
use App\Frontend\Services\WatchlistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EtfController extends Controller
{
    public function __construct(
        private readonly EtfService $service,
        private readonly WatchlistService $watchlist
    ) {}

    /** Catalog: filter by kind / tracked index / text, sort by liquidity or any return window. */
    public function index(Request $request): View
    {
        return view('etf.index', $this->service->catalog($request->query()) + ['max' => EtfService::MAX_COMPARE] + $this->watchData());
    }

    public function show(string $symbol): View
    {
        $detail = $this->service->detail($symbol) ?? abort(404);

        return view('etf.show', $detail + ['max' => EtfService::MAX_COMPARE] + $this->watchData());
    }

    /** ★ state for the buttons on the page: only the signed-in user's own list. */
    private function watchData(): array
    {
        return ['watch' => ['auth' => Auth::check(), 'watched' => Auth::check() ? $this->watchlist->symbols(Auth::id()) : []]];
    }
}

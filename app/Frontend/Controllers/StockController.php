<?php

/**
 * Author: Sun Nguyen
 * Email: nhat.nguyenminh94@gmail.com
 * Github: https://github.com/nhatnguyen94
 */

namespace App\Frontend\Controllers;

use App\Frontend\Interfaces\NewsServiceInterface;
use App\Frontend\Interfaces\StockRepositoryInterface;
use App\Frontend\Services\AiService;
use App\Frontend\Services\AiUsageService;
use App\Frontend\Services\CompanyFinancialService;
use App\Frontend\Services\ExchangeRateService;
use App\Frontend\Services\HomeDashboardService;
use App\Frontend\Services\MarketHeatmapService;
use App\Frontend\Services\MarketOverviewService;
use App\Frontend\Services\PortfolioService;
use App\Frontend\Services\StockPriceFreshness;
use App\Frontend\Services\StockService;
use App\Frontend\Services\WatchlistService;
use App\Models\AiRequest;
use App\Models\HotIndustry;
use App\Models\Stock;
use App\Models\StockPrice;
use App\Support\MarketBrief;
use App\Support\SiteSettings;
use App\Support\StockQuoteSummary;
use App\Support\TransformerResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Throwable;

class StockController extends Controller
{
    protected $stockRepo;

    protected $stockService;

    protected $exchangeService;

    protected $financialService;

    protected $freshness;

    public function __construct(
        StockRepositoryInterface $stockRepo,
        StockService $stockService,
        ExchangeRateService $exchangeService,
        CompanyFinancialService $financialService,
        StockPriceFreshness $freshness
    ) {
        $this->stockRepo = $stockRepo;
        $this->stockService = $stockService;
        $this->exchangeService = $exchangeService;
        $this->financialService = $financialService;
        $this->freshness = $freshness;
    }

    /**
     * Show homepage with featured stocks.
     *
     * @return View
     */
    public function home(Request $request, NewsServiceInterface $newsService, MarketOverviewService $marketService, WatchlistService $watchlistService, MarketHeatmapService $heatmapService, PortfolioService $portfolioService, HomeDashboardService $dashboard)
    {
        $symbols = SiteSettings::featuredSymbols();   // chosen in Admin > Giao diện & Cache (default FPT, VNM, ACB)

        // Cache featured stocks data for 10 minutes
        $featured = Cache::remember(SiteSettings::featuredCacheKey(), 600, function () use ($symbols) {
            foreach ($symbols as $symbol) {
                $stock = Stock::firstOrCreate(['symbol' => $symbol]);
                $latestPrice = StockPrice::where('stock_id', $stock->id)
                    ->orderByDesc('date')->first();
                // if (! $latestPrice || Carbon::parse($latestPrice->date)->lt(Carbon::today())) {
                //     // Chỉ update nếu chưa có giá của ngày hôm nay
                //     $this->stockRepo->updateStockPriceFromPython($symbol);
                // }
            }

            return $this->stockRepo->getFeaturedStocks($symbols);
        });

        // Cache exchange rates for 30 minutes
        $today = Carbon::now()->format('Y-m-d');
        $exchangeRates = Cache::remember("exchange_rates_home_{$today}", 1800, function () use ($today) {
            $rates = $this->exchangeService->getRatesByDate($today);
            if (! empty($rates) && isset($rates[0]['currency_code'])) {
                $rates = [$today => $rates];
            }

            return $rates;
        });

        // Load hot industries from DB (populated by scheduler sync:hot-industries).
        // Falls back to Python on first run, persisting result for subsequent requests.
        $hotIndustriesRaw = $this->getHotIndustries();

        // Paginate array manually
        $page = $request->get('page', 1);
        $perPage = 10;
        $hotIndustries = new LengthAwarePaginator(
            collect($hotIndustriesRaw)->slice(($page - 1) * $perPage, $perPage)->values(),
            count($hotIndustriesRaw),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Cache news for 15 minutes (reads from DB, very fast)
        $news = Cache::remember('homepage_news', 900, function () use ($newsService) {
            return $newsService->getLatestNews(6);
        });

        // Market overview (indices, breadth, movers) + the signed-in user's watchlist. A failure here must never
        // take the home page down: the section then shows its own empty state.
        $blocks = SiteSettings::homeBlocks();   // what an admin switched on (Admin > Giao diện & Cache)
        $market = ['has_data' => false];
        try {
            $market = $marketService->overview();
        } catch (Throwable $e) {
            report($e);
        }
        // the heat map is an extra on top of the market section: it never takes the page down
        $heatmap = null;
        $brief = null;
        if ($market['has_data'] ?? false) {
            try {
                $heatmap = $blocks['heatmap'] || $blocks['brief'] ? $heatmapService->heatmap() : null;
            } catch (Throwable $e) {
                report($e);
            }
            try {
                $brief = $blocks['brief'] ? MarketBrief::build($market, $heatmap) : null;
            } catch (Throwable $e) {
                report($e);
            }
            $heatmap = $blocks['heatmap'] ? $heatmap : null;
            $market['foreign'] = $blocks['foreign'] ? ($market['foreign'] ?? null) : null;
        }
        // "Của tôi" (signed-in visitors only): their portfolios in one line, never able to take the page down
        $mine = null;
        if (Auth::check()) {
            try {
                $mine = ['portfolio' => $portfolioService->homeSummary(Auth::id())];
            } catch (Throwable $e) {
                report($e);
            }
        }
        // world strip, "Vàng · Tỷ giá · Quỹ", sentiment, signals and events: each part tolerates its own failure (null)
        $extras = $dashboard->build($market, Auth::id(), $blocks);
        $watchRows = Auth::check() ? $watchlistService->rows(Auth::id(), 8) : null;
        $watched = Auth::check() ? $watchlistService->symbols(Auth::id()) : [];

        return view('index', compact('featured', 'exchangeRates', 'hotIndustries', 'news', 'market', 'watchRows', 'watched', 'heatmap', 'brief', 'mine', 'extras', 'blocks'));
    }

    /**
     * Load hot industries from DB. If DB is empty (first run before scheduler has run),
     * fall back to Python and persist the result so subsequent requests are instant.
     */
    private function getHotIndustries(): array
    {
        $rows = HotIndustry::select('symbol', 'organ_name', 'icb_name3')->get()->toArray();

        if (! empty($rows)) {
            return $rows;
        }

        // First-run fallback: call Python, persist to DB
        $data = $this->stockService->fetchHotIndustriesFromPython(100);

        if (! empty($data)) {
            $inserts = array_map(fn ($item) => [
                'symbol' => $item['symbol'] ?? '',
                'organ_name' => $item['organ_name'] ?? null,
                'icb_name3' => $item['icb_name3'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ], $data);

            foreach (array_chunk($inserts, 200) as $chunk) {
                HotIndustry::insert($chunk);
            }
        }

        return $data;
    }

    /**
     * Show historical price and overview for a stock symbol.
     *
     * @return View
     */
    public function index(Request $request)
    {
        $symbol = strtoupper(trim((string) $request->input('symbol', 'E1VFVN30')));
        TransformerResponse::abortUnless(StockPriceFreshness::isValidSymbol($symbol), TransformerResponse::HTTP_NOT_FOUND);

        // Never wait on a data provider for a symbol that already has history: serve it, queue the missing sessions
        // in the background and paint the newest session from the market snapshot. Only a symbol with NO history
        // triggers one short (~1 s) fetch. See StockPriceFreshness.
        $freshness = $this->freshness->ensure($symbol);

        $prices = $this->freshness->withLiveBar($this->stockRepo->getStockPrice($symbol), $symbol);
        $data = $prices['rows'];
        $liveBar = $prices['live'];
        $overview = $this->stockRepo->getOverview($symbol);
        $summary = StockQuoteSummary::build($data, $symbol);   // header numbers in the unit people read (whole VND / index points)

        $error = match ($freshness['status']) {
            'failed' => $freshness['error'] ?? null,
            'unknown' => "Không tìm thấy mã {$symbol}. Hãy chọn mã từ danh sách gợi ý.",
            default => null,
        };

        return view('stock.stock', compact('symbol', 'data', 'overview', 'summary', 'liveBar', 'error'));
    }

    /**
     * Search and return stock symbols as JSON.
     *
     * @return JsonResponse
     */
    public function getStockSymbols(Request $request)
    {
        $query = $request->input('q');
        $stocks = $this->stockRepo->searchSymbols($query);

        return TransformerResponse::json($stocks);
    }

    /**
     * Show stock comparison page.
     */
    public function compare(Request $request): View
    {
        $symbols = $request->input('symbols', '');

        return view('stock.compare', compact('symbols'));
    }

    /**
     * API: return comparison data for multiple stocks.
     */
    public function compareData(Request $request): JsonResponse
    {
        $request->validate([
            'symbols' => 'required|string|max:100',
        ]);

        $symbols = array_unique(array_filter(array_map('strtoupper', explode(',', $request->input('symbols')))));
        $symbols = array_slice($symbols, 0, 4); // Max 4 stocks

        $symbols = array_values(array_filter($symbols, fn ($s) => StockPriceFreshness::isValidSymbol($s)));
        $this->freshness->ensureMany($symbols);

        $result = [];
        foreach ($symbols as $symbol) {
            // Same policy as the stock page (history-less symbols are fetched together in ONE script run below)
            $prices = $this->freshness->withLiveBar($this->stockRepo->getStockPrice($symbol), $symbol)['rows'];
            if (empty($prices)) {
                continue;
            }

            // Normalize to percentage change from first price
            $firstClose = $prices[0]['close'] ?? 1;
            $normalized = array_map(function ($p) use ($firstClose) {
                return [
                    'time' => $p['time'],
                    'close' => $p['close'],
                    'percent' => round((($p['close'] - $firstClose) / max($firstClose, 0.01)) * 100, 2),
                ];
            }, $prices);

            $overview = $this->stockRepo->getOverview($symbol);
            $lastPrice = end($prices);

            $result[] = [
                'symbol' => $symbol,
                'name' => $overview['name'] ?? $symbol,
                'prices' => $normalized,
                'latest_close' => $lastPrice['close'] ?? 0,
                'change_percent' => end($normalized)['percent'] ?? 0,
                'high' => max(array_column($prices, 'close')),
                'low' => min(array_column($prices, 'close')),
            ];
        }

        return TransformerResponse::json($result);
    }

    /**
     * API: return company financial data (income / balance / cashflow / ratio).
     * Cached 4 hours per symbol+type+period combination.
     */
    public function finance(Request $request): JsonResponse
    {
        $symbol = strtoupper(trim($request->input('symbol', '')));
        $type = $request->input('type', 'income');
        $period = $request->input('period', 'quarter');

        if (! $symbol || ! preg_match('/^[A-Z0-9]{1,20}$/', $symbol)) {
            return TransformerResponse::badRequest('Invalid symbol', ['error' => 'Invalid symbol']);
        }
        if (! in_array($type, ['income', 'balance', 'cashflow', 'ratio'])) {
            return TransformerResponse::badRequest('Invalid type', ['error' => 'Invalid type']);
        }
        if (! in_array($period, ['quarter', 'year'])) {
            return TransformerResponse::badRequest('Invalid period', ['error' => 'Invalid period']);
        }

        return TransformerResponse::json(
            $this->financialService->getFinancialData($symbol, $type, $period)
        );
    }

    /**
     * Stock screener — filter cached financial ratios (P/E, P/B, ROE, dividend yield, debt/equity).
     */
    public function screener(Request $request): View
    {
        $filters = $request->only([
            'pe_min', 'pe_max', 'pb_min', 'pb_max', 'roe_min', 'dividend_yield_min', 'debt_equity_max', 'sort', 'dir',
        ]);

        $results = $this->financialService->screenStocks($filters);

        return view('stock.screener', compact('results', 'filters'));
    }

    public function aiChat(Request $request, AiService $aiService, AiUsageService $usage)
    {
        $request->validate([
            'message' => 'required|string|max:500',
            'lang' => 'nullable|string|in:vi,en',
        ]);

        $lang = $request->input('lang', 'vi');

        // Strip control characters and null bytes to prevent prompt injection
        $question = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $request->input('message'));
        $question = trim($question);

        if (empty($question)) {
            return TransformerResponse::success(extra: ['answer' => $lang === 'en' ? 'Please enter a valid question.' : 'Vui lòng nhập câu hỏi hợp lệ.']);
        }

        $user = $request->user();
        $startedAt = microtime(true);
        if ($user && $refusal = $usage->refusal($user)) {
            $usage->record($user, AiRequest::KIND_CHAT, AiRequest::STATUS_REFUSED, null, $startedAt, $question);

            return TransformerResponse::failed($refusal['message'], $refusal['status'], extra: ['error' => true]);
        }

        $answer = $aiService->tryAsk($question, $lang);
        $usage->record($user, AiRequest::KIND_CHAT, $answer === null ? AiRequest::STATUS_ERROR : AiRequest::STATUS_OK, $aiService->lastModel(), $startedAt, $question);

        if ($answer === null) {
            return TransformerResponse::serviceUnavailable($aiService->unavailableMessage($lang), ['error' => true]);
        }

        return TransformerResponse::success(extra: ['answer' => $answer]);
    }
}

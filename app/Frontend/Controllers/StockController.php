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
use App\Frontend\Services\ExchangeRateService;
use App\Frontend\Services\StockService;
use App\Models\Stock;
use App\Models\StockPrice;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class StockController extends Controller
{
    protected $stockRepo;

    protected $stockService;

    protected $exchangeService;

    public function __construct(
        StockRepositoryInterface $stockRepo,
        StockService $stockService,
        ExchangeRateService $exchangeService
    ) {
        $this->stockRepo = $stockRepo;
        $this->stockService = $stockService;
        $this->exchangeService = $exchangeService;
    }

    /**
     * Show homepage with featured stocks.
     *
     * @return View
     */
    public function home(Request $request, NewsServiceInterface $newsService)
    {
        $symbols = ['FPT', 'VNM', 'VCB'];

        // Cache featured stocks data for 10 minutes
        $featured = Cache::remember('featured_stocks', 600, function () use ($symbols) {
            foreach ($symbols as $symbol) {
                $stock = Stock::firstOrCreate(['symbol' => $symbol]);
                $latestPrice = StockPrice::where('stock_id', $stock->id)
                    ->orderByDesc('date')->first();
                if (! $latestPrice || Carbon::parse($latestPrice->date)->lt(now()->subDay())) {
                    $this->stockRepo->updateStockPriceFromPython($symbol);
                }
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

        $hotIndustriesRaw = Cache::remember('hot_industries', 3600, function () {
            return $this->stockService->fetchHotIndustriesFromPython(30);
        });

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

        // Cache news for 15 minutes
        $news = Cache::remember('homepage_news', 900, function () use ($newsService) {
            return $newsService->getLatestNews(4);
        });

        return view('index', compact('featured', 'exchangeRates', 'hotIndustries', 'news'));
    }

    /**
     * Show historical price and overview for a stock symbol.
     *
     * @return View
     */
    public function index(Request $request)
    {
        $symbol = strtoupper($request->input('symbol', 'E1VFVN30'));
        $stock = Stock::firstOrCreate(['symbol' => $symbol]);

        // Only call Python if not recently updated (cache flag for 1 hour)
        $cacheKey = "stock_updated_{$symbol}";
        if (! Cache::has($cacheKey)) {
            $latestDate = StockPrice::where('stock_id', $stock->id)->max('date');
            if (! $latestDate || Carbon::parse($latestDate)->lt(now()->subDay())) {
                $this->stockRepo->updateStockPriceFromPython($symbol);
            }
            Cache::put($cacheKey, true, 3600);
        }

        $data = $this->stockRepo->getStockPrice($symbol);
        $overview = $this->stockRepo->getOverview($symbol);

        return view('stock.stock', compact('symbol', 'data', 'overview'));
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

        return response()->json($stocks);
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

        $result = [];
        foreach ($symbols as $symbol) {
            if (!preg_match('/^[A-Za-z0-9]{1,20}$/', $symbol)) continue;

            $stock = Stock::firstOrCreate(['symbol' => $symbol]);

            // Ensure data exists
            $cacheKey = "stock_updated_{$symbol}";
            if (!Cache::has($cacheKey)) {
                $latestDate = StockPrice::where('stock_id', $stock->id)->max('date');
                if (!$latestDate || Carbon::parse($latestDate)->lt(now()->subDay())) {
                    $this->stockRepo->updateStockPriceFromPython($symbol);
                }
                Cache::put($cacheKey, true, 3600);
            }

            $prices = $this->stockRepo->getStockPrice($symbol);
            if (empty($prices)) continue;

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

        return response()->json($result);
    }

    public function aiChat(Request $request, AiService $aiService)
    {
        $request->validate([
            'message' => 'required|string|max:500',
            'lang' => 'nullable|string|in:vi,en',
        ]);

        $question = $request->input('message');
        $lang = $request->input('lang', 'vi');
        $answer = $aiService->ask($question, $lang);

        return response()->json(['answer' => $answer]);
    }
}

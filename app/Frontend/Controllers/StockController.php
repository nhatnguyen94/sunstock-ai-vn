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
use App\Frontend\Services\CompanyFinancialService;
use App\Frontend\Services\ExchangeRateService;
use App\Frontend\Services\StockService;
use App\Models\HotIndustry;
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
    protected $financialService;

    public function __construct(
        StockRepositoryInterface $stockRepo,
        StockService $stockService,
        ExchangeRateService $exchangeService,
        CompanyFinancialService $financialService
    ) {
        $this->stockRepo        = $stockRepo;
        $this->stockService     = $stockService;
        $this->exchangeService  = $exchangeService;
        $this->financialService = $financialService;
    }

    /**
     * Show homepage with featured stocks.
     *
     * @return View
     */
    public function home(Request $request, NewsServiceInterface $newsService)
    {
        $symbols = ['FPT', 'VNM', 'ACB'];

        // Cache featured stocks data for 10 minutes
        $featured = Cache::remember('featured_stocks', 600, function () use ($symbols) {
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

        return view('index', compact('featured', 'exchangeRates', 'hotIndustries', 'news'));
    }

    /**
     * Load hot industries from DB. If DB is empty (first run before scheduler has run),
     * fall back to Python and persist the result so subsequent requests are instant.
     */
    private function getHotIndustries(): array
    {
        $rows = HotIndustry::select('symbol', 'organ_name', 'icb_name3')->get()->toArray();

        if (!empty($rows)) {
            return $rows;
        }

        // First-run fallback: call Python, persist to DB
        $data = $this->stockService->fetchHotIndustriesFromPython(100);

        if (!empty($data)) {
            $inserts = array_map(fn($item) => [
                'symbol'     => $item['symbol'] ?? '',
                'organ_name' => $item['organ_name'] ?? null,
                'icb_name3'  => $item['icb_name3'] ?? null,
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
        $symbol = strtoupper($request->input('symbol', 'E1VFVN30'));
        $stock = Stock::firstOrCreate(['symbol' => $symbol]);

        // Only call Python if not recently updated (cache flag for 1 hour)
        $cacheKey = "stock_updated_{$symbol}";
        if (! Cache::has($cacheKey)) {
            $latestDate = StockPrice::where('stock_id', $stock->id)->max('date');
            if (! $latestDate || Carbon::parse($latestDate)->lt(Carbon::today())) {
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
                if (!$latestDate || Carbon::parse($latestDate)->lt(Carbon::today())) {
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

    /**
     * API: return company financial data (income / balance / cashflow / ratio).
     * Cached 4 hours per symbol+type+period combination.
     */
    public function finance(Request $request): JsonResponse
    {
        $symbol = strtoupper(trim($request->input('symbol', '')));
        $type   = $request->input('type',   'income');
        $period = $request->input('period', 'quarter');

        if (! $symbol || ! preg_match('/^[A-Z0-9]{1,20}$/', $symbol)) {
            return response()->json(['error' => 'Invalid symbol'], 400);
        }
        if (! in_array($type, ['income', 'balance', 'cashflow', 'ratio'])) {
            return response()->json(['error' => 'Invalid type'], 400);
        }
        if (! in_array($period, ['quarter', 'year'])) {
            return response()->json(['error' => 'Invalid period'], 400);
        }

        return response()->json(
            $this->financialService->getFinancialData($symbol, $type, $period)
        );
    }

    public function aiChat(Request $request, AiService $aiService)
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
            return response()->json(['answer' => $lang === 'en' ? 'Please enter a valid question.' : 'Vui lòng nhập câu hỏi hợp lệ.']);
        }

        $answer = $aiService->ask($question, $lang);

        return response()->json(['answer' => $answer]);
    }
}

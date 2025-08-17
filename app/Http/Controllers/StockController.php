<?php
/**
 * Author: Sun Nguyen
 * Email: nhat.nguyenminh94@gmail.com
 * Github: https://github.com/nhatnguyen94
 */
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Repositories\StockRepositoryInterface;
use App\Services\StockService;
use App\Models\Stock;
use App\Models\StockPrice;
use App\Models\StockSymbol;
use Carbon\Carbon;
use App\Repositories\ExchangeRateRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use App\Services\AiService;
use App\Services\NewsServiceInterface;

class StockController extends Controller
{
    protected $stockRepo;
    protected $stockService;
    protected $exchangeRateRepo;

    public function __construct(
        StockRepositoryInterface $stockRepo,
        StockService $stockService,
        ExchangeRateRepositoryInterface $exchangeRateRepo
    ) {
        $this->stockRepo = $stockRepo;
        $this->stockService = $stockService;
        $this->exchangeRateRepo = $exchangeRateRepo;
    }

    /**
     * Show homepage with featured stocks.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function home(Request $request, NewsServiceInterface $newsService)
    {
        $symbols = ['FPT', 'VNM', 'VCB'];
        foreach ($symbols as $symbol) {
            $stock = Stock::firstOrCreate(['symbol' => $symbol]);
            $latestPrice = StockPrice::where('stock_id', $stock->id)
                ->orderByDesc('date')->first();
            if (!$latestPrice || Carbon::parse($latestPrice->date)->lt(now()->subDay())) {
                $this->stockRepo->updateStockPriceFromPython($symbol);
            }
        }
        $featured = $this->stockRepo->getFeaturedStocks($symbols);

        $exchangeRates = $this->exchangeRateRepo->getLatestRates(1);

        $hotIndustries = Cache::remember('hot_industries', 3600, function() {
            return $this->stockService->fetchHotIndustriesFromPython(30);
        });

        $news = $newsService->getLatestNews(4);

        return view('index', compact('featured', 'exchangeRates', 'hotIndustries', 'news'));
    }

    /**
     * Show historical price and overview for a stock symbol.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $symbol = strtoupper($request->input('symbol', 'E1VFVN30'));
        $stock = Stock::firstOrCreate(['symbol' => $symbol]);
        $latestDate = StockPrice::where('stock_id', $stock->id)->max('date');
        $now = Carbon::now();

        if (!$latestDate || Carbon::parse($latestDate)->lt($now->subDay())) {
            $this->stockRepo->updateStockPriceFromPython($symbol);
        }

        $data = $this->stockRepo->getStockPrice($symbol);
        $overview = $this->stockRepo->getOverview($symbol);

        return view('stock.stock', compact('symbol', 'data', 'overview'));
    }

    /**
     * Search and return stock symbols as JSON.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStockSymbols(Request $request)
    {
        $query = $request->input('q');
        $stocks = $this->stockRepo->searchSymbols($query);
        return response()->json($stocks);
    }

    public function aiChat(Request $request, AiService $aiService)
    {
        $question = $request->input('message');
        $lang = $request->input('lang', 'vi');
        // Thêm hướng dẫn ngôn ngữ vào prompt
        $prompt = $lang === 'en'
            ? "Answer in English: " . $question
            : "Trả lời bằng tiếng Việt: " . $question;
        $answer = $aiService->askOllama($prompt, 'mistral');
        return response()->json(['answer' => $answer]);
    }
}
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

class StockController extends Controller
{
    protected $stockRepo;
    protected $stockService;

    public function __construct(StockRepositoryInterface $stockRepo, StockService $stockService)
    {
        $this->stockRepo = $stockRepo;
        $this->stockService = $stockService;
    }

    /**
     * Show homepage with featured stocks.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function home(Request $request)
    {
        $symbols = ['FPT', 'VNM', 'VCB'];
        // Kiểm tra và cập nhật giá nếu cần
        foreach ($symbols as $symbol) {
            $stock = Stock::firstOrCreate(['symbol' => $symbol]);
            $latestPrice = StockPrice::where('stock_id', $stock->id)
                ->orderByDesc('date')->first();
            if (!$latestPrice || Carbon::parse($latestPrice->date)->lt(now()->subDay())) {
                $this->stockRepo->updateStockPriceFromPython($symbol);
            }
        }
        $featured = $this->stockRepo->getFeaturedStocks($symbols);
        return view('index', compact('featured'));
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
}
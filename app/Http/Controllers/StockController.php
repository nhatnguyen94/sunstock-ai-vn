<?php 
namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\StockPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\StockSymbol;
use Carbon\Carbon;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $symbol = strtoupper($request->input('symbol', 'E1VFVN30'));
        $stock = Stock::firstOrCreate(['symbol' => $symbol]);

        // Lấy ngày mới nhất trong DB
        $latestDate = StockPrice::where('stock_id', $stock->id)->max('date');
        $now = Carbon::now();

        // Nếu chưa có hoặc cũ hơn 1 ngày, gọi Python để lấy dữ liệu
        if (!$latestDate || Carbon::parse($latestDate)->lt($now->subDay())) {
            $data = $this->fetchStockDataFromPython($symbol);

            if (isset($data['error'])) {
                return view('stock.stock', compact('symbol'))->with('error', $data['error']);
            }

            // Lưu dữ liệu vào DB
            foreach ($data as $item) {
                $date = Carbon::createFromTimestampMs($item['time'])->toDateString();

                StockPrice::updateOrCreate(
                    ['stock_id' => $stock->id, 'date' => $date],
                    [
                        'open' => $item['open'],
                        'high' => $item['high'],
                        'low' => $item['low'],
                        'close' => $item['close'],
                        'volume' => $item['volume'],
                    ]
                );
            }
        }

        // Truy vấn lại từ DB
        $data = StockPrice::where('stock_id', $stock->id)
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'time' => Carbon::parse($item->date)->timestamp * 1000,
                    'open' => $item->open,
                    'high' => $item->high,
                    'low' => $item->low,
                    'close' => $item->close,
                    'volume' => $item->volume,
                ];
            });
            // dd($data);die;
        return view('stock.stock', compact('symbol', 'data'));
    }

    private function fetchStockDataFromPython($symbol)
    {
        $pythonPath = env('PYTHON_PATH', 'python');
        $scriptPath = base_path('get_stock.py');
        $command = "\"{$pythonPath}\" \"{$scriptPath}\" {$symbol}";
        exec($command, $output, $returnVar);

        $jsonStart = strpos(implode("\n", $output), '[');
        $jsonStr = $jsonStart !== false ? substr(implode("\n", $output), $jsonStart) : '';

        return json_decode($jsonStr, true) ?? ['error' => 'Lỗi khi gọi script Python'];
    }

    public function getStockSymbols(Request $request)
    {
        $latest = StockSymbol::orderByDesc('updated_at')->first();
        $needUpdate = !$latest || now()->diffInHours($latest->updated_at) > 24;

        if ($needUpdate) {
            $pythonPath = env('PYTHON_PATH', 'python');
            $scriptPath = base_path('get_stock_list.py');
            $command = "\"{$pythonPath}\" \"{$scriptPath}\"";
            exec($command, $output, $returnVar);

            $jsonStart = strpos(implode("\n", $output), '[');
            $jsonStr = $jsonStart !== false ? substr(implode("\n", $output), $jsonStart) : '';
            $symbols = json_decode($jsonStr, true);

            if (is_array($symbols)) {
                StockSymbol::truncate();
                foreach ($symbols as $item) {
                    StockSymbol::create([
                        'symbol' => $item['symbol'],
                        'name' => $item['organ_name'] ?? '',
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $query = $request->input('q');
        $stocks = StockSymbol::when($query, function ($qBuilder) use ($query) {
                $qBuilder->where('symbol', 'like', "%$query%")
                        ->orWhere('name', 'like', "%$query%");
            })
            ->limit(20)
            ->get();

        return response()->json($stocks);
    }
}

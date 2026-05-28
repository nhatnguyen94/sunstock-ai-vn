<?php

/**
 * Author: Sun Nguyen
 * Email: nhat.nguyenminh94@gmail.com
 * Github: https://github.com/nhatnguyen94
 */

namespace App\Frontend\Services;

use Illuminate\Support\Facades\Log;

class StockService
{
    /**
     * Lấy 1 mã cổ phiếu ngẫu nhiên từ script Python và trả về payload chi tiết.
     * Dùng để debug kiểm tra dữ liệu trả về từ py/vnstock.
     * @return array
     */
    public function debugFetchRandomStockSymbol(): array
    {
        $symbols = $this->fetchStockListFromPython();
        if (empty($symbols)) {
            return ['error' => 'Không lấy được danh sách mã cổ phiếu từ Python'];
        }
        $random = $symbols[array_rand($symbols)];
        $symbol = $random['symbol'] ?? null;
        if (!$symbol) {
            return ['error' => 'Không có symbol hợp lệ'];
        }
        $priceData = $this->fetchStockDataFromPython($symbol);
        return [
            'symbol_row' => $random,
            'price_data' => $priceData,
        ];
    }
    /**
     * Đồng bộ chỉ danh sách mã cổ phiếu và thông tin chi tiết (không bao gồm giá lịch sử).
     * @return array
     */
    public function syncStockSymbolsAndDetails(): array
    {
        try {
            $symbols = $this->fetchStockListFromPython();
            if (empty($symbols)) {
                return ['success' => false, 'message' => 'Không lấy được danh sách mã cổ phiếu từ Python'];
            }

            $now = now();
            $symbolRows = [];
            $stockRows = [];
            foreach ($symbols as $row) {
                if (empty($row['symbol'])) continue;
                $name = $row['organ_name'] ?? $row['name'] ?? null;
                $symbolRows[] = [
                    'symbol' => $row['symbol'],
                    'name' => $name,
                    'updated_at' => $now,
                ];
                $stockRows[] = [
                    'symbol' => $row['symbol'],
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            \App\Models\StockSymbol::upsert($symbolRows, ['symbol'], ['name', 'updated_at']);
            \App\Models\Stock::upsert($stockRows, ['symbol'], ['name', 'updated_at']);

            return ['success' => true, 'message' => 'Đã đồng bộ ' . count($symbolRows) . ' mã cổ phiếu.'];
        } catch (\Throwable $e) {
            Log::error('SyncStockSymbolsAndDetails error', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Đồng bộ giá cổ phiếu lịch sử.
     * @param callable|null $progressCallback
     * @param array|null $symbolsToSync
     * @return array
     */
    public function syncStockPrices(callable $progressCallback = null, ?array $symbolsToSync = null): array
    {
        try {
            if ($symbolsToSync) {
                // Lấy thông tin từ DB cho các mã cụ thể
                $symbols = \App\Models\StockSymbol::whereIn('symbol', $symbolsToSync)->get()->toArray();
            } else {
                // Lấy toàn bộ danh sách mã
                $symbols = \App\Models\StockSymbol::all()->toArray();
            }
            
            if (empty($symbols)) {
                return ['success' => false, 'message' => 'Không có mã cổ phiếu nào để đồng bộ giá.'];
            }

            $symbolToId = \App\Models\Stock::pluck('id', 'symbol')->toArray();

            $count = 0;
            $total = count($symbols);
            $startTime = microtime(true);
            $batchSize = 20; // Giảm batch size để tuân thủ rate limit chặt chẽ hơn
            $symbolChunks = array_chunk($symbols, $batchSize);

            foreach ($symbolChunks as $chunkIndex => $chunk) {
                $priceDataBatch = [];
                $symbolBatch = array_map(fn($row) => $row['symbol'], array_filter($chunk, fn($row) => !empty($row['symbol']) && isset($symbolToId[$row['symbol']])));

                if (empty($symbolBatch)) {
                    continue;
                }

                $batchPriceData = $this->fetchStockDataFromPython(implode(',', $symbolBatch));

                if ($chunkIndex === 0) {
                    Log::info("Raw price data for first batch", ['data' => $batchPriceData]);
                }

                if (is_array($batchPriceData) && isset($batchPriceData['data'])) {
                    foreach ($batchPriceData['data'] as $symbol => $priceData) {
                        if (!isset($symbolToId[$symbol])) continue;
                        
                        foreach ($priceData as $item) {
                            if (!isset($item['date']) && isset($item['time'])) {
                                $item['date'] = date('Y-m-d', is_numeric($item['time']) ? (int)($item['time']/1000) : strtotime($item['time']));
                            }
                            if (empty($item['date'])) continue;
                            $priceDataBatch[] = [
                                'stock_id' => $symbolToId[$symbol],
                                'date' => $item['date'],
                                'open' => $item['open'] ?? null,
                                'high' => $item['high'] ?? null,
                                'low' => $item['low'] ?? null,
                                'close' => $item['close'] ?? null,
                                'volume' => $item['volume'] ?? null,
                            ];
                        }
                    }
                } else {
                    Log::warning("Could not fetch price data for batch", ['symbols' => $symbolBatch, 'response' => $batchPriceData]);
                }

                if (!empty($priceDataBatch)) {
                    \App\Models\StockPrice::upsert($priceDataBatch, ['stock_id', 'date'], ['open', 'high', 'low', 'close', 'volume']);
                }

                $count += count($chunk);

                if ($progressCallback) {
                    $progress = ($total > 0) ? ($count / $total) * 100 : 0;
                    $elapsedTime = microtime(true) - $startTime;
                    $estimatedTotalTime = ($count > 0) ? ($elapsedTime / $count) * $total : 0;
                    $remainingTime = $estimatedTotalTime - $elapsedTime;
                    $progressCallback($progress, $count, $total, $remainingTime);
                }
                
                // Thêm độ trễ giữa các batch để tránh rate limit
                sleep(3);
            }

            return ['success' => true, 'message' => "Đã đồng bộ giá cho $count mã cổ phiếu."];
        } catch (\Throwable $e) {
            Log::error('SyncStockPrices error', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    /**
     * Validate stock symbol to prevent command injection.
     */
    private function validateSymbol(string $symbol): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9]{1,20}$/', $symbol);
    }

    /**
     * Call Python script to get historical stock data.
     *
     * @param  string  $symbol
     * @return mixed
     */
    public function fetchStockDataFromPython(string $symbols)
    {
        // Symbols can be a single symbol or a comma-separated list
        $validatedSymbols = [];
        foreach (explode(',', $symbols) as $symbol) {
            $trimmedSymbol = trim($symbol);
            if ($this->validateSymbol($trimmedSymbol)) {
                $validatedSymbols[] = $trimmedSymbol;
            }
        }

        if (empty($validatedSymbols)) {
            return ['error' => 'Không có mã cổ phiếu hợp lệ.'];
        }
        
        $symbolString = implode(',', $validatedSymbols);

        $pythonPath = config('services.python.path', 'python');
        $scriptPath = base_path('py/get_stock.py');
        $command = escapeshellarg($pythonPath) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($symbolString);
        exec($command, $output, $returnVar);

        // Find the JSON string (usually the last line containing '{')
        $jsonStr = '';
        for ($i = count($output) - 1; $i >= 0; $i--) {
            if (strpos(trim($output[$i]), '{') === 0) {
                $jsonStr = trim($output[$i]);
                break;
            }
        }

        return json_decode($jsonStr, true) ?? ['error' => 'Lỗi khi gọi script Python'];
    }

    /**
     * Call Python script to get stock symbol list.
     *
     * @return mixed
     */
    public function fetchStockListFromPython()
    {
        $pythonPath = config('services.python.path', 'python');
        $scriptPath = base_path('py/get_stock_list.py');
        $command = escapeshellarg($pythonPath) . ' ' . escapeshellarg($scriptPath);
        exec($command, $output, $returnVar);

        $jsonStr = '';
        for ($i = count($output) - 1; $i >= 0; $i--) {
            if (strpos(trim($output[$i]), '[') === 0 || strpos(trim($output[$i]), '{') === 0) {
                $jsonStr = trim($output[$i]);
                break;
            }
        }

        return json_decode($jsonStr, true) ?? [];
    }

    /**
     * Call Python script to get hot industries data.
     *
     * @return mixed
     */
    public function fetchHotIndustriesFromPython($limit = 30)
    {
        $limit = (int) $limit;
        if ($limit < 1 || $limit > 100) {
            $limit = 30;
        }

        $pythonPath = config('services.python.path', 'python');
        $scriptPath = base_path('py/get_hot_industries.py');
        $command = escapeshellarg($pythonPath) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg((string) $limit);
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            Log::error('Python script error', [
                'output' => $output,
                'returnVar' => $returnVar,
            ]);
            return [];
        }

        $jsonStr = '';
        for ($i = count($output) - 1; $i >= 0; $i--) {
            if (strpos(trim($output[$i]), '[') === 0 || strpos(trim($output[$i]), '{') === 0) {
                $jsonStr = trim($output[$i]);
                break;
            }
        }

        return json_decode($jsonStr, true) ?? [];
    }

    /**
     * Xử lý một "chunk" các mã cổ phiếu để đồng bộ giá.
     * Được gọi từ ProcessStockPriceSync Job.
     *
     * @param array $symbolChunk
     * @return void
     * @throws \Throwable
     */
    public function processPriceSyncChunk(array $symbolChunk): void
    {
        if (empty($symbolChunk)) {
            return;
        }

        $symbolToId = \App\Models\Stock::whereIn('symbol', array_column($symbolChunk, 'symbol'))
            ->pluck('id', 'symbol')
            ->toArray();

        $symbolBatch = array_keys($symbolToId);

        if (empty($symbolBatch)) {
            return;
        }

        // Fetch data for the entire batch at once
        $batchPriceData = $this->fetchStockDataFromPython(implode(',', $symbolBatch));

        if (is_array($batchPriceData) && isset($batchPriceData['data'])) {
            $priceDataBatch = [];
            foreach ($batchPriceData['data'] as $symbol => $priceData) {
                if (!isset($symbolToId[$symbol])) continue;

                foreach ($priceData as $item) {
                    if (!isset($item['date']) && isset($item['time'])) {
                        $item['date'] = date('Y-m-d', is_numeric($item['time']) ? (int)($item['time'] / 1000) : strtotime($item['time']));
                    }
                    if (empty($item['date'])) continue;

                    $priceDataBatch[] = [
                        'stock_id' => $symbolToId[$symbol],
                        'date' => $item['date'],
                        'open' => $item['open'] ?? null,
                        'high' => $item['high'] ?? null,
                        'low' => $item['low'] ?? null,
                        'close' => $item['close'] ?? null,
                        'volume' => $item['volume'] ?? null,
                    ];
                }
            }

            if (!empty($priceDataBatch)) {
                \App\Models\StockPrice::upsert($priceDataBatch, ['stock_id', 'date'], ['open', 'high', 'low', 'close', 'volume']);
                Log::info('Successfully upserted ' . count($priceDataBatch) . ' price records for symbols: ' . implode(',', $symbolBatch));
            }
        } else {
            Log::warning("Could not fetch price data for batch in job", ['symbols' => $symbolBatch, 'response' => $batchPriceData]);
        }

        // Thêm một khoảng nghỉ nhỏ để tránh quá tải API
        sleep(5);
    }

    /**
     * Debug function to fetch raw stock list data from Python.
     *
     * @return array
     */
    public function debugFetchRawStockList(): array
    {
        $data = $this->fetchStockListFromPython();
        if (empty($data)) {
            return ['error' => 'No data returned from Python script for stock list.'];
        }
        
        // Log and return the raw data for debugging
        Log::info('Raw stock list data fetched from Python:', $data);
        return $data;
    }

    /**
     * Debug function to fetch raw stock data for a specific symbol from Python.
     *
     * @param string $symbol
     * @return array
     */
    public function debugFetchRawStockData(string $symbol): array
    {
        $data = $this->fetchStockDataFromPython($symbol);
        if (empty($data)) {
            return ['error' => "No data returned from Python script for symbol: $symbol."];
        }
        
        // Log and return the raw data for debugging
        Log::info("Raw stock data fetched from Python for symbol: $symbol", $data);
        return $data;
    }
}

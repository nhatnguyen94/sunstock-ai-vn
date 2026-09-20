<?php

/**
 * Author: Sun Nguyen
 * Email: nhat.nguyenminh94@gmail.com
 * Github: https://github.com/nhatnguyen94
 */

namespace App\Frontend\Services;

use App\Support\PythonRunner;
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
                    'symbol'     => $row['symbol'],
                    'name'       => $name,
                    'exchange'   => $row['exchange'] ?? null,
                    'industry'   => $row['industry_name'] ?? null,
                    'updated_at' => $now,
                ];
                $stockRows[] = [
                    'symbol'     => $row['symbol'],
                    'name'       => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            \App\Models\StockSymbol::upsert($symbolRows, ['symbol'], ['name', 'exchange', 'industry', 'updated_at']);
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
        return (bool) preg_match('/^[A-Za-z0-9]{1,20}\z/', $symbol);
    }

    /**
     * Call Python script to get historical stock data.
     *
     * @param  string  $symbol
     * @return mixed
     */
    public function fetchStockDataFromPython(string $symbols, int $timeoutSeconds = 280, ?string $start = null, ?string $end = null)
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

        // 280s: comfortably under ProcessStockPriceSync's own $timeout=300s — see
        // App\Support\PythonRunner for why a hard OS-level timeout is needed here.
        // A chunk (up to 20 symbols) is fetched in ONE Python call, looping per symbol
        // internally — if the data source is down for most/all of the chunk, there is
        // no value in grinding through all 20 (nothing will succeed); better to give up
        // at this ceiling and let the job's normal retry, or the next scheduled run,
        // pick it back up than tie up a worker for the chunk's full worst-case duration.
        // Optional range (Y-m-d): an incremental refresh only asks for the sessions it is missing instead of a whole
        // year. A web request passes a small $timeoutSeconds so a slow source can never hold a PHP-FPM worker for minutes.
        $args = [$symbolString];
        if ($start !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
            $args[] = $start;
            if ($end !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
                $args[] = $end;
            }
        }

        $result = PythonRunner::run(base_path('py/get_stock.py'), $args, $timeoutSeconds);

        // Find the JSON string (usually the last line containing '{')
        $jsonStr = '';
        for ($i = count($result['output']) - 1; $i >= 0; $i--) {
            if (strpos(trim($result['output'][$i]), '{') === 0) {
                $jsonStr = trim($result['output'][$i]);
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
        // 120s: single call fetching the full symbol list, should take a few seconds normally.
        $result = PythonRunner::run(base_path('py/get_stock_list.py'), [], 120);

        $jsonStr = '';
        for ($i = count($result['output']) - 1; $i >= 0; $i--) {
            if (strpos(trim($result['output'][$i]), '[') === 0 || strpos(trim($result['output'][$i]), '{') === 0) {
                $jsonStr = trim($result['output'][$i]);
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

        // 60s: single call, small dataset, should be fast normally.
        $result = PythonRunner::run(base_path('py/get_hot_industries.py'), [$limit], 60);

        if ($result['exit_code'] !== 0) {
            Log::error('Python script error', [
                'output' => $result['output'],
                'returnVar' => $result['exit_code'],
            ]);
            return [];
        }

        $jsonStr = '';
        for ($i = count($result['output']) - 1; $i >= 0; $i--) {
            if (strpos(trim($result['output'][$i]), '[') === 0 || strpos(trim($result['output'][$i]), '{') === 0) {
                $jsonStr = trim($result['output'][$i]);
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
            $stored = $this->storePriceData($batchPriceData['data'], $symbolToId);
            if ($stored > 0) {
                Log::info('Successfully upserted ' . $stored . ' price records for symbols: ' . implode(',', $symbolBatch));
            }
        } else {
            Log::warning("Could not fetch price data for batch in job", ['symbols' => $symbolBatch, 'response' => $batchPriceData]);
        }

        // Thêm một khoảng nghỉ nhỏ để tránh quá tải API
        sleep(5);
    }

    /**
     * Upsert the `data` part of get_stock.py's output ({SYMBOL: [bar, ...]}) into stock_prices.
     * A bar carries `date` (Y-m-d); older output only had the epoch-ms `time`, which is converted.
     *
     * @param array<string, array<int, array<string, mixed>>> $data
     * @param array<string, int> $symbolToId symbol => stocks.id (symbols not in here are ignored)
     * @return int rows written
     */
    public function storePriceData(array $data, array $symbolToId): int
    {
        $rows = [];

        foreach ($data as $symbol => $bars) {
            if (! isset($symbolToId[$symbol]) || ! is_array($bars)) {
                continue;
            }

            foreach ($bars as $item) {
                if (! isset($item['date']) && isset($item['time'])) {
                    $item['date'] = date('Y-m-d', is_numeric($item['time']) ? (int) ($item['time'] / 1000) : strtotime((string) $item['time']));
                }
                if (empty($item['date']) || ! isset($item['close'])) {
                    continue;
                }

                $rows[] = [
                    'stock_id' => $symbolToId[$symbol],
                    'date' => $item['date'],
                    'open' => $item['open'] ?? $item['close'],
                    'high' => $item['high'] ?? $item['close'],
                    'low' => $item['low'] ?? $item['close'],
                    'close' => $item['close'],
                    'volume' => $item['volume'] ?? 0,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            \App\Models\StockPrice::upsert($chunk, ['stock_id', 'date'], ['open', 'high', 'low', 'close', 'volume']);
        }

        return count($rows);
    }

    /**
     * Fetch and store the price history of some symbols in ONE script run (used by the stock page and its
     * background refresh; the bulk sync goes through processPriceSyncChunk()).
     *
     * @param  string[] $symbols
     * @param  ?string  $from    Y-m-d of the first session wanted (null = one year back)
     * @return array{stored: int, errors: array<string, string>}
     */
    public function refreshPrices(array $symbols, ?string $from = null, int $timeoutSeconds = 25): array
    {
        $symbols = array_values(array_unique(array_filter($symbols, fn ($s) => $this->validateSymbol((string) $s))));
        if ($symbols === []) {
            return ['stored' => 0, 'errors' => ['*' => 'Không có mã cổ phiếu hợp lệ.']];
        }

        $ids = \App\Models\Stock::whereIn('symbol', $symbols)->pluck('id', 'symbol')->all();
        $result = $this->fetchStockDataFromPython(implode(',', $symbols), $timeoutSeconds, $from);

        if (! is_array($result) || ! isset($result['data'])) {
            return ['stored' => 0, 'errors' => ['*' => is_array($result) ? (string) ($result['error'] ?? 'Không lấy được dữ liệu giá.') : 'Không lấy được dữ liệu giá.']];
        }

        return ['stored' => $this->storePriceData($result['data'], $ids), 'errors' => (array) ($result['errors'] ?? [])];
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

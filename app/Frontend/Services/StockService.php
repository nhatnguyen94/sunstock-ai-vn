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
    public function fetchStockDataFromPython($symbol)
    {
        if (!$this->validateSymbol($symbol)) {
            return ['error' => 'Mã cổ phiếu không hợp lệ.'];
        }

        $pythonPath = config('services.python.path', 'python');
        $scriptPath = base_path('py/get_stock.py');
        $command = escapeshellarg($pythonPath) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($symbol);
        exec($command, $output, $returnVar);

        // Find the JSON string (usually the last line containing '[')
        $jsonStr = '';
        for ($i = count($output) - 1; $i >= 0; $i--) {
            if (strpos(trim($output[$i]), '[') === 0 || strpos(trim($output[$i]), '{') === 0) {
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
}

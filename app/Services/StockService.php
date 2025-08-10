<?php
/**
 * Author: Sun Nguyen
 * Email: nhat.nguyenminh94@gmail.com
 * Github: https://github.com/nhatnguyen94
 */
namespace App\Services;

use Illuminate\Support\Facades\Log;

class StockService
{
    /**
     * Call Python script to get historical stock data.
     *
     * @param string $symbol
     * @return mixed
     */
    public function fetchStockDataFromPython($symbol)
    {
        $pythonPath = env('PYTHON_PATH', 'python');
        $scriptPath = base_path('py/get_stock.py');
        $command = "\"{$pythonPath}\" \"{$scriptPath}\" {$symbol}";
        exec($command, $output, $returnVar);

        $jsonStart = strpos(implode("\n", $output), '[');
        $jsonStr = $jsonStart !== false ? substr(implode("\n", $output), $jsonStart) : '';

        return json_decode($jsonStr, true) ?? ['error' => 'Lỗi khi gọi script Python'];
    }

    /**
     * Call Python script to get stock symbol list.
     *
     * @return mixed
     */
    public function fetchStockListFromPython()
    {
        $pythonPath = env('PYTHON_PATH', 'python');
        $scriptPath = base_path('py/get_stock_list.py');
        $command = "\"{$pythonPath}\" \"{$scriptPath}\"";
        exec($command, $output, $returnVar);

        $jsonStart = strpos(implode("\n", $output), '[');
        $jsonStr = $jsonStart !== false ? substr(implode("\n", $output), $jsonStart) : '';
        return json_decode($jsonStr, true) ?? [];
    }

    /**
     * Call Python script to get hot industries data.
     *
     * @return mixed
     */
    public function fetchHotIndustriesFromPython($limit = 30)
    {
        $pythonPath = env('PYTHON_PATH', 'python');
        $scriptPath = base_path('py/get_hot_industries.py');
        $command = "\"{$pythonPath}\" \"{$scriptPath}\" {$limit}";
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            Log::error('Python script error', [
                'command' => $command,
                'output' => $output,
                'returnVar' => $returnVar
            ]);
            return [];
        }

        $jsonStart = strpos(implode("\n", $output), '[');
        $jsonStr = $jsonStart !== false ? substr(implode("\n", $output), $jsonStart) : '';
        return json_decode($jsonStr, true) ?? [];
    }
}

<?php
/**
 * Author: Sun Nguyen
 * Email: nhat.nguyenminh94@gmail.com
 * Github: https://github.com/nhatnguyen94
 */
namespace App\Services;

class StockService
{
    public function fetchStockDataFromPython($symbol)
    {
        $pythonPath = env('PYTHON_PATH', 'python');
        $scriptPath = base_path('get_stock.py');
        $command = "\"{$pythonPath}\" \"{$scriptPath}\" {$symbol}";
        exec($command, $output, $returnVar);

        $jsonStart = strpos(implode("\n", $output), '[');
        $jsonStr = $jsonStart !== false ? substr(implode("\n", $output), $jsonStart) : '';

        return json_decode($jsonStr, true) ?? ['error' => 'Lỗi khi gọi script Python'];
    }

    public function fetchStockListFromPython()
    {
        $pythonPath = env('PYTHON_PATH', 'python');
        $scriptPath = base_path('get_stock_list.py');
        $command = "\"{$pythonPath}\" \"{$scriptPath}\"";
        exec($command, $output, $returnVar);

        $jsonStart = strpos(implode("\n", $output), '[');
        $jsonStr = $jsonStart !== false ? substr(implode("\n", $output), $jsonStart) : '';
        return json_decode($jsonStr, true) ?? [];
    }
}
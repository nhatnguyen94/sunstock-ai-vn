<?php
namespace App\Services;

class ExchangeRateService
{
    public function fetchRatesFromPython()
    {
        $pythonPath = env('PYTHON_PATH', 'python');
        $scriptPath = base_path('py/get_exchange_rate.py');
        $command = "\"{$pythonPath}\" \"{$scriptPath}\"";
        exec($command, $output, $returnVar);

        $jsonStart = strpos(implode("\n", $output), '[');
        $jsonStr = $jsonStart !== false ? substr(implode("\n", $output), $jsonStart) : '';
        return json_decode($jsonStr, true) ?? [];
    }
}
<?php
namespace App\Services;

use App\Interfaces\ExchangeRateRepositoryInterface;

class ExchangeRateService
{
    protected $repo;

    public function __construct(ExchangeRateRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function getLatestRates($days = 3)
    {
        return $this->repo->getLatestRates($days);
    }

    public function getRatesByDate($date)
    {
        $rates = $this->repo->getRatesByDate($date);
        if (empty($rates)) { // Sửa lại dùng empty()
            $rates = $this->fetchRatesFromPython($date);
            // Optionally: save to DB
        }
        return $rates;
    }

    public function fetchRatesFromPython($date)
    {
        $python = env('PYTHON_PATH', 'python');
        $script = base_path('py/get_exchange_rate.py');
        $command = "$python $script $date";
        exec($command, $output);
        $json = implode('', $output);
        $data = json_decode($json, true) ?? [];
        return $data;
    }
}
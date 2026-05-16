<?php

namespace App\Frontend\Services;

use App\Frontend\Interfaces\ExchangeRateRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class ExchangeRateService
{
    protected $repo;

    public function __construct(ExchangeRateRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function getLatestRates($days = 3)
    {
        return Cache::remember("exchange_rates_latest_{$days}", 1800, function () use ($days) {
            $rates = $this->repo->getLatestRates($days);
            if (empty($rates)) {
                $rates = $this->fetchRatesFromPython($days);
                // Lưu vào DB từng ngày
                foreach ($rates as $date => $items) {
                    foreach ($items as $item) {
                        $this->repo->saveRate($item);
                    }
                }
            }

            return $rates;
        });
    }

    public function getRatesByDate($date)
    {
        return Cache::remember("exchange_rates_{$date}", 1800, function () use ($date) {
            $rates = $this->repo->getRatesByDate($date);
            if (empty($rates)) {
                $ratesArr = $this->fetchRatesFromPython($date);
                // $ratesArr là [date => [item, ...]]
                foreach ($ratesArr as $items) {
                    foreach ($items as $item) {
                        $this->repo->saveRate($item);
                    }
                }
                $rates = $this->repo->getRatesByDate($date); // Lấy lại từ DB cho chắc chắn
            }

            return $rates;
        });
    }

    public function fetchRatesFromPython($daysOrDate)
    {
        // Validate input: must be numeric (days) or date format (Y-m-d)
        if (!is_numeric($daysOrDate) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $daysOrDate)) {
            return [];
        }

        $python = config('services.python.path', 'python');
        $script = base_path('py/get_exchange_rate.py');
        $command = escapeshellarg($python) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg((string) $daysOrDate);
        exec($command, $output);
        $json = implode('', $output);
        $data = json_decode($json, true) ?? [];
        // Nếu truyền days, $data là mảng các ngày, mỗi ngày có 'date' và 'rates'
        // Nếu truyền date, $data là mảng các item
        // Chuẩn hóa về [date => [item, item, ...]]
        if (is_array($data) && isset($data[0]['date']) && isset($data[0]['rates'])) {
            // Trường hợp lấy nhiều ngày
            $result = [];
            foreach ($data as $day) {
                $result[$day['date']] = $day['rates'];
            }

            return $result;
        } elseif (is_array($data) && isset($data[0]['currency_code'])) {
            // Trường hợp lấy 1 ngày
            $date = is_numeric($daysOrDate) ? now()->format('Y-m-d') : $daysOrDate;

            return [$date => $data];
        }

        return [];
    }
}

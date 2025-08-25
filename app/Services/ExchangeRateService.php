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
    }

    public function getRatesByDate($date)
    {
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
    }

    public function fetchRatesFromPython($daysOrDate)
    {
        $python = env('PYTHON_PATH', 'python');
        $script = base_path('py/get_exchange_rate.py');
        $command = "$python $script $daysOrDate";
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
<?php

namespace App\Frontend\Services;

use App\Frontend\Interfaces\ExchangeRateRepositoryInterface;
use App\Support\PythonRunner;
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

        // 30s: called from a live web request (ExchangeRateController) as well as
        // sync:exchange-rates — must fail fast rather than hang a page load or a
        // scheduled run. See App\Support\PythonRunner.
        $result = PythonRunner::run(base_path('py/get_exchange_rate.py'), [$daysOrDate], 30);

        return $this->parsePythonOutput($result['output'], $daysOrDate);
    }

    /**
     * Parse py/get_exchange_rate.py's stdout lines into [date => [item, item, ...], ...].
     *
     * Split out from fetchRatesFromPython() so it can be unit-tested directly with
     * fixture $output arrays, without invoking a real Python process — exec() itself
     * isn't mockable. See docs/TESTING.md.
     *
     * @param  array  $output  Raw stdout lines from exec(); vnstock prints promo
     *                         banners/version notices before the JSON payload, so the
     *                         JSON line must be found by scanning backward (same
     *                         pattern as StockService/CompanyFinancialService — see
     *                         docs/PYTHON_INTEGRATION.md).
     * @param  int|string  $daysOrDate  The original argument passed to the script.
     */
    public function parsePythonOutput(array $output, $daysOrDate): array
    {
        $json = '';
        for ($i = count($output) - 1; $i >= 0; $i--) {
            $line = trim($output[$i]);
            if (str_starts_with($line, '{') || str_starts_with($line, '[')) {
                $json = $line;
                break;
            }
        }

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

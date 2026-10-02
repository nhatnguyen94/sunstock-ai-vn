<?php

namespace App\Frontend\Services;

use App\Frontend\Interfaces\ExchangeRateRepositoryInterface;
use App\Support\PythonRunner;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;

class ExchangeRateService
{
    /** How far back the date search goes. Older dates are refused without touching the cache or Python. */
    public const MAX_HISTORY_DAYS = 1095;

    protected $repo;

    /** True when the last Python call was refused by the web guard (see PythonWebGuard): that answer must not be cached. */
    protected bool $fetchWasRefused = false;

    public function __construct(ExchangeRateRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function getLatestRates($days = 3)
    {
        $key = "exchange_rates_latest_{$days}";
        if (($cached = Cache::get($key)) !== null) {
            return $cached;
        }

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

        // An empty answer (source down, or a run the web guard refused) is not remembered: the page would stay empty for
        // half an hour even after the source is back
        if (! empty($rates)) {
            Cache::put($key, $rates, 1800);
        }

        return $rates;
    }

    /**
     * Is this a real calendar date (Y-m-d), not in the future and not older than MAX_HISTORY_DAYS?
     * Anything else must never become a cache key or a Python argument: the search form is public.
     */
    public static function isSearchableDate(mixed $date): bool
    {
        if (! is_string($date) || preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date) !== 1) {
            return false;
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            return false;   // "2026-02-31" and friends
        }

        return $date <= now()->toDateString() && $date >= now()->subDays(self::MAX_HISTORY_DAYS)->toDateString();
    }

    public function getRatesByDate($date)
    {
        if (! self::isSearchableDate($date)) {
            return [];
        }

        $key = "exchange_rates_{$date}";
        if (($cached = Cache::get($key)) !== null) {
            return $cached;
        }

        $rates = $this->repo->getRatesByDate($date);
        if (empty($rates)) {
            $this->fetchWasRefused = false;
            $ratesArr = $this->fetchRatesFromPython($date);
            // $ratesArr là [date => [item, ...]]
            foreach ($ratesArr as $items) {
                foreach ($items as $item) {
                    $this->repo->saveRate($item);
                }
            }
            $rates = $this->repo->getRatesByDate($date); // Lấy lại từ DB cho chắc chắn
        }

        // "No rates that day" is a real answer worth remembering (weekends), but a run the web guard refused is not:
        // caching it would hide that day for half an hour
        if (! $this->fetchWasRefused) {
            Cache::put($key, $rates, 1800);
        }

        return $rates;
    }

    public function fetchRatesFromPython($daysOrDate)
    {
        // Validate input: must be numeric (days) or date format (Y-m-d)
        if (! is_numeric($daysOrDate) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $daysOrDate)) {
            return [];
        }

        // 30s: called from a live web request (ExchangeRateController) as well as
        // sync:exchange-rates — must fail fast rather than hang a page load or a
        // scheduled run. See App\Support\PythonRunner.
        $result = PythonRunner::run(base_path('py/get_exchange_rate.py'), [$daysOrDate], 30);
        $this->fetchWasRefused = (bool) ($result['refused'] ?? false);

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

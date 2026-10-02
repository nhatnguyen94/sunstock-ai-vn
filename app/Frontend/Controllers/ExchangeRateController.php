<?php

namespace App\Frontend\Controllers;

use App\Frontend\Services\ExchangeRateService;
use Illuminate\Http\Request;

class ExchangeRateController extends Controller
{
    protected $service;

    public function __construct(ExchangeRateService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $rates = $this->service->getLatestRates(3);

        return view('exchange_rate.index', compact('rates'));
    }

    public function search(Request $request)
    {
        $date = $request->input('date');
        $searchRates = [];
        $searchError = null;
        if ($date !== null && $date !== '' && ! ExchangeRateService::isSearchableDate($date)) {
            // Never reaches the cache or Python: only a real date, not in the future, within the last 3 years
            $searchError = 'Ngày không hợp lệ. Hãy chọn một ngày trong 3 năm gần đây, không phải ngày trong tương lai.';
            $date = null;
        }
        if ($date) {
            $searchRates = $this->service->getRatesByDate($date);
            // Nếu $searchRates là mảng phẳng, wrap lại cho view
            if (! empty($searchRates) && isset($searchRates[0]['currency_code'])) {
                $searchRates = [$date => $searchRates];
            }
        }
        $rates = $this->service->getLatestRates(3);

        return view('exchange_rate.index', compact('rates', 'searchRates', 'date', 'searchError'));
    }
}

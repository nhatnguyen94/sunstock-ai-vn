<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ExchangeRateService;

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
        if ($date) {
            $searchRates = $this->service->getRatesByDate($date);
        }
        $rates = $this->service->getLatestRates(3);
        return view('exchange_rate.index', compact('rates', 'searchRates', 'date'));
    }
}
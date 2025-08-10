<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Repositories\ExchangeRateRepositoryInterface;

class ExchangeRateController extends Controller
{
    protected $repo;

    public function __construct(ExchangeRateRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function index()
    {
        $rates = $this->repo->getLatestRates(3);
        if (empty($rates)) {
            $this->repo->updateRatesFromPython();
            $rates = $this->repo->getLatestRates(3);
        }
        return view('exchange_rate.index', compact('rates'));
    }
}
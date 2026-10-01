<?php

namespace App\Frontend\Interfaces;
use App\Models\ExchangeRate;

interface ExchangeRateRepositoryInterface
{
    public function getLatestRates($days = 3);

    /** Newest stored rate for one currency code (e.g. 'USD'), or null. */
    public function getLatestRate(string $currencyCode): ?ExchangeRate;

    public function getRatesByDate($date);

    public function saveRate($item);
}

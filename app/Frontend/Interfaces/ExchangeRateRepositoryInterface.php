<?php

namespace App\Frontend\Interfaces;

use App\Models\ExchangeRate;

interface ExchangeRateRepositoryInterface
{
    public function getLatestRates($days = 3);

    /** Newest stored rate for one currency code (e.g. 'USD'), or null. */
    public function getLatestRate(string $currencyCode): ?ExchangeRate;

    /** The newest stored rate for a currency dated BEFORE `$date` (Y-m-d): what "yesterday" was, for a change figure. */
    public function getRateBefore(string $currencyCode, string $date): ?ExchangeRate;

    public function getRatesByDate($date);

    public function saveRate($item);
}

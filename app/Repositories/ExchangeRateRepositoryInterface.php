<?php
namespace App\Repositories;

interface ExchangeRateRepositoryInterface
{
    public function getLatestRates(int $days = 3): array;
    public function updateRatesFromPython(): void;
}
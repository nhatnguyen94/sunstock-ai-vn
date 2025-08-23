<?php
namespace App\Interfaces;

interface ExchangeRateRepositoryInterface
{
    public function getLatestRates($days = 3);
    public function getRatesByDate($date);
}
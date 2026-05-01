<?php
namespace App\Frontend\Interfaces;

interface ExchangeRateRepositoryInterface
{
    public function getLatestRates($days = 3);
    public function getRatesByDate($date);
    public function saveRate($item);
}
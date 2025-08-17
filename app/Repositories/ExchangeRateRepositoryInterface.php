<?php
namespace App\Repositories;

interface ExchangeRateRepositoryInterface
{
    public function getLatestRates($days = 3);
    public function getRatesByDate($date);
}
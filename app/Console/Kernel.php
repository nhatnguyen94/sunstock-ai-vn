<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\SyncStockData::class,
        \App\Console\Commands\SyncStockPrices::class,
        \App\Console\Commands\RegisterVnstockApiKey::class,
        \App\Console\Commands\SyncHotIndustries::class,
        \App\Console\Commands\SyncExchangeRates::class,
        \App\Console\Commands\GeneratePriceSummaries::class,
        \App\Console\Commands\BackfillStockPrices::class,
        \App\Console\Commands\SyncNews::class,
        // SyncCompanyFinancials is auto-discovered via $this->load() below
    ];

    /**
     * Define the application's command schedule.
     *
     * To activate the scheduler on Windows (XAMPP), add a Task Scheduler entry:
     *   Program: php
     *   Arguments: C:\xampp\htdocs\stock-app\artisan schedule:run
     *   Trigger: Every 1 minute
     *
     * Or run once manually: php artisan schedule:work
     */
    protected function schedule(Schedule $schedule): void
    {
        // Pre-populate exchange rates before users visit (Vietcombank updates ~7-8 AM)
        $schedule->command('sync:exchange-rates')->dailyAt('07:30')
            ->withoutOverlapping()
            ->runInBackground();

        // Refresh hot industries (company listings rarely change, daily is enough)
        $schedule->command('sync:hot-industries --limit=100')->dailyAt('07:45')
            ->withoutOverlapping()
            ->runInBackground();

        // Sync stock symbols list weekly (new listings are rare)
        $schedule->command('sync:stock-data')->weeklyOn(1, '07:00')
            ->withoutOverlapping()
            ->runInBackground();

        // Sync stock prices daily after VN market closes (~3 PM = 15:00 ICT)
        $schedule->command('sync:stock-prices')->dailyAt('15:30')
            ->withoutOverlapping()
            ->runInBackground();

        // Sync company financials weekly (quarterly reports don't change often)
        $schedule->command('sync:company-financials')->weeklyOn(1, '08:00')
            ->withoutOverlapping()
            ->runInBackground();

        // Crawl RSS news from all sources every 30 minutes
        $schedule->command('sync:news')->everyThirtyMinutes()
            ->withoutOverlapping()
            ->runInBackground();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}

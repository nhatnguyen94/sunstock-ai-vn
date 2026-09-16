<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Đăng ký middleware AdminAccess với alias 'admin'
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminAccess::class,
        ]);

        // auth:web runs before the 'admin' alias on /admin/* routes, so an
        // unauthenticated guest was always redirected to the default /login
        // (Laravel's built-in guest redirect target) instead of /admin/login
        // — AdminAccess's own guest branch never got a chance to run.
        $middleware->redirectGuestsTo(
            fn ($request) => $request->is('admin*') ? route('admin.login') : route('login')
        );
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Pre-populate exchange rates before users visit (Vietcombank updates ~7-8 AM)
        $schedule->command('sync:exchange-rates')->dailyAt('07:30')
            ->withoutOverlapping()
            ->runInBackground();

        // Refresh hot industries daily
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

        // Refresh portfolio current_price + send target/stop-loss alerts.
        // Runs 30 min after sync:stock-prices to give the queue workers time
        // to finish processing the dispatched price-sync jobs.
        $schedule->command('sync:portfolio-prices')->dailyAt('16:00')
            ->withoutOverlapping()
            ->runInBackground();

        // Regenerate monthly summaries after daily price sync completes (~1 hour after)
        // Summaries power multi-year chart views without querying millions of daily rows
        $schedule->command('generate:price-summaries')->dailyAt('17:00')
            ->withoutOverlapping()
            ->runInBackground();

        // Sync company financial statements monthly (data updates quarterly/annually)
        // --stale: only re-fetch records older than 30 days to avoid unnecessary API calls
        $schedule->command('sync:company-financials --stale --limit=50')->monthlyOn(5, '02:00')
            ->withoutOverlapping()
            ->runInBackground();

        // Crawl RSS news from all sources every 30 minutes
        $schedule->command('sync:news')->everyThirtyMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Keep queue_job_logs (Admin > Giám sát Queue) from growing forever,
        // and un-stuck any "processing" row left behind by a crashed worker
        $schedule->command('queue-logs:prune')->hourly()
            ->withoutOverlapping()
            ->runInBackground();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

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

        // Weekly database backup (zip beside the project, see App\Support\DatabaseBackup). Checked hourly because the
        // stack may be off at any fixed time: the command itself is a cheap no-op while a backup from the last 7 days exists.
        $schedule->command('db:backup')->hourly()
            ->timezone('Asia/Ho_Chi_Minh')
            ->withoutOverlapping(120)
            ->runInBackground();

        // Market overview (indices, breadth, liquidity, movers, a quote per symbol): one ~4 s KBS call. Every 5 minutes
        // through the session, plus a closing snapshot after the ATC auction and one in the evening for late data.
        $schedule->command('sync:market-overview')->weekdays()->everyFiveMinutes()
            ->timezone('Asia/Ho_Chi_Minh')->between('9:00', '15:10')
            ->withoutOverlapping()
            ->runInBackground();
        $schedule->command('sync:market-overview')->weekdays()->dailyAt('18:00')
            ->timezone('Asia/Ho_Chi_Minh')
            ->runInBackground();

        // Gold/silver (SJC + BTMC) and world gold: quotes change through the day and there is no free history
        // API, so we snapshot every 15 minutes during Vietnam business hours to build our own.
        $schedule->command('sync:gold-prices')->everyFifteenMinutes()
            ->timezone('Asia/Ho_Chi_Minh')->between('7:00', '19:00')
            ->withoutOverlapping()
            ->runInBackground();

        // Open-ended fund catalog: Fmarket publishes NAV in the evening, one call refreshes all funds
        $schedule->command('sync:funds')->dailyAt('18:30')
            ->withoutOverlapping()
            ->runInBackground();

        // Company profiles are cached on demand (first page view). Weekly, refresh the ones that went
        // stale and warm up to 50 not-yet-cached stocks; the queue fans it out.
        $schedule->command('sync:company-profiles --seed --limit=50 --dispatch')->weeklyOn(0, '03:00')
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

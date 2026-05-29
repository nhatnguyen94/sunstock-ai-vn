@echo off
cd /d C:\xampp\htdocs\stock-app

echo ========================================
echo  Stock App - Sync + Background Workers
echo ========================================
echo.

echo [1/8] Syncing exchange rates...
php artisan sync:exchange-rates
echo Done.
echo.

echo [2/8] Syncing hot industries...
php artisan sync:hot-industries --limit=100
echo Done.
echo.

echo [3/8] Starting 6 Queue Workers in parallel...
echo   (timeout=600 supports both normal sync and long backfill jobs)
start "Queue Worker 1" cmd /k "cd /d C:\xampp\htdocs\stock-app && php artisan queue:work --timeout=600 --tries=2 --sleep=3"
start "Queue Worker 2" cmd /k "cd /d C:\xampp\htdocs\stock-app && php artisan queue:work --timeout=600 --tries=2 --sleep=3"
start "Queue Worker 3" cmd /k "cd /d C:\xampp\htdocs\stock-app && php artisan queue:work --timeout=600 --tries=2 --sleep=3"
start "Queue Worker 4" cmd /k "cd /d C:\xampp\htdocs\stock-app && php artisan queue:work --timeout=600 --tries=2 --sleep=3"
start "Queue Worker 5" cmd /k "cd /d C:\xampp\htdocs\stock-app && php artisan queue:work --timeout=600 --tries=2 --sleep=3"
start "Queue Worker 6" cmd /k "cd /d C:\xampp\htdocs\stock-app && php artisan queue:work --timeout=600 --tries=2 --sleep=3"
echo Done.
echo.

echo [4/8] Dispatching company financial sync jobs (top 50 stocks, stale-only)...
echo   6 workers will process in parallel (~3-5 min vs ~30 min sequential).
php artisan sync:company-financials --stale --limit=50 --dispatch
echo Jobs dispatched.
echo.

echo [5/8] Dispatching daily stock price sync jobs (chunk-size=10)...
php artisan sync:stock-prices --chunk-size=10
echo Jobs dispatched - 5 Workers processing in parallel.
echo.

echo [6/8] Dispatching BACKFILL jobs (all stocks, from 2019-01-01)...
echo   Workers will process them in background. No duplicate risk - upsert is idempotent.
php artisan backfill:stock-prices --start=2019-01-01 --chunk-size=5 --dispatch
echo Backfill jobs dispatched.
echo.

echo [7/8] Starting Laravel Scheduler (background)...
start "Laravel Scheduler" cmd /k "cd /d C:\xampp\htdocs\stock-app && php artisan schedule:work"
echo Done.
echo.

echo ========================================
echo  All done! Windows opened:
echo   - "Queue Worker 1..6" : 6 parallel workers
echo       * Daily sync jobs  : ~3-4 min
echo       * Backfill jobs    : ~60-90 min (background, ~300 jobs)
echo   - "Laravel Scheduler"  : auto-sync daily at 07:30 and 15:30
echo.
echo  Steps run synchronously:
echo   [1] Exchange rates, [2] Hot industries
echo   [3] Queue workers started
echo   [4] Company financials dispatched (parallel, stale-only)
echo   [5] Daily price sync dispatched, [6] Backfill dispatched
echo   [7] Scheduler started
echo.
echo  Keep all windows open while using the app.
echo ========================================
echo.
pause

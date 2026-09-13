#!/bin/sh
# Runs once whenever the scheduler container starts (i.e. whenever `docker compose up`
# is run), so data is fresh even if the stack was off during the cron windows in
# bootstrap/app.php ->withSchedule(). Each command below already skips work that is
# still fresh (see docs/GUIDELINES.md "Scheduling"), so this is safe to re-run anytime.
# After the startup pass, it hands off to the normal Laravel scheduler loop.

cd /var/www/html || exit 1

echo "[scheduler-entrypoint] Running startup data sync..."

php artisan sync:stock-data              || echo "[scheduler-entrypoint] sync:stock-data failed, continuing"
php artisan sync:exchange-rates          || echo "[scheduler-entrypoint] sync:exchange-rates failed, continuing"
php artisan sync:hot-industries --limit=100 || echo "[scheduler-entrypoint] sync:hot-industries failed, continuing"
php artisan sync:news                    || echo "[scheduler-entrypoint] sync:news failed, continuing"
php artisan sync:stock-prices            || echo "[scheduler-entrypoint] sync:stock-prices failed, continuing"
php artisan generate:price-summaries     || echo "[scheduler-entrypoint] generate:price-summaries failed, continuing"
php artisan sync:company-financials --stale --dispatch --limit=50 || echo "[scheduler-entrypoint] sync:company-financials failed, continuing"

echo "[scheduler-entrypoint] Startup sync dispatched. Starting scheduler loop..."

exec php artisan schedule:work

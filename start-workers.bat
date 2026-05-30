@echo off
cd /d C:\xampp\htdocs\stock-app

echo =========================================================
echo  Stock App - Manual Sync (Docker)
echo  Queue workers va Scheduler chay tu dong trong Docker.
echo  File nay chi dung khi can sync thu cong.
echo =========================================================
echo.

echo Kiem tra Docker containers dang chay...
docker compose ps --format "table {{.Name}}\t{{.Status}}"
echo.

echo [1/4] Syncing exchange rates...
docker compose exec php php artisan sync:exchange-rates
echo Done.
echo.

echo [2/4] Syncing hot industries...
docker compose exec php php artisan sync:hot-industries --limit=100
echo Done.
echo.

echo [3/4] Dispatching daily stock price sync jobs...
docker compose exec php php artisan sync:stock-prices --chunk-size=10
echo Jobs dispatched - 6 workers dang xu ly song song (~4-5 phut).
echo.

echo [4/4] Dispatching BACKFILL jobs (all stocks, from 2019-01-01)...
echo   Upsert idempotent - an toan neu chay lai.
docker compose exec php php artisan backfill:stock-prices --start=2019-01-01 --chunk-size=5 --dispatch
echo Backfill jobs dispatched - workers xu ly trong nen (~60-90 phut).
echo.

echo =========================================================
echo  Xong! Tat ca jobs da duoc dua vao hang doi Redis.
echo  6 queue workers trong container "queue" tu xu ly.
echo.
echo  Theo doi tien do:
echo    docker compose logs -f queue
echo    docker compose exec php php artisan queue:monitor
echo =========================================================
echo.
pause

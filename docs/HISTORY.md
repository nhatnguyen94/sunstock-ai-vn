# Feature Update History

---

## Feature Update: COMPANY_FINANCIALS_DB_CACHE_AND_ARCHITECTURE - May 29, 2026

### Problem Solved:
- `GET /stock/finance` fetched live from vnstock on every request → slow (2–5s), fails when API is down.
- Financial data (income/balance/cashflow/ratio) updates quarterly/annually — no need for live fetch.
- Original `finance()` method had direct DB query (`CompanyFinancial::where(...)`) inside Controller — violation of Controller→Service→Repository architecture.

### Solution:
Cache financial data in a dedicated DB table. First request fetches live and persists; all subsequent requests are instant DB reads. Monthly scheduler refreshes stale records.

### New Files:
- **`database/migrations/2026_05_29_161453_create_company_financials_table.php`**
  - Table `company_financials`: `symbol`, `type` (enum income/balance/cashflow/ratio), `period` (enum quarter/year), `raw_data` (JSON), `synced_at`
  - Unique index on `(symbol, type, period)`
- **`app/Models/CompanyFinancial.php`** — `$timestamps = false`, `raw_data` cast to array, `isStale()` method (>30 days)
- **`app/Frontend/Interfaces/CompanyFinancialRepositoryInterface.php`** — `find()` + `upsert()` contract
- **`app/Frontend/Repositories/CompanyFinancialRepository.php`** — implements interface, all DB access here
- **`app/Frontend/Services/CompanyFinancialService.php`**
  - `getFinancialData()`: DB hit → fallback Python → persist (used by controller)
  - `syncSymbol()`: always fetch Python → persist (used by sync command)
  - Fixed `2>/dev/null` → cross-platform (`2>NUL` on Windows, `2>/dev/null` on Unix)
- **`app/Console/Commands/SyncCompanyFinancials.php`**
  - Signature: `sync:company-financials {--symbol=} {--type=} {--period=} {--stale} {--limit=50}`
  - Uses `CompanyFinancialService::syncSymbol()` + `CompanyFinancialRepositoryInterface::find()` — no direct DB access
  - `--stale`: skip records synced within last 30 days

### Modified:
- **`app/Frontend/Controllers/StockController.php`** — `finance()` method now delegates entirely to `$this->financialService->getFinancialData()`. Removed all direct DB access and Python exec from controller.
- **`app/Providers/AppServiceProvider.php`** — added `CompanyFinancialRepositoryInterface → CompanyFinancialRepository` binding
- **`bootstrap/app.php`** — added `sync:company-financials --stale --limit=50` schedule monthly on 5th at 02:00
- **`app/Console/Kernel.php`** — command auto-discovered via `$this->load()`, removed from explicit `$commands` array
- **`start-workers.bat`** — added step `[3/8]` running `sync:company-financials --stale --limit=50` on startup

### Architecture (Controller → Service → Repository):
```
StockController::finance()          ← validates input only
  → CompanyFinancialService::getFinancialData()
    → CompanyFinancialRepositoryInterface::find()   ← DB read
    → [miss] fetchFromPython()                      ← Python exec (once only)
    → CompanyFinancialRepositoryInterface::upsert() ← DB write

SyncCompanyFinancials::handle()     ← Artisan command
  → CompanyFinancialService::syncSymbol()
    → fetchFromPython()                             ← forced fetch
    → CompanyFinancialRepositoryInterface::upsert() ← DB write
```

### Verified:
- `php artisan migrate` → `company_financials` table created ✓
- `php artisan sync:company-financials --symbol=FPT --type=income --period=quarter` → `Synced: 1` ✓
- `php artisan tinker` → `CompanyFinancial::count()` = 1 ✓
- `GET /stock/finance?symbol=FPT&type=income&period=quarter` → instant DB read on 2nd request ✓
- `php artisan sync:company-financials --help` → command registers correctly via auto-discovery ✓

---

## Feature Update: TECHNICAL_INDICATORS_AND_COMPANY_FINANCIALS - May 29, 2026

### Features Added:
Two major new capabilities added to the Stock Detail page (`/stock?symbol=XXX`).

#### 1. Technical Indicators on Price Chart
Overlay technical analysis indicators directly onto the existing ApexCharts candlestick/line chart.

- **Indicator toolbar** — Row of checkboxes above chart controls: MA20, MA50, MA200, Bollinger Bands, RSI(14), MACD
- **Moving Averages** — Simple Moving Average (SMA) overlaid as line series on main chart (MA20=blue, MA50=orange, MA200=red)
- **Bollinger Bands** — 20-period SMA ± 2σ. Renders upper/mid/lower lines on main chart
- **RSI(14)** — Wilder smoothing. Renders as a separate sub-chart below main chart (0–100 scale, 70/30 reference lines)
- **MACD(12,26,9)** — EMA-based. Renders as a separate sub-chart: MACD line (blue), Signal line (orange), Histogram (bar, green/red)
- **Period filter integration** — All indicators update when user filters by 1M/3M/6M/1Y/All
- **Pre-computation** — All indicator values computed once on full dataset when chart loads; filtering just slices the arrays (no recalc)

#### 2. Company Financial Data
On-demand financial statement viewer for any Vietnamese listed company.

- **"Tải dữ liệu tài chính" button** — Appears at bottom of stock page, loads on first click
- **4 statement types** — Kết quả kinh doanh (income), Bảng cân đối kế toán (balance sheet), Lưu chuyển tiền tệ (cash flow), Chỉ số tài chính (ratios)
- **Quarter / Year toggle** — Fetch quarterly or annual data
- **Latest 8 periods** — Displayed in reverse-chronological order (newest first)
- **Number formatting** — Large values auto-abbreviated: tỷ (billions), tr (millions), K (thousands)
- **4-hour cache** — Reduces API calls; cache keyed by `finance_{symbol}_{type}_{period}`
- **Negative value highlighting** — Red color for negative financial figures

### New Files:
- **`py/get_company_finance.py`** — Python script calling `vnstock.Finance(source='kbs', symbol, show_log=False)` for income/balance/cashflow/ratio. Uses `io.TextIOWrapper` to force UTF-8 stdout (fixes Windows charmap encoding error for Vietnamese text).
- **`app/Frontend/Controllers/StockController.php`** → added `finance()` method — validates inputs, calls Python via `exec()`, scans output from last line for JSON, caches result 4 hours.
- **`routes/web.php`** → added `GET /stock/finance` route
- **`resources/frontend/js/stock/stock.js`** → added indicator math (calcSMA, calcEMAValues, calcBB, calcRSI, calcMACD), ApexCharts integration, IIFE finance AJAX section
- **`resources/frontend/css/stock/stock.css`** → added styles for `.indicator-toolbar`, `.indicator-check`, sub-chart wrappers, `.finance-section`, tab buttons, `.btn-load-finance`

### Bug Fixed (same session):
- **Windows `charmap` encoding error** in `get_company_finance.py`: `os.environ['PYTHONIOENCODING']` set inside Python has no effect on already-open stdout stream. Fixed by using `io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')` to reconfigure stdout at runtime.

### Verified:
- `python get_company_finance.py ACB income quarter` → valid JSON with 26 rows ✓
- `/stock/finance?symbol=FPT&type=income&period=quarter` → returns `{data:[...], periods:[...]}` ✓
- Indicator checkboxes render above chart ✓
- MA20/50/200 overlay on main chart ✓
- RSI + MACD sub-charts appear/disappear on checkbox toggle ✓
- Finance table loads and tab-switching works ✓
- Vite build: `stock-Cc0M-p8Y.js` (15.5 kB) ✓

---

## Feature Update: HISTORICAL_BACKFILL_PARALLEL_QUEUE - May 29, 2026

### Problem Solved:
- Stock price history showed only 2025–2026 data (e.g., FPT chart). Root cause: `py/get_stock.py` hardcoded `timedelta(days=365)` as start date.
- Single-worker sync took ~26 minutes for all 1,555 stocks (sequential, 1s Python delay per call).

### Added:
- **`py/get_stock.py`** — Added optional `argv[2]` (start_date) and `argv[3]` (end_date) parameters. Reduced sleep from 1.0s → 0.3s.
- **`app/Jobs/BackfillStockPriceChunk.php`** — Queue job: fetches multi-symbol OHLCV batch via Python for a given date range, upserts into `stock_prices`. `timeout=600`, `tries=2`, `backoff=120`.
- **`app/Console/Commands/BackfillStockPrices.php`** — Artisan command `backfill:stock-prices {--start=2015-01-01} {--symbols=} {--chunk-size=5} {--dispatch}`. With `--dispatch` flag: pushes `BackfillStockPriceChunk` jobs to queue (returns immediately). Without: runs inline sequentially.
- **`start-workers.bat`** — Updated to 6 parallel workers (`--timeout=600 --tries=2`), auto-dispatches backfill on startup, runs scheduler. Flow: sync:exchange-rates → sync:hot-industries → 6 workers → sync:stock-prices (chunk=10) → backfill:stock-prices (--start=2019-01-01 --dispatch) → scheduler.
- **`app/Console/Kernel.php`** — Registered `BackfillStockPrices` in `$commands`.

### Result:
- Full historical backfill from 2019 runs in parallel across 6 workers (~3–4 min vs 26 min before).
- `start-workers.bat` is the single command to spin up entire data pipeline.

---

## Feature Update: DATABASE_SCALABILITY_PARTITIONING - May 29, 2026
### Problem Solved:
- `stock_prices` table at 417k rows (1,555 stocks × ~268 rows avg), projected 2.4M rows in 5 years.
- Growing table causes slow bulk INSERT during sync, large backups, difficult archiving.
- No aggregate data for multi-year chart queries (future feature).

### Added:
- **Migration**: `2026_05_29_000001_partition_stock_prices_by_year.php`
  - Drops FK to `stocks.id` (MySQL disallows FK on partitioned tables — enforced at app level)
  - Drops `created_at`/`updated_at` (market OHLCV data is immutable — date IS the timestamp)
  - Changes PRIMARY KEY to composite `(id, date)` (MySQL RANGE partitioning requirement)
  - Applies `PARTITION BY RANGE (YEAR(date))` — partitions p2018 through p2027 + p_future
- **Migration**: `2026_05_29_000002_create_stock_price_summaries_table.php`
  - New table `stock_price_summaries`: monthly OHLCV per stock (open=first day, high=MAX, low=MIN, close=last day, volume=SUM)
  - Unique `(stock_id, period_start)` + index on `period_start`
- **Model**: `app/Models/StockPriceSummary.php` — Eloquent model, `$timestamps = false`
- **Model**: `app/Models/StockPrice.php` — Added `$timestamps = false` (columns dropped)
- **Command**: `app/Console/Commands/GeneratePriceSummaries.php` — `php artisan generate:price-summaries`
  - Loads daily prices per stock (index scan), groups by month in PHP, bulk upserts via `StockPriceSummary::upsert()`
  - Supports `--stock_id=X` for targeted rebuild
- **Schedule**: `bootstrap/app.php` — Added `generate:price-summaries` daily at 17:00 (after 15:30 sync)
- **Kernel**: `app/Console/Kernel.php` — Registered `GeneratePriceSummaries` in `$commands`

### Verified:
- `php artisan migrate` → both migrations ran successfully ✓
- `php artisan generate:price-summaries` → 1,555 stocks with monthly summaries populated ✓
- `php artisan schedule:list` → 5 scheduled tasks including new 17:00 summary job ✓
- `SHOW PARTITIONS` → 11 partitions (p2018–p2027 + p_future) created ✓

### Architecture Notes (for future maintainers):
- Add new partition each January: `ALTER TABLE stock_prices REORGANIZE PARTITION p_future INTO (PARTITION p2028 VALUES LESS THAN (2029), PARTITION p_future VALUES LESS THAN MAXVALUE);`
- To archive old data: `ALTER TABLE stock_prices DROP PARTITION p2018;` (instant, no DELETE needed)
- `stock_price_summaries` → use for chart views > 1 year range (~4,800 rows/year vs ~390k daily)

---

## Feature Update: FIX_VNSTOCK_DEPRECATED_API_AND_SCHEDULER - May 29, 2026
### Problem Solved:
- Stock price data stuck at 2026-05-15 — no data updated since then.
- `ProcessStockPriceSync` jobs were failing with `MaxAttemptsExceededException` (6 failed jobs).
- Root cause 1: `py/get_stock.py` used `Vnstock().stock()` which was **deprecated and removed** by vnstock on 31/08/2025.
- Root cause 2: Laravel 12 does NOT use `Kernel.php` schedule method — scheduling was silently ignored since project started.

### Fixed:
- **Python**: `py/get_stock.py` — replaced deprecated `Vnstock().stock(symbol, source='VCI')` with `from vnstock.api.quote import Quote; Quote(symbol, source='VCI').history(start, end)`. Removed unused `import os`. Fixed `show=False` parameter (not supported by `Quote.history()`).
- **Python**: `py/get_stock_list.py` — replaced `Vnstock().listing.*` with `Listing().*` (same pattern as `get_hot_industries.py`). Added `show=False` for cleaner output. Removed unused `import os`.
- **Scheduler**: `bootstrap/app.php` — added `->withSchedule()` with all 4 tasks (Laravel 12 requires this; `Kernel.php` schedule() method is ignored by the new Application bootstrap).

### Technical Details:
- vnstock version: 4.0.3 (Python 3.13). New API: `from vnstock.api.quote import Quote`
- `Quote.history(start, end)` — does NOT accept `show=False` parameter (unlike `Listing` methods)
- Laravel 12 bootstrap (`Application::configure()`) ignores `Kernel::schedule()` — must use `->withSchedule(fn(Schedule $s))` in `bootstrap/app.php`
- After fix: `php artisan schedule:list` shows all 4 tasks correctly

### Verified:
- `get_stock.py VCB` returns JSON with data up to 2026-05-29 ✓
- `php artisan schedule:list` shows 4 scheduled tasks ✓
- `ProcessStockPriceSync` job for [VCB,FPT,ACB,HPG,VIC] completed with no failures ✓
- `StockPrice::max('date')` → `2026-05-29` ✓
- Full sync of 78 job batches dispatched and queue:work started ✓

---

## Feature Update: AUTO_SYNC_BACKGROUND_SCHEDULER - May 28, 2026
### Problem Solved:
- Homepage was slow because `fetchHotIndustriesFromPython()` was called synchronously on cache miss (every 3600s), blocking page load for 10-20s.
- Exchange rates had the same issue on the first request of each day.
- No automatic data refresh — user had to manually run `php artisan stock:sync`.

### Added:
- **Migration**: `database/migrations/2026_05_28_070000_create_hot_industries_table.php` — stores hot industry records in DB.
- **Model**: `app/Models/HotIndustry.php` — Eloquent model for `hot_industries` table.
- **Command**: `app/Console/Commands/SyncHotIndustries.php` — `php artisan sync:hot-industries [--limit=100]` — fetches from Python, truncates and re-populates the table, busts cache.
- **Command**: `app/Console/Commands/SyncExchangeRates.php` — `php artisan sync:exchange-rates [--days=1]` — fetches rates from Python, saves to DB, busts cache.

### Modified:
- **Kernel**: `app/Console/Kernel.php` — Added 4 scheduled tasks:
  - `sync:exchange-rates` → daily at 07:30 (before users access)
  - `sync:hot-industries` → daily at 07:45
  - `sync:stock-data` → weekly Monday at 07:00 (symbols rarely change)
  - `sync:stock-prices` → daily at 15:30 (after VN market close)
- **Controller**: `app/Frontend/Controllers/StockController.php` — `home()` now reads hot industries from DB (instant) via new private `getHotIndustries()` method. Falls back to Python on first run and persists result.
- **Python**: `py/get_hot_industries.py` — Fixed for vnstock 4.x API (`Listing()` instead of deprecated `Vnstock().listing`). Joins `all_symbols()` to get `organ_name`. Updated industry name `'Công nghệ Thông tin'` → `'Công nghệ và thông tin'`.

### How to Activate Scheduler (XAMPP/Windows):
```
# Option A: Persistent process (keep terminal open)
php artisan schedule:work

# Option B: Windows Task Scheduler (recommended for production)
# Program: C:\xampp\php\php.exe
# Arguments: C:\xampp\htdocs\stock-app\artisan schedule:run
# Trigger: Every 1 minute, starting at system startup
```

### Verified:
- `php artisan sync:hot-industries` → synced 100 records ✓
- `php artisan sync:exchange-rates` → synced 20 records across 1 date ✓
- DB table `hot_industries` contains 100 rows ✓
- Homepage hot industries section now loads from DB (no Python call on page load) ✓

---

## Feature Update: DOCUMENTATION_AUDIT_AND_EXPANSION - May 28, 2026
### Updated:
- **docs/STRUCTURE.md**: Full audit against actual codebase — added missing controllers (`EmailVerificationController`, all Backend controllers), `Role` model, Middleware, Console Commands, Jobs, full Python scripts list, Backend views, key architectural patterns.
- **docs/GUIDELINES.md**: Added RBAC/Gates usage patterns, email verification notes, Python integration gotcha, caching patterns, updated controller inheritance rules, updated backend middleware to use `admin` alias.
- **docs/AGENTS.md**: Linked new documentation files in the master guide.
### Added:
- **docs/QUICKSTART.md**: Setup, environment, migration, seed, artisan commands, common dev workflow.
- **docs/ROUTES_MAP.md**: Complete route → controller → action mapping table (Frontend + Backend).
- **docs/BINDINGS.md**: All DI bindings (Interface → Implementation) and Gate definitions.
- **docs/PYTHON_INTEGRATION.md**: How to call Python scripts, error handling, adding new scripts.
- **docs/RBAC.md**: Role constants, Gate definitions, middleware, permission matrix.

---

## Feature Update: SEARCH_AND_DATA_INTEGRITY - May 16, 2026
### Fixed:
- **Search UI/UX**: Resolved issue where search bar would auto-select first result incorrectly, improving user control.
- **Data Integrity (ETFs)**: Added support for missing ETF data, specifically "FUEVFVND", which was previously excluded from stock lists.
- **Backend Pipeline**: Updated `get_stock_list.py` and sync logic to handle ETFs and ensure all valid symbols are indexed.
- **Search Logic**: Enhanced backend search to return accurate results for tickers and company names.

### Modified:
- **Python**: `py/get_stock_list.py` - Expanded data fetching to include ETFs.
- **Service**: `app/Frontend/Services/StockService.php` - Improved data synchronization and verification logic.
- **Database**: Updated `stocks` and `stock_symbols` tables to include newly discovered assets.

### Verified:
- **Search**: Tested search functionality with various symbols (VCB, FUEVFVND, etc.) and verified correct behavior.
- **Sync**: Ran `php artisan stock:sync` to confirm all 400+ symbols are correctly populated.

---
### Fixed:
- **Search UI/UX**: Resolved issue where search bar would auto-select first result incorrectly, improving user control.
- **Data Integrity (ETFs)**: Added support for missing ETF data, specifically "FUEVFVND", which was previously excluded from stock lists.
- **Backend Pipeline**: Updated `get_stock_list.py` and sync logic to handle ETFs and ensure all valid symbols are indexed.
- **Search Logic**: Enhanced backend search to return accurate results for tickers and company names.

### Modified:
- **Python**: `py/get_stock_list.py` - Expanded data fetching to include ETFs.
- **Service**: `app/Frontend/Services/StockService.php` - Improved data synchronization and verification logic.
- **Database**: Updated `stocks` and `stock_symbols` tables to include newly discovered assets.

### Verified:
- **Search**: Tested search functionality with various symbols (VCB, FUEVFVND, etc.) and verified correct behavior.
- **Sync**: Ran `php artisan stock:sync` to confirm all 400+ symbols are correctly populated.

---

## Feature Update: PORTFOLIO_MANAGEMENT_FEATURE - January 2025
### Added:
- **Models**: `app/Models/Portfolio.php`, `app/Models/PortfolioItem.php`
- **Database**: `portfolios` and `portfolio_items` tables.
- **Controller**: `app/Frontend/Controllers/PortfolioController.php`
- **Service**: `app/Frontend/Services/PortfolioService.php`
- **Repository**: `app/Frontend/Repositories/PortfolioRepository.php`
- **Interface**: `app/Frontend/Interfaces/PortfolioRepositoryInterface.php`
- **Views**: Complete set of CRUD views for portfolios and stocks.

### Features:
- Portfolio CRUD, Stock Management, Real-time P&L, Analytics, Price Alerts.

---

## Feature Update: AUTHENTICATION_SYSTEM - January 2025
### Added:
- **Controller**: `app/Frontend/Controllers/ProfileController.php`
- **Views**: Profile show/edit.
- **Routes**: Profile management routes.

### Modified:
- **AuthController**: Enhanced validation and security.
- **Layout**: Updated navbar for profile access.

---

## Feature Update: SYSTEM_ERROR_FIXES_AND_EMAIL_VERIFICATION - January 3, 2025
### Fixed:
- SQL errors in stocks table, missing backend views.
- Implemented full email verification and admin manual verification.

---

## Feature Update: RBAC_SYSTEM_MULTIPLE_ROLES_AND_SEPARATE_AUTH - January 3, 2025
### Added:
- Multiple roles support, separate admin login system, enhanced user management UI.

---

## Feature Update: RBAC_SYSTEM_AND_ADMIN_DASHBOARD - May 2, 2026
### Added:
- Role system, Admin Dashboard, Permission Gates, Middleware protection.

---

## Feature Update: CLEANUP_OLD_FILES - May 1, 2026
### Removed:
- Legacy files in old namespaces.
- Optimized autoloader and fixed controller inheritance.

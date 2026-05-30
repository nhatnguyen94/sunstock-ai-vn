# Feature Update History

---

## Feature Update: NEWS_CATEGORIES_FRONTEND - May 30, 2026

### Added:
- **Migration** `2026_05_30_130000_create_news_categories_table.php` — `news_categories(id, name, slug)` seeded with 4 initial categories
- **Migration** `2026_05_30_130001_refactor_news_category_to_category_id.php` — replaces `news.category` varchar with `news.category_id` FK; migrates all 260 existing rows
- **Model** `app/Models/NewsCategory.php` — fillable(name, slug), hasMany(News)
- **Updated Model** `app/Models/News.php` — `category_id` in fillable, `belongsTo(NewsCategory)`
- **`app/Frontend/Interfaces/NewsRepositoryInterface.php`** — `getLatest`, `paginate`, `getCategories`
- **`app/Frontend/Repositories/NewsRepository.php`** — reads from `news` table with category relationship
- **`app/Frontend/Controllers/NewsController.php`** — `index(?$categorySlug)`: paginated news + category sidebar
- **Routes** `/news` (news.index) and `/news/category/{slug}` (news.category)
- **`resources/views/news/index.blade.php`** — hero header, search bar, card grid, sidebar category nav, pagination
- **`resources/frontend/css/news/index.css`** — full styling (hero, grid, cards, sidebar, badges)

### Modified:
- **`app/Frontend/Interfaces/NewsServiceInterface.php`** — `getLatestNews()→Collection`, `getPaginatedNews()`, `getCategories()`
- **`app/Frontend/Services/NewsService.php`** — rewrites from RSS live-fetch to DB-backed via `NewsRepositoryInterface`
- **`app/Frontend/Controllers/StockController.php`** — `getLatestNews(6)` (was 4), returns `Collection` not array
- **`resources/views/index.blade.php`** — news section uses Eloquent model attrs (`$item->url`, `$item->image_url`, `$item->published_at`); "Xem tất cả" links to `/news`
- **`resources/views/layouts/app.blade.php`** — added "Tin tức" dropdown nav with category sub-items (queried inline)
- **Backend `NewsService`** — SOURCES now use hardcoded `category_id` (1–4), no string lookup
- **Backend `NewsRepository`** — `getCategories()` added; `paginate()` adds `category_id` filter + `with('category')` eager load
- **Backend `news/index.blade.php`** — category filter dropdown; `$item->category?->name` instead of string
- **`AppServiceProvider`** — binds `FrontendNewsRepositoryInterface → FrontendNewsRepository`

### Technical:
- 260 news rows all have `category_id` populated after migration
- DB schema: `news_categories(id,name,slug)` ← `news.category_id` FK
- Nav dropdown queries `NewsCategory::orderBy('name')` inline in layout (1 query, cached by OPcache)
- Frontend `NewsService` reads from DB (no live RSS), homepage news cached 15 min via Redis

---

## Feature Update: NEWS_RSS_SYSTEM - May 30, 2026

### Added:
- **Migration** `2026_05_30_120000_create_news_table.php` — `news` table with dedup via `url_hash` (MD5), index on `source` + `published_at`
- **Model** `app/Models/News.php` — fillable + datetime casts
- **`app/Backend/Interfaces/NewsRepositoryInterface.php`** — `paginate`, `insertNew`, `getSources`, `getLatestSyncTime`
- **`app/Backend/Interfaces/NewsServiceInterface.php`** — `listNews`, `syncFromAllSources`, `getSources`
- **`app/Backend/Repositories/NewsRepository.php`** — bulk insert with dedup, filter paginate (search/source/date range)
- **`app/Backend/Services/NewsService.php`** — crawls 5 RSS feeds (VnExpress ×2, CafeF ×2, Dân Trí ×1), parses SimpleXML, extracts images from enclosure/media:content/description
- **`app/Console/Commands/SyncNews.php`** — `php artisan sync:news`
- **Scheduler** `Kernel.php` — `sync:news` every 30 minutes
- **DI Bindings** `AppServiceProvider` — `BackendNewsRepositoryInterface → BackendNewsRepository`, `BackendNewsServiceInterface → BackendNewsService`

### Modified:
- **`app/Backend/Controllers/NewsController.php`** — replaced fake hardcoded data with proper DI injection of `NewsServiceInterface`; `index()` paginate + filter; `updateRss()` calls service and reports count
- **`resources/views/backend/news/index.blade.php`** — full rewrite: filter form (search/source/date), thumbnail + description table, source badges, pagination, spinner on sync button

### Technical:
- RSS sources: VnExpress Kinh doanh, VnExpress Chứng khoán, CafeF Thị trường, CafeF Doanh nghiệp, Dân Trí Kinh doanh
- Dedup by `url_hash = MD5(url)` — safe to re-run sync anytime
- 260 articles saved on first sync (160 + 100 after URL fix)

---

## Feature Update: STOCK_ADMIN_SERVICE_LAYER - May 30, 2026

### Problem Solved:
- `StockController` was calling `StockRepositoryInterface` directly — violating the Controller→Service→Repository pattern required by AGENTS.md.
- Business logic (symbol normalization, data transformation) was leaking into the controller.

### Solution:
- Added full Service layer for admin stock management:
  - `app/Backend/Interfaces/StockServiceInterface.php` — contract with 6 methods
  - `app/Backend/Services/StockService.php` — implements business logic (normalization, orchestration, job dispatch stub); injects `StockRepositoryInterface`
- Updated `StockController` to inject `StockServiceInterface` instead of repository directly.
- Moved `strtoupper(trim($symbol))` normalization from controller to `StockService`.
- Added guard in `updateStock()` to `unset()` exchange/industry/market_cap fields — prevents admin form from ever overwriting auto-synced data.
- Registered `BackendStockServiceInterface → BackendStockService` binding in `AppServiceProvider`.

### Modified:
- **`app/Backend/Interfaces/StockServiceInterface.php`** — NEW
- **`app/Backend/Services/StockService.php`** — NEW
- **`app/Backend/Controllers/StockController.php`** — inject service; move transforms out
- **`app/Providers/AppServiceProvider.php`** — add service binding

---



### Problem Solved:
- `/admin/stocks` threw `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'exchange' in 'field list'` — migration never ran.
- `stocks` table had redundant `exchange`, `industry`, `market_cap` columns that duplicated data already in `stock_symbols` (master reference table).
- `StockController` used no Repository pattern — direct Eloquent calls in controller violated architecture.
- `StockController.php` had a duplicate class definition causing PHP fatal error.

### Solution:
- Ran pending migration `2026_05_02_032539_add_exchange_column_to_stocks_table`.
- Created migration to add `exchange` and `industry` to `stock_symbols` table.
- Created migration to remove `exchange`, `industry`, `market_cap` from `stocks` table.
- Updated `Stock` model: removed redundant fillable fields, added `symbolInfo()` hasOne relationship to `StockSymbol`.
- Updated `StockSymbol` model: added `exchange` and `industry` to `$fillable`.
- Updated `py/get_stock_list.py` to fetch exchange and industry from vnstock `symbols_by_exchange()` / `symbols_by_industries()`.
- Updated `StockService::syncStockSymbolsAndDetails()` to persist exchange/industry to `stock_symbols`.
- Implemented full Repository pattern for Backend stocks:
  - `app/Backend/Interfaces/StockRepositoryInterface.php`
  - `app/Backend/Repositories/StockRepository.php` (eager-loads `symbolInfo`, caches exchanges 5 min)
  - Registered binding in `AppServiceProvider` (aliased to avoid collision with Frontend interface)
- Rewrote `app/Backend/Controllers/StockController.php`: constructor DI, `Gate::authorize()`, English docblocks, return type hints, all DB ops via repository.
- Created missing admin views: `create.blade.php`, `edit.blade.php`, `show.blade.php` for stocks.
- Updated `index.blade.php` to show exchange badge via `$stock->symbolInfo->exchange`.

### Modified:
- **`app/Backend/Controllers/StockController.php`** — full rewrite with Repository DI
- **`app/Backend/Interfaces/StockRepositoryInterface.php`** — NEW
- **`app/Backend/Repositories/StockRepository.php`** — NEW
- **`app/Providers/AppServiceProvider.php`** — added Backend stock binding
- **`app/Models/Stock.php`** — removed redundant fillable, added `symbolInfo()` relationship
- **`app/Models/StockSymbol.php`** — added exchange/industry to fillable
- **`py/get_stock_list.py`** — fetches exchange and industry from vnstock
- **`app/Frontend/Services/StockService.php`** — writes exchange/industry to stock_symbols
- **`resources/views/backend/stocks/index.blade.php`** — uses symbolInfo relationship
- **`resources/views/backend/stocks/create.blade.php`** — NEW
- **`resources/views/backend/stocks/edit.blade.php`** — NEW
- **`resources/views/backend/stocks/show.blade.php`** — NEW
- **`database/migrations/2026_05_30_000001_add_exchange_industry_to_stock_symbols_table.php`** — NEW
- **`database/migrations/2026_05_30_000002_remove_redundant_columns_from_stocks_table.php`** — NEW

---

## Feature Update: AI_GROQ_MIGRATION_AND_SECURITY_HARDENING - May 30, 2026

### Problem Solved:
- OpenRouter free tier extremely unstable: most model IDs returned HTTP 404 (models removed), popular models 429 rate-limited constantly.
- `AiService` had no timeout → requests hung indefinitely.
- XSS vulnerability: `data.answer` and user `msg` rendered via raw `innerHTML` without escaping.
- Prompt injection possible: no input sanitization, system prompt too weak.
- API response body logged in full → potential info leak.
- AI market prediction called live API on every click, no caching.
- Chat allowed double-send (no busy lock).

### Solution:
- Migrated AI provider from OpenRouter → **Groq** (free: 14,400 req/day, ~0.4s response time)
- Added XSS escaping for all AI output and user input in frontend
- Hardened system prompt against jailbreak/role-play/injection
- Added server-side input sanitization (control chars, null bytes)
- Cached market prediction result 2 hours per week in Redis
- Added busy lock + AbortController timeout in frontend chat

### Modified:
- **`app/Frontend/Services/AiService.php`**
  - Provider: OpenRouter → **Groq** (`https://api.groq.com/openai/v1/chat/completions`)
  - Models: `llama-3.3-70b-versatile` → `llama3-70b-8192` → `gemma2-9b-it` (fallback chain)
  - Added `->timeout(30)` to all HTTP calls
  - `predictMarket()`: result cached in Redis 2 hours (`ai_market_predict_YYYYWW`)
  - System prompt hardened: scope-limited to finance, anti-jailbreak, no HTML/script output
  - Log truncated to 300 chars (was full body)
  - 0.3s delay between fallback retries
  - Config key: `config('services.groq.key')`

- **`app/Frontend/Controllers/StockController.php`** — `aiChat()`: strip control chars (`\x00`–`\x1F`) from user input before sending to AI

- **`config/services.php`** — added `'groq' => ['key' => env('GROQ_API_KEY')]`

- **`.env`** — added `GROQ_API_KEY=...`

- **`resources/frontend/js/layouts/app.js`** — added `escapeHtml()` helper; applied to `msg` and `data.answer`; busy lock `_aiChatBusy`; `AbortController` 35s timeout; check `res.ok` before parse

- **`resources/frontend/js/index.js`** — added `_escapeHtml()` helper; applied to `data.result`; `AbortController` 40s timeout; check `res.ok`; button re-enabled on error

### Performance:
- Before (OpenRouter): 4–8s response, frequent 404/429 failures
- After (Groq): **~0.4s chat, ~1.9s predict**, stable HTTP 200

### Verified:
- Chat: `FPT là cổ phiếu gì?` → response in 0.41s ✓
- Predict: full market analysis in 1.94s ✓

---

## Feature Update: DOCKER_MIGRATION - May 30, 2026

### Problem Solved:
- XAMPP requires manual start/stop of services; no reproducible environment.
- Python venv management difficult on Windows.
- No HTTPS support locally.
- Queue workers required manual `.bat` script; scheduler not persistent.

### Solution:
Full migration to Docker Compose stack with 6 containers.

### New Files:
- **`docker-compose.yml`** — 6 containers: nginx, php (PHP-FPM 8.2 + Python venv), mysql:8.0, redis:7, queue (supervisor 6 workers), scheduler
- **`docker/php/Dockerfile`** — PHP 8.2-FPM + `python3-venv` + `pip install vnstock pandas` in `/opt/venv`
- **`docker/nginx/default.conf`** — HTTPS server block, HTTP→HTTPS redirect, fastcgi proxy to `php:9000`
- **`docker/nginx/ssl/sunstock-local.dev.pem`** + key — mkcert cert trusted by Windows/browsers, expires 2028
- **`docker/php/supervisord.conf`** — 6 `queue:work redis` workers in queue container
- **`docker/php/php.ini`** — upload_max_filesize, max_execution_time overrides
- **`.dockerignore`** — excludes node_modules, vendor, storage logs, docker/nginx/ssl
- **`.env`** — updated: `DB_HOST=mysql`, `REDIS_HOST=redis`, `APP_URL=https://sunstock-local.dev`, `PYTHON_PATH=/opt/venv/bin/python3`
- **`.env.xampp`** — backup of original XAMPP config
- **`docs/DOCKER.md`** — comprehensive guide: setup, daily commands, exec into containers, MySQL Workbench, troubleshooting, production notes

### Stack Details:
| Container | Image | Host Port |
|---|---|---|
| nginx | nginx:1.27-alpine | 80, 443 |
| php | stock-app-php (custom) | — |
| mysql | mysql:8.0 | 3307 |
| redis | redis:7-alpine | — |
| queue | stock-app-php | — |
| scheduler | stock-app-php | — |

### Access:
- **App**: `https://sunstock-local.dev` (add `127.0.0.1 sunstock-local.dev` to `hosts` file)
- **MySQL Workbench**: host `127.0.0.1`, port `3307`, user `root`, password `<DB_PASSWORD từ .env>`

### Common Commands:
```bash
docker compose up -d                           # Start all containers
docker compose down                            # Stop (data volumes persist)
docker exec -it stock-app-php-1 bash          # Shell into PHP container
docker exec stock-app-php-1 php artisan ...   # Run artisan commands
```

---

## Feature Update: IDEMPOTENCY_SYNC_SKIP_EXISTING_DATA - May 30, 2026

### Problem Solved:
- `backfill:stock-prices --dispatch` dispatched ~312 jobs every time `.bat` was run, even if all stocks already had historical data from 2018+.
- `sync:stock-prices` dispatched 1,558 jobs every daily run, even for stocks that already had today's price synced (e.g., ran twice in a day).
- `sync:company-financials --dispatch --stale` created a job per symbol even when all 8 type/period combinations were fresh — jobs would immediately skip all work.

### Solution:
Add pre-dispatch idempotency checks in all 3 commands so jobs are only created when there is actual work to do.

### Modified:
- **`app/Console/Commands/SyncStockPrices.php`**
  - Added `--force` option
  - Before chunking symbols, queries `StockPrice` for `date = today` → gets set of already-synced `stock_id`s
  - Filters out those stock IDs before dispatch
  - Reports: `Skipped {N} symbols already synced today (YYYY-MM-DD). Use --force to override.`
  - Result: **979 of 1,558 stocks skipped** on re-run; only 58 jobs dispatched instead of 156

- **`app/Console/Commands/SyncCompanyFinancials.php`** (dispatch mode)
  - In `--dispatch` mode with `--stale`: before dispatching job per symbol, checks all type/period combinations via `$this->repo->find()` + `isStale()`
  - If ALL 8 combinations (4 types × 2 periods) are fresh → skip symbol, no job created
  - If ANY combination is stale/missing → dispatch job (job still respects `$stale` flag internally)
  - Reports: `Dispatched {N} jobs | Skipped (all fresh): {M}`

- **`app/Console/Commands/BackfillStockPrices.php`**
  - Added `--force` option
  - Uses **2-year threshold**: if a stock has any `StockPrice` record with `date <= now() - 2 years`, it has already been backfilled (daily-sync-only stocks only have 1-2 years of data)
  - Filters out already-backfilled stocks before dispatch
  - Reports: `Skipped {N} stocks already have historical data (data before YYYY-MM-DD). Use --force to rebackfill.`
  - Result: **900 of 1,558 stocks skipped** on re-run

### Verified:
- `php artisan sync:stock-prices` → `Skipped 979 symbols already synced today (2026-05-29)` ✓
- `php artisan sync:company-financials --symbol=VCB --type=income --period=quarter --dispatch --stale` → `Dispatched 0 jobs | Skipped (all fresh): 1` ✓
- `php artisan backfill:stock-prices --dispatch` → `Skipped 900 stocks... Dispatched 132 jobs` ✓
- `--force` flag bypasses all checks and dispatches everything ✓

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

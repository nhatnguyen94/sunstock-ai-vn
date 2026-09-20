# Project Structure & Architecture

## Overview
This is a Laravel 12 stock application with strict separation between Frontend (user-facing) and Backend (admin) layers.

## Directory Structure

### Frontend (User Interface)
- **Controllers**: `app/Frontend/Controllers/` - Handle requests from regular users
  - `Controller.php` - Base controller (extends `Illuminate\Routing\Controller`)
  - `StockController.php` - Homepage, stock chart view, stock search, AI chat, compare, company financials API (`finance`), ratio-based stock screener (`screener`)
  - `AuthController.php` - User login/registration/logout with validation
  - `EmailVerificationController.php` - Email verification flow (notice, resend, verify, admin verify/unverify)
  - `PasswordResetController.php` - Forgot/reset password flow via Laravel's built-in `Password` broker (`showForgotForm`, `sendResetLink`, `showResetForm`, `reset`). `sendResetLink` always returns the same generic message regardless of whether the email exists (avoids user enumeration).
  - `ProfileController.php` - User profile management (show/edit/update: username, mobile, birthday, gender, address, bio, avatar upload, password change). `storeAvatar()` handles upload/replace/remove of the avatar file on the `public` disk (`storage/app/public/avatars`, served via `storage/` symlink) — public so it's unit-testable with `Storage::fake()`
  - `PortfolioController.php` - Portfolio management (full CRUD + stock management + AJAX price update)
  - `ExchangeRateController.php` - View & search exchange rates
  - `GoldPriceController.php` - Gold page `/gold` (`index`), chart series `/gold/history/{id}?range=1D|7D|30D|ALL` (`history`, JSON) and rate-limited "Làm mới" (`refresh`, POST: 429 on cooldown, 502 on source failure)
  - `CompanyProfileController.php` - Company profile page `/company/{symbol}` (`show`) + AJAX first-visit loader / manual refresh (`load`, POST, `?force=1` is rate-limited). Cached profile → instant server-rendered page; none yet → light shell whose JS loads it once then reloads
  - `FundController.php` - Open-ended fund catalog: `index` (filter type/owner/text, sort by any return window), `show`, `detail` (JSON: NAV history + holdings + per-window stats), `compare` (≤4 funds)
  - `AiController.php` - AI market prediction

- **Services**: `app/Frontend/Services/` - Business logic for Frontend
  - `StockService.php` - Call Python scripts to fetch/sync stock data, hot industries
  - `ExchangeRateService.php` - Handle exchange rate data; DB-first cache with fallback to `py/get_exchange_rate.py`. `parsePythonOutput()` is split out from `fetchRatesFromPython()` for unit testing (scans exec() stdout backward for the JSON line — vnstock prints promo banners before it)
  - `AiService.php` - AI chat/prediction via Groq API (llama-3.3-70b-versatile, fallback chain, Redis cache 2h for predict, XSS-safe)
  - `NewsService.php` - Reads news from DB via NewsRepositoryInterface. getLatestNews(6) for homepage, getPaginatedNews for /news page.
  - `PortfolioService.php` - Portfolio management business logic. **Prices**: `refreshPrices()` returns a report (`updated`, `missing`, `queued`, `as_of`), `getPortfolioAnalytics()` refreshes from synced quotes on every view (no button needed) and returns holdings rows, today's move, best/worst, performance series, upcoming events (via `CompanyProfileService::upcomingEventsFor()`), concentration suggestions; `requestPriceSync()` queues a deduplicated `ProcessStockPriceSync` for symbols with no data; `getQuote()` feeds the add form. Earlier notes: Injects both `PortfolioRepositoryInterface` and `StockRepositoryInterface` — no direct Eloquent queries, fully mockable. `fetchCurrentPrices()` delegates to `StockRepositoryInterface::getLatestPrices()`; `refreshAllPortfolioPrices()` (used by `sync:portfolio-prices`) updates every active portfolio and fires `PortfolioAlertNotification` once per target/stop-loss crossing via `PortfolioRepositoryInterface::setAlertFlag()`
  - `CompanyFinancialService.php` - Fetches company financials (income/balance/cashflow/ratio) via Python; DB-cached, falls back to live Python on miss. `screenStocks(filters)` parses cached ratio JSON into a filterable/sortable screener dataset (cached 1h as `screener_ratio_metrics`)
  - `CompanyProfileService.php` - Company profile (vnstock `Company`, KBS + VCI merged by `py/get_company_profile.py`): DB-first, **stale-while-revalidate** (stale copy served instantly + one deduplicated `SyncCompanyProfileJob` queued), `load()` for first-visit/forced refresh via `SingleFlight`, 15-min negative cache for unknown symbols (timeouts/outages are NOT negative-cached), `present()` shapes events (upcoming/dividends/meetings/insider trades), grouped officers and donut-chart series. Python boundary is the protected `runScript()` so tests stub it
  - `FundService.php` - Fund catalog (vnstock `Fund`/Fmarket): `catalog()` (first-ever visit loads the listing live, then DB-only), `normalizeFilters()` (whitelists type/sort/dir — nothing user-supplied reaches `ORDER BY`), `syncAll()` (one Fmarket call updates every fund), `detail()` (NAV history + holdings, cached 6h in `Cache` + `SingleFlight`, adds `FundMetrics` stats), `parseCodes()`/`compareFunds()`/`pickerList()`. Python boundary: protected `runListScript()`/`runDetailScript()`
  - `GoldPriceService.php` - Gold/silver prices (vnstock `sjc_gold_price` + `btmc_goldprice` via `py/get_gold_price.py`): DB-first, first-ever visit loads live once (`SingleFlight`), a page older than 20 min queues one deduplicated `SyncGoldPricesJob`; `page()` builds headline SJC/BTMC quotes with day change, SJC uniform-branch detection, world price in VND/lượng (USD × 37.5/31.1035 × VCB USD sell) and the domestic premium; `history()` for the chart; `sync()`, `refresh()` (120s cooldown), protected `runScript()`

- **Repositories**: `app/Frontend/Repositories/` - Database access for Frontend
  - `StockRepository.php` - CRUD operations for stock data. `getLatestPrices(symbols)` — batch latest-close lookup via `Stock::latestPrice`, used by `PortfolioService`
  - `ExchangeRateRepository.php` - CRUD operations for exchange rate data
  - `UserProfileRepository.php` - CRUD operations for user profiles; `updateOrCreateForUser()` — accounts don't always have a profile row yet (e.g. admin-created ones), so this creates one on first save instead of crashing
  - `PortfolioRepository.php` - CRUD operations for portfolios and portfolio items; `getAllActivePortfolios()` for the scheduled bulk price refresh; `setAlertFlag()` persists target/stop-loss alert timestamps. `createItem`/`updateItem`/`deleteItem` call `Portfolio::recalculateTotals()` on a freshly-fetched Portfolio (not the item's possibly-stale cached one) instead of incrementally adjusting totals — see `Portfolio` model. A `paginate()` method exists for the portfolio list but isn't wired into `PortfolioController::index()` yet, which loads all of a user's portfolios unpaginated.
  - `NewsRepository.php` - Reads news from DB: getLatest, paginate (filter by category slug/search), getCategories
  - `CompanyFinancialRepository.php` - DB cache for company financials: find(symbol, type, period), upsert, getAllRatiosByPeriod(period) for the screener
  - `CompanyProfileRepository.php` - DB cache for company profiles: find, upsert, staleSymbols(limit), allSymbols
  - `FundRepository.php` - Fund catalog queries: `search(filters)` (NULL returns always sort last; `SORTABLE` whitelist), findByShortName/findManyByShortNames, upsertMany, typeCounts/typeStats/owners/lastSyncedAt
  - `GoldPriceRepository.php` - Gold quotes: `saveQuotes` (normalises `quoted_at`, SJC only stores a changed price), `latestQuotes(metal)` (newest row per source/product/branch), `quoteBefore`, `history`, `latestWorldPrice`, `lastSyncedAt`, `count`

- **Interfaces**: `app/Frontend/Interfaces/` - Contracts for Frontend
  - `StockRepositoryInterface.php` — includes `getLatestPrices(symbols)`
  - `ExchangeRateRepositoryInterface.php`
  - `GoldPriceRepositoryInterface.php` — saveQuotes, latestQuotes, quoteBefore, history, exists, latestWorldPrice, lastSyncedAt, count
  - `UserProfileRepositoryInterface.php`
  - `PortfolioRepositoryInterface.php` — includes `getAllActivePortfolios()` for the scheduled bulk price refresh, `setAlertFlag(item, column, value)` for target/stop-loss timestamps
  - `NewsServiceInterface.php` — getLatestNews, getPaginatedNews, getCategories
  - `NewsRepositoryInterface.php` — getLatest, paginate, getCategories
  - `CompanyFinancialRepositoryInterface.php` — find, upsert, getAllRatiosByPeriod(period)
  - `CompanyProfileRepositoryInterface.php` — find, upsert, staleSymbols, allSymbols
  - `FundRepositoryInterface.php` — search, findByShortName, findManyByShortNames, upsertMany, count, lastSyncedAt, typeCounts, owners, typeStats

### Backend (Administration)
- **Controllers**: `app/Backend/Controllers/` - Handle admin requests
  - `Controller.php` - Base controller for Backend
  - `AdminAuthController.php` - Separate admin login/logout
  - `AccountController.php` - Self-service password change for the currently-logged-in backend user (`/admin/account`) — no `can:` gate, every backend account (any role) manages its own. Separate from `AdminAuthController` (login/logout only) and from Frontend's `ProfileController` (full profile editing, still reachable at `/profile`).
  - `DashboardController.php` - Admin dashboard with system statistics
  - `UserController.php` - Admin user management (CRUD, role assignment)
  - `RoleController.php` - Role management: CRUD + assign permissions to a role (checkbox grid grouped by `permissions.group`). Gate: `manage-roles`. Blocks destroy for system roles (`RoleService::isSystemRole()`) and roles still assigned to a user.
  - `PermissionController.php` - Permission management: CRUD (name/display_name/group). Gate: `manage-permissions`. Blocks destroy for the 2 core permissions (`manage-roles`, `manage-permissions`) via `PermissionService::isCorePermission()`.
  - `QueueMonitorController.php` - Queue dashboard: `index`/`stats` (JSON, polled), `retry`/`destroy`/`retryAll`/`destroyAll` for `failed_jobs`. Gate: `manage-queue`. See "Queue Monitoring" below.
  - `StockController.php` - Admin stock management (list, update prices)
  - `NewsController.php` - Admin news management (list, update RSS)
  - `NewsCategoryController.php` - CRUD for `news_categories` (`/admin/news-categories`). Gate: `manage-features`. Blocks destroy for a category with existing `news` rows, or one referenced by `NewsService::SOURCES` (RSS sync would insert future articles under a `category_id` that no longer resolves).
  - `PortfolioController.php` - Admin portfolio management (list, toggle status, destroy)
  - `TimelineController.php` - Admin timeline/activity log viewer (real data from `activity_logs` table, filters, pagination)
  - `SyncStatusController.php` - Admin sync status page; AJAX trigger buttons for 5 whitelisted artisan commands; logs to ActivityLogger

- **Services**: `app/Backend/Services/`
  - `StockService.php` - Admin stock business logic: data normalization, orchestration, price-update job dispatch. Implements `StockServiceInterface`, delegates DB to `StockRepository`.
  - `NewsService.php` - Crawls 5 RSS feeds (VnExpress ×2, CafeF ×2, Dân Trí ×1), deduplicates by url_hash, persists to `news` table. `usedCategoryIds()` exposes the `category_id`s hardcoded in `SOURCES`, for `NewsCategoryService`'s destroy guard. Implements `NewsServiceInterface`.
  - `NewsCategoryService.php` - CRUD for `NewsCategory` + `isInUse()` guard (has news rows, or `id` is in `NewsService::usedCategoryIds()`). Implements `NewsCategoryServiceInterface`, delegates DB to `NewsCategoryRepository`.
  - `UserService.php` - Admin user management business logic (CRUD, role assignment). Implements `UserServiceInterface`, delegates DB to `UserRepository`.
  - `RoleService.php` - Role CRUD + `isSystemRole()` guard (admin/webadmin/adminsupport/user cannot be deleted via UI). `getPermissions()` for the picker form. Implements `RoleServiceInterface`, delegates DB to `RoleRepository`.
  - `PermissionService.php` - Permission CRUD + `isCorePermission()` guard (`manage-roles`/`manage-permissions` cannot be deleted via UI — would lock the admin out of this screen). Implements `PermissionServiceInterface`, delegates DB to `PermissionRepository`.
  - `QueueMonitorService.php` - Queue stats + failed-job decoration (`job_class`/`short_exception`) + retry/delete/retry-all/delete-all. Implements `QueueMonitorServiceInterface`, delegates to `QueueMonitorRepository`.
- **Repositories**: `app/Backend/Repositories/`
  - `StockRepository.php` - Admin stock DB operations (paginate with filters, getExchanges, create/update/delete with cache busting)
  - `NewsRepository.php` - News DB operations (paginate with filters, bulk insertNew with dedup, getSources, getLatestSyncTime)
  - `NewsCategoryRepository.php` - `NewsCategory` DB operations (all() with news count, create/update with auto-slug via `Str::slug()` when not provided, delete)
  - `UserRepository.php` - Admin user DB operations (paginate with search, findWithRelations, create/update/delete with role sync via syncRoles)
  - `ActivityLogRepository.php` - Activity log DB operations (paginate with type/date/search filters, countByType for last 7 days)
  - `RoleRepository.php` - Role DB operations (all() with users/permissions counts, findWithRelations, create/update with `permissions()->sync()`, delete)
  - `PermissionRepository.php` - Permission DB operations (all() with roles count, findWithRelations, create, update, delete)
  - `QueueMonitorRepository.php` - Redis queue size queries (`Queue::connection('redis')`) + `failed_jobs` table queries/retry/forget
- **Interfaces**: `app/Backend/Interfaces/`
  - `StockServiceInterface.php` - Contract for admin stock service (listStocks, getExchanges, createStock, updateStock, deleteStock, triggerPriceUpdate)
  - `StockRepositoryInterface.php` - Contract for admin stock DB operations
  - `NewsServiceInterface.php` - Contract for admin news service (listNews, syncFromAllSources, getSources)
  - `NewsRepositoryInterface.php` - Contract for admin news DB operations
  - `NewsCategoryServiceInterface.php` / `NewsCategoryRepositoryInterface.php` - Contracts for News Category management
  - `UserServiceInterface.php` - Contract for admin user service (listUsers, getRoles, createUser, updateUser, deleteUser, findWithRelations)
  - `UserRepositoryInterface.php` - Contract for admin user DB operations (paginate, findWithRelations, create, update, delete)
  - `ActivityLogRepositoryInterface.php` - Contract for activity log DB (paginate with filters, countByType)
  - `RoleServiceInterface.php` / `RoleRepositoryInterface.php` - Contracts for role management (see `docs/RBAC.md`)
  - `PermissionServiceInterface.php` / `PermissionRepositoryInterface.php` - Contracts for permission management (see `docs/RBAC.md`)
  - `QueueMonitorServiceInterface.php` / `QueueMonitorRepositoryInterface.php` - Contracts for the queue dashboard (see "Queue Monitoring" below)

### Models (Shared)
- `app/Models/` - Eloquent models shared between Frontend/Backend
  - `Stock.php` - Stock master data (symbol, name, exchange)
  - `StockPrice.php` - Historical daily price records
  - `StockSymbol.php` - Stock symbol reference list
  - `ExchangeRate.php` - Daily exchange rate records
  - `User.php` - User auth (implements `MustVerifyEmail`), RBAC helpers (`hasRole`, `hasAnyRole`, `canAccessBackend`, `hasPermission`)
  - `UserProfile.php` - Extended user profile (username, mobile, birthday, gender [`male`/`female`/`other`], address, bio, avatar — all editable from `/profile/edit` as of the profile audit follow-up)
  - `Portfolio.php` - User portfolios with P&L calculations. `recalculateTotals()` recomputes `total_invested`/`current_value` from the actual items (source of truth) — always call it via a freshly-fetched Portfolio, not one whose `items` relation may already be cached from before the triggering change
  - `PortfolioItem.php` - Individual stock holdings
  - `Role.php` - RBAC role model (constants: `admin`, `webadmin`, `adminsupport`, `user`); `permissions()` BelongsToMany + `hasPermission()`
  - `Permission.php` - DB-driven permission model (name/display_name/group); `roles()` BelongsToMany. See `docs/RBAC.md`.
  - `NewsCategory.php` - News category model; fillable(name, slug); hasMany(News)
  - `News.php` - News article; belongsTo(NewsCategory via category_id); fields: title, description, url, url_hash, source, image_url, category_id, published_at, synced_at
  - `HotIndustry.php` - Hot industry stocks synced by scheduler; fields: symbol, organ_name, icb_name3
  - `StockPriceSummary.php` - Monthly OHLCV summary per stock; belongsTo(Stock); fields: stock_id, period_start, open, high, low, close, volume
  - `CompanyFinancial.php` - DB cache for company financial data; fields: symbol, type, period, raw_data (JSON), synced_at; STALE_DAYS=30
  - `CompanyProfile.php` - DB cache of one company's whole profile (symbol unique, `data` JSON, synced_at); `STALE_DAYS=3`, `isStale()`
  - `Fund.php` - One open-ended fund (Fmarket): identity, `type_code` (STOCK/BOND/BALANCED/MMF/OTHER, derived from the Vietnamese label by `typeCodeFromLabel()`), fee, NAV, return columns `nav_change_*` (NULL = fund too young); `TYPE_LABELS`, `RETURN_COLUMNS`
  - `GoldPrice.php` - One gold/silver quote (`source` SJC|BTMC, `metal`, `product`, `branch`, `buy_price`/`sell_price` whole VND per lượng, `world_price` USD/oz, `quoted_at` UTC); unique per source+product+branch+quoted_at; `spread` and `display_name` accessors
  - `ActivityLog.php` - Activity log model; no `updated_at`; `iconConfig()` static method maps event_type → icon/color; event types: user_register, user_login, admin_login, portfolio_created, portfolio_deleted, stock_added, stock_removed, news_sync, stock_price_sync, admin_action
  - `QueueJobLog.php` - One row per queue job attempt (started/finished/duration/status/summary), written by `App\Support\QueueJobLogger`; powers Admin > Giám sát Queue's real-time view — see "Queue Monitoring" below

### Support Classes
- `app/Support/ActivityLogger.php` - Static helper `ActivityLogger::log(eventType, description, properties, user)`. Swallows all Throwable — never crashes calling code. Used in controllers for audit trail.
- `app/Support/PythonRunner.php` - **Mandatory** wrapper for every `py/*.py` call (`run()`/`runAndDecodeJson()`) — wraps `exec()` with the Unix `timeout` utility so a hung Python subprocess can't block a worker/request past a configured ceiling, which plain `exec()` + Laravel's own job `--timeout` cannot guarantee (pcntl signal delivery doesn't interrupt a blocked syscall). See `docs/PYTHON_INTEGRATION.md` for the full per-call-site timeout table and the real bug this fixes.
- `app/Support/SingleFlight.php` - `run(key, cached, produce)`: when N requests miss the same cache at once only ONE runs the expensive producer (a ~4s Python subprocess); the rest wait on a `Cache::lock` and re-read the result. Used by `CompanyProfileService` and `FundService`
- `app/Support/FundMetrics.php` - Pure NAV-series maths (no I/O): `windowStats()` → return %, max drawdown, annualised volatility for 1M/3M/6M/1Y/3Y/ALL (volatility is null for ALL: history is thinned to weekly before the daily window)
- `app/Support/VnFormat.php` - Null-safe Vietnamese formatting for Blade (`number`, `percent`, `bigMoney`, `date`, `trendClass`); used via `@use('App\Support\VnFormat', 'F')`
- `app/Support/Mojibake.php` - `repairCp437()`: inverse of "UTF-8 bytes shown as Windows console CP437" (`C├┤ng ty` → `Công ty`); returns null unless the result is valid UTF-8 and differs. Used by migration `2026_09_19_000003_repair_mojibake_in_stock_symbol_names` (182 `stock_symbols.name` rows)
- `app/Support/PriceUnit.php` - **The** conversion between the vnstock feed's price unit (thousands of VND: ACB `22.05` = 22,050 ₫) and whole VND. Anything stored in a VND column (portfolio buy/current/target/stop-loss) must go through it — copying a feed price straight in is what produced a `-99.97%` loss
- `app/Support/PortfolioPerformance.php` - Pure: rebuilds a portfolio's value-over-time from holdings' buy dates + daily closes (buy-date gating, carry-forward across gaps, buy-price fallback, thinning to ≤400 points). Powers the performance chart without a snapshot table

### Notifications
- `app/Notifications/PortfolioAlertNotification.php` - `ShouldQueue` mail notification; sent once when a `PortfolioItem` crosses `target_price` or `stop_loss_price`. Always queued `onQueue('high')` so it isn't delayed by heavy `default`-queue sync jobs.

### Middleware
- `app/Http/Middleware/AdminAccess.php` - Blocks non-backend users; registered as alias `admin` in `bootstrap/app.php`

### Console Commands
- `app/Console/Commands/SyncNews.php` - Crawl all RSS sources and save new articles; called by scheduler every 30 min
- `app/Console/Commands/SyncStockData.php` - Artisan command to sync stock symbols/details from Python
- `app/Console/Commands/SyncStockPrices.php` - Sync daily stock prices; skips symbols already synced today (`--force` to override)
- `app/Console/Commands/BackfillStockPrices.php` - Backfill historical prices from a start date; skips stocks with data >2 years old (`--force` to override)
- `app/Console/Commands/GeneratePriceSummaries.php` - Rebuild monthly OHLCV summaries in `stock_price_summaries`
- `app/Console/Commands/SyncHotIndustries.php` - Sync hot industry stock list from Python/vnstock
- `app/Console/Commands/SyncExchangeRates.php` - Sync VCB exchange rates via Python
- `app/Console/Commands/SyncCompanyFinancials.php` - Sync company financials to DB cache; `--dispatch` mode pre-filters fully-fresh symbols
- `app/Console/Commands/SyncCompanyProfiles.php` - `sync:company-profiles`: refresh stale cached profiles (`--symbol=`, `--seed` also adds not-yet-cached `stocks`, `--limit=50`, `--dispatch`)
- `app/Console/Commands/SyncFunds.php` - `sync:funds`: one Fmarket call updates every fund's NAV/returns (scheduled daily 18:30)
- `app/Console/Commands/SyncGoldPrices.php` - `sync:gold-prices`: fetch SJC + BTMC + world gold and store new quotes (scheduled every 15 min, 07:00–19:00 Asia/Ho_Chi_Minh)
- `app/Console/Commands/SyncPortfolioPrices.php` - `sync:portfolio-prices`: refresh every active portfolio's `current_price` from the latest `StockPrice` and fire target/stop-loss email alerts
- `app/Console/Commands/PruneQueueJobLogs.php` - `queue-logs:prune`: mark stuck `queue_job_logs` rows stale, delete old finished ones — see "Queue Monitoring" below
- `app/Console/Commands/RegisterVnstockApiKey.php` - Register vnstock API key via Python script

### Jobs
- `app/Jobs/ProcessStockPriceSync.php` - Queued job for async stock price synchronization. `queueSummary()`: symbol count + first 3
- `app/Jobs/BackfillStockPriceChunk.php` - Queued job for historical price backfill (multi-symbol batch, date range). `queueSummary()`: symbol count + first 3 + date range
- `app/Jobs/SyncCompanyFinancialJob.php` - Queued job for syncing company financials (all types/periods for one symbol). `queueSummary()`: the symbol
- `app/Jobs/SyncCompanyProfileJob.php` - Queued refresh of one company profile (`$timeout=90`, `tries=2`); throws on a transient failure so the queue retries, swallows "no such company". `queueSummary()`: the symbol
- `app/Jobs/SyncGoldPricesJob.php` - Queued gold refresh dispatched by `GoldPriceService::queueRefresh()` (`$timeout=90`, `tries=2`); throws on a source error so the queue retries
- All four implement a `queueSummary(): string` method (no formal interface — checked via `method_exists()`) purely for the Queue Monitor's real-time display; unrelated to `handle()`/queue processing itself

### Queue Monitoring
- `app/Backend/Controllers/QueueMonitorController.php` - `index` (dashboard), `stats` (JSON, polled every 5s by the page — queue depth + currently-processing + recently-finished + today's processed count), `retry`/`destroy`/`retryAll`/`destroyAll` for `failed_jobs` rows. Gate: `manage-queue`.
- `app/Backend/Services/QueueMonitorService.php` / `app/Backend/Repositories/QueueMonitorRepository.php` - Per-queue counts via `Queue::connection('redis')->pendingSize()/delayedSize()/reservedSize()`; failed jobs via `DB::table('failed_jobs')` + `Artisan::call('queue:retry'|'queue:forget')`; live activity via the `queue_job_logs` table (see below)
- `App\Support\QueueJobLogger` - Static helper, registered against `Queue::before()/after()/failing()` in `AppServiceProvider::registerQueueMonitoring()`. Writes one `queue_job_logs` row per job attempt (started/finished/duration/status), and best-effort extracts a human-readable `summary` by unserializing the job command and calling its `queueSummary()` if present. Same "never crash the caller" pattern as `App\Support\ActivityLogger` — here the caller is a live queue worker, so this matters even more.
- `App\Models\QueueJobLog` - `job_id` (stable per job attempt, from `Job::getJobId()`), `job_class`, `queue`, `summary`, `status` (processing/completed/failed/stale), `started_at`, `finished_at`, `duration_ms`
- `app/Console/Commands/PruneQueueJobLogs.php` (`queue-logs:prune`, hourly via scheduler) - Marks `processing` rows stuck >20 min as `stale` (a crashed worker never fired the finish event) and deletes finished/stale rows older than 3 days
- `resources/views/backend/queue-monitor/index.blade.php` - Tabler cards (per-queue counts) + 2 live tables (currently-processing, recently-finished, both rebuilt client-side from the JSON `stats` response every 5s) + failed-jobs table (retry/delete/retry-all/delete-all), vanilla JS `fetch()` — same pattern as `backend/sync-status/index.blade.php`
- `docker/php/supervisord.conf` (queue container) - 3 `queue:work redis --queue=high,default` processes (plain Laravel, no third-party package — a Horizon-based dashboard was tried and removed, see docs/HISTORY.md QUEUE_MONITOR_CUSTOM_PAGE for why)
- `config/queue.php` - `connections.redis.retry_after` (660s) must stay above the workers' `--timeout` (600s), see the comment there

### Routes (`routes/web.php`)
- **Public**: homepage, stock index/compare/finance, exchange rate, AI chat/predict, stock search
- **Auth** (throttled): login, register, logout, forgot-password, reset-password
- **Email Verification** (`auth` middleware): verify email, resend
- **User Protected** (`auth` + `verified`): profile, portfolio CRUD
- **Admin** (`/admin` prefix, `admin` middleware): dashboard, account (self-service password), users, roles, permissions, stocks, news, news categories, portfolios, timeline, queue monitor

### Views (`resources/views/`)
- **Frontend**: `index.blade.php`, `stock/`, `exchange_rate/`, `news/`, `portfolio/`, `profile/`, `auth/`
- **Backend (admin)**: `backend/dashboard/`, `backend/account/`, `backend/users/`, `backend/roles/`, `backend/permissions/`, `backend/queue-monitor/`, `backend/stocks/`, `backend/news/`, `backend/news-categories/`, `backend/portfolios/`, `backend/timeline/`, `backend/sync-status/`, `backend/auth/`, `backend/layouts/`
- **Shared**: `layouts/`, `partials/`

### Python Scripts (`py/`)
- `get_stock.py` - Fetch historical price data for one or more symbols (via vnstock, VCI source, 1 year)
- `get_exchange_rate.py` - Fetch VCB exchange rates by date or last N days
- `get_gold_price.py` - Fetch SJC + BTMC gold/silver prices and the world gold price (vnstock), cross-checking SJC against BTMC
- `get_hot_industries.py` - Fetch hot industry stocks (Banking, Real Estate, IT)
- `get_stock_list.py` - Fetch full list of stock symbols from vnstock
- `get_company_finance.py` - Fetch company financial statements (income/balance/cashflow/ratio) for a symbol+type+period
- `register_api_key.py` - Register/configure vnstock API key

## Runtime Environment (Docker)

> **Full guide**: [docs/DOCKER.md](DOCKER.md)

Project runs in Docker Compose with 6 containers:

| Container | Role | Host Port |
|---|---|---|
| `nginx` | HTTPS reverse proxy (nginx:1.27-alpine) | 80, 443 |
| `php` | PHP-FPM 8.2 + Python 3 venv | — |
| `mysql` | MySQL 8.0 database | 3307 |
| `redis` | Redis 7 (queue, cache, sessions) | — |
| `queue` | 6 Redis queue workers (`queue:work redis --queue=high,default`, supervisor) — each running `py/get_stock.py`'s own 4-way internal thread pool, see docs/PYTHON_INTEGRATION.md | — |
| `scheduler` | Laravel scheduler (`schedule:work`) | — |

- **App URL**: `https://sunstock-local.dev`
- **Python path**: `/opt/venv/bin/python3` (env: `PYTHON_PATH`)
- **AI Provider**: Groq API (env: `GROQ_API_KEY`) — `config('services.groq.key')`

```bash
# Daily use
docker compose up -d
docker exec -it stock-app-php-1 bash
docker exec stock-app-php-1 php artisan <command>
```

## Namespace Convention
- Frontend: `App\Frontend\{Controllers|Services|Repositories|Interfaces}`
- Backend: `App\Backend\{Controllers|Services|Repositories|Interfaces}`
- Models: `App\Models`
- Middleware: `App\Http\Middleware`

## Key Architectural Patterns
- **Repository Pattern**: All DB access via Repository classes (bound via Interface in `AppServiceProvider`)
- **Service Layer**: Business logic in Service classes (injected into Controllers)
- **Dependency Injection**: Constructor injection throughout; all bindings in `AppServiceProvider::register()`
- **RBAC**: Role-based access control via `role_user` pivot table + `Gate` definitions in `AppServiceProvider::boot()`
- **Python Integration**: Services spawn Python subprocess calls to `py/*.py` scripts; results returned as JSON
- **Caching**: Frequently accessed data (featured stocks, exchange rates, hot industries) cached via Laravel Cache facade

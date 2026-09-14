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
  - `ProfileController.php` - User profile management (show/edit/update)
  - `PortfolioController.php` - Portfolio management (full CRUD + stock management + AJAX price update)
  - `ExchangeRateController.php` - View & search exchange rates
  - `AiController.php` - AI market prediction

- **Services**: `app/Frontend/Services/` - Business logic for Frontend
  - `StockService.php` - Call Python scripts to fetch/sync stock data, hot industries
  - `ExchangeRateService.php` - Handle exchange rate data
  - `AiService.php` - AI chat/prediction via Groq API (llama-3.3-70b-versatile, fallback chain, Redis cache 2h for predict, XSS-safe)
  - `NewsService.php` - Reads news from DB via NewsRepositoryInterface. getLatestNews(6) for homepage, getPaginatedNews for /news page.
  - `PortfolioService.php` - Portfolio management business logic. `fetchCurrentPrices()` reads real latest close via `Stock::latestPrice`; `refreshAllPortfolioPrices()` (used by `sync:portfolio-prices`) updates every active portfolio and fires `PortfolioAlertNotification` once per target/stop-loss crossing
  - `CompanyFinancialService.php` - Fetches company financials (income/balance/cashflow/ratio) via Python; DB-cached, falls back to live Python on miss. `screenStocks(filters)` parses cached ratio JSON into a filterable/sortable screener dataset (cached 1h as `screener_ratio_metrics`)

- **Repositories**: `app/Frontend/Repositories/` - Database access for Frontend
  - `StockRepository.php` - CRUD operations for stock data
  - `ExchangeRateRepository.php` - CRUD operations for exchange rate data
  - `UserProfileRepository.php` - CRUD operations for user profiles
  - `PortfolioRepository.php` - CRUD operations for portfolios and portfolio items; `getAllActivePortfolios()` for the scheduled bulk price refresh
  - `NewsRepository.php` - Reads news from DB: getLatest, paginate (filter by category slug/search), getCategories
  - `CompanyFinancialRepository.php` - DB cache for company financials: find(symbol, type, period), upsert, getAllRatiosByPeriod(period) for the screener

- **Interfaces**: `app/Frontend/Interfaces/` - Contracts for Frontend
  - `StockRepositoryInterface.php`
  - `ExchangeRateRepositoryInterface.php`
  - `UserProfileRepositoryInterface.php`
  - `PortfolioRepositoryInterface.php` — includes `getAllActivePortfolios()` for the scheduled bulk price refresh
  - `NewsServiceInterface.php` — getLatestNews, getPaginatedNews, getCategories
  - `NewsRepositoryInterface.php` — getLatest, paginate, getCategories
  - `CompanyFinancialRepositoryInterface.php` — find, upsert, getAllRatiosByPeriod(period)

### Backend (Administration)
- **Controllers**: `app/Backend/Controllers/` - Handle admin requests
  - `Controller.php` - Base controller for Backend
  - `AdminAuthController.php` - Separate admin login/logout
  - `DashboardController.php` - Admin dashboard with system statistics
  - `UserController.php` - Admin user management (CRUD, role assignment)
  - `StockController.php` - Admin stock management (list, update prices)
  - `NewsController.php` - Admin news management (list, update RSS)
  - `PortfolioController.php` - Admin portfolio management (list, toggle status, destroy)
  - `TimelineController.php` - Admin timeline/activity log viewer (real data from `activity_logs` table, filters, pagination)
  - `SyncStatusController.php` - Admin sync status page; AJAX trigger buttons for 5 whitelisted artisan commands; logs to ActivityLogger

- **Services**: `app/Backend/Services/`
  - `StockService.php` - Admin stock business logic: data normalization, orchestration, price-update job dispatch. Implements `StockServiceInterface`, delegates DB to `StockRepository`.
  - `NewsService.php` - Crawls 5 RSS feeds (VnExpress ×2, CafeF ×2, Dân Trí ×1), deduplicates by url_hash, persists to `news` table. Implements `NewsServiceInterface`.
  - `UserService.php` - Admin user management business logic (CRUD, role assignment). Implements `UserServiceInterface`, delegates DB to `UserRepository`.
- **Repositories**: `app/Backend/Repositories/`
  - `StockRepository.php` - Admin stock DB operations (paginate with filters, getExchanges, create/update/delete with cache busting)
  - `NewsRepository.php` - News DB operations (paginate with filters, bulk insertNew with dedup, getSources, getLatestSyncTime)
  - `UserRepository.php` - Admin user DB operations (paginate with search, findWithRelations, create/update/delete with role sync via syncRoles)
  - `ActivityLogRepository.php` - Activity log DB operations (paginate with type/date/search filters, countByType for last 7 days)
- **Interfaces**: `app/Backend/Interfaces/`
  - `StockServiceInterface.php` - Contract for admin stock service (listStocks, getExchanges, createStock, updateStock, deleteStock, triggerPriceUpdate)
  - `StockRepositoryInterface.php` - Contract for admin stock DB operations
  - `NewsServiceInterface.php` - Contract for admin news service (listNews, syncFromAllSources, getSources)
  - `NewsRepositoryInterface.php` - Contract for admin news DB operations
  - `UserServiceInterface.php` - Contract for admin user service (listUsers, getRoles, createUser, updateUser, deleteUser, findWithRelations)
  - `UserRepositoryInterface.php` - Contract for admin user DB operations (paginate, findWithRelations, create, update, delete)
  - `ActivityLogRepositoryInterface.php` - Contract for activity log DB (paginate with filters, countByType)

### Models (Shared)
- `app/Models/` - Eloquent models shared between Frontend/Backend
  - `Stock.php` - Stock master data (symbol, name, exchange)
  - `StockPrice.php` - Historical daily price records
  - `StockSymbol.php` - Stock symbol reference list
  - `ExchangeRate.php` - Daily exchange rate records
  - `User.php` - User auth (implements `MustVerifyEmail`), RBAC helpers (`hasRole`, `hasAnyRole`, `canAccessBackend`)
  - `UserProfile.php` - Extended user profile
  - `Portfolio.php` - User portfolios with P&L calculations
  - `PortfolioItem.php` - Individual stock holdings
  - `Role.php` - RBAC role model (constants: `admin`, `webadmin`, `adminsupport`, `user`)
  - `NewsCategory.php` - News category model; fillable(name, slug); hasMany(News)
  - `News.php` - News article; belongsTo(NewsCategory via category_id); fields: title, description, url, url_hash, source, image_url, category_id, published_at, synced_at
  - `HotIndustry.php` - Hot industry stocks synced by scheduler; fields: symbol, organ_name, icb_name3
  - `StockPriceSummary.php` - Monthly OHLCV summary per stock; belongsTo(Stock); fields: stock_id, period_start, open, high, low, close, volume
  - `CompanyFinancial.php` - DB cache for company financial data; fields: symbol, type, period, raw_data (JSON), synced_at; STALE_DAYS=30
  - `ActivityLog.php` - Activity log model; no `updated_at`; `iconConfig()` static method maps event_type → icon/color; event types: user_register, user_login, admin_login, portfolio_created, portfolio_deleted, stock_added, stock_removed, news_sync, stock_price_sync, admin_action

### Support Classes
- `app/Support/ActivityLogger.php` - Static helper `ActivityLogger::log(eventType, description, properties, user)`. Swallows all Throwable — never crashes calling code. Used in controllers for audit trail.

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
- `app/Console/Commands/SyncPortfolioPrices.php` - `sync:portfolio-prices`: refresh every active portfolio's `current_price` from the latest `StockPrice` and fire target/stop-loss email alerts
- `app/Console/Commands/RegisterVnstockApiKey.php` - Register vnstock API key via Python script

### Jobs
- `app/Jobs/ProcessStockPriceSync.php` - Queued job for async stock price synchronization
- `app/Jobs/BackfillStockPriceChunk.php` - Queued job for historical price backfill (multi-symbol batch, date range)
- `app/Jobs/SyncCompanyFinancialJob.php` - Queued job for syncing company financials (all types/periods for one symbol)

### Routes (`routes/web.php`)
- **Public**: homepage, stock index/compare/finance, exchange rate, AI chat/predict, stock search
- **Auth** (throttled): login, register, logout
- **Email Verification** (`auth` middleware): verify email, resend
- **User Protected** (`auth` + `verified`): profile, portfolio CRUD
- **Admin** (`/admin` prefix, `admin` middleware): dashboard, users, stocks, news, portfolios, timeline

### Views (`resources/views/`)
- **Frontend**: `index.blade.php`, `stock/`, `exchange_rate/`, `news/`, `portfolio/`, `profile/`, `auth/`
- **Backend (admin)**: `backend/dashboard/`, `backend/users/`, `backend/stocks/`, `backend/news/`, `backend/portfolios/`, `backend/timeline/`, `backend/sync-status/`, `backend/auth/`, `backend/layouts/`
- **Shared**: `layouts/`, `partials/`

### Python Scripts (`py/`)
- `get_stock.py` - Fetch historical price data for one or more symbols (via vnstock, VCI source, 1 year)
- `get_exchange_rate.py` - Fetch VCB exchange rates by date or last N days
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
| `queue` | 6 queue workers (supervisor) | — |
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

# Routes Map

> Complete route → controller → action mapping. Run `php artisan route:list` to verify live.

## Frontend Routes (Public)

| Method | URI | Route Name | Controller | Action |
|---|---|---|---|---|
| GET | `/` | `home` | `StockController` | `home` |
| GET | `/stock` | `stock.index` | `StockController` | `index` |
| GET | `/stock/compare` | `stock.compare` | `StockController` | `compare` |
| GET | `/stock/compare-data` | `stock.compare-data` | `StockController` | `compareData` |
| GET | `/stock/finance` | `stock.finance` | `StockController` | `finance` |
| GET | `/stock/screener` | `stock.screener` | `StockController` | `screener` |
| GET | `/stocks-list` | *(none)* | `StockController` | `getStockSymbols` |
| GET | `/company/{symbol}` | `company.show` | `CompanyProfileController` | `show` |
| POST | `/company/{symbol}/load` | `company.load` | `CompanyProfileController` | `load` |
| GET | `/funds` | `funds.index` | `FundController` | `index` |
| GET | `/funds/compare` | `funds.compare` | `FundController` | `compare` |
| GET | `/funds/{code}` | `funds.show` | `FundController` | `show` |
| GET | `/funds/{code}/detail` | `funds.detail` | `FundController` | `detail` |
| POST | `/search` | `stock.search` | `StockController` | `search` |
| GET | `/news` | `news.index` | `NewsController` (Frontend) | `index` |
| GET | `/news/category/{categorySlug}` | `news.category` | `NewsController` (Frontend) | `index` |
| GET | `/exchange-rate` | `exchange-rate.index` | `ExchangeRateController` | `index` |
| GET | `/exchange-rate/search` | `exchange-rate.search` | `ExchangeRateController` | `search` |
| POST | `/ai-chat` | *(none)* | `StockController` | `aiChat` |
| POST | `/ai-predict` | *(none)* | `AiController` | `predict` |

**Route notes**: `{symbol}` must match `[A-Za-z0-9]{2,10}` and `{code}` `[A-Za-z0-9._-]{2,40}` (route constraints — anything else is a 404 before a controller runs). `/funds/compare` is declared above `/funds/{code}` so it is not swallowed by it. `POST /company/{symbol}/load?force=1` is additionally rate-limited (one forced refresh per symbol per 5 minutes → 429).

**Throttle notes**: the data-heavy group (`/stock*`, `/company/*`, `/funds*`, `/exchange-rate*`) → 30 req/min; `/ai-chat` and `/ai-predict` → 10 req/min; `/login` and `/register` → 5 req/min

## Auth Routes

| Method | URI | Route Name | Controller | Action |
|---|---|---|---|---|
| GET | `/login` | `login` | `AuthController` | `showLoginForm` |
| POST | `/login` | *(none)* | `AuthController` | `login` |
| GET | `/register` | `register` | `AuthController` | `showRegisterForm` |
| POST | `/register` | *(none)* | `AuthController` | `register` |
| POST | `/logout` | `logout` | `AuthController` | `logout` |

## Password Reset Routes (throttled 5/min, same group as login/register)

| Method | URI | Route Name | Controller | Action |
|---|---|---|---|---|
| GET | `/forgot-password` | `password.request` | `PasswordResetController` | `showForgotForm` |
| POST | `/forgot-password` | `password.email` | `PasswordResetController` | `sendResetLink` |
| GET | `/reset-password/{token}` | `password.reset` | `PasswordResetController` | `showResetForm` |
| POST | `/reset-password` | `password.update` | `PasswordResetController` | `reset` |

## Email Verification Routes (middleware: `auth`)

| Method | URI | Route Name | Controller | Action |
|---|---|---|---|---|
| GET | `/email/verify` | `verification.notice` | `EmailVerificationController` | `notice` |
| POST | `/email/verification-notification` | `verification.send` | `EmailVerificationController` | `resend` |
| GET | `/email/verify/{id}/{hash}` | `verification.verify` | `EmailVerificationController` | `verify` |

## User Protected Routes (middleware: `auth` + `verified`)

| Method | URI | Route Name | Controller | Action |
|---|---|---|---|---|
| GET | `/profile` | `profile.show` | `ProfileController` | `show` |
| GET | `/profile/edit` | `profile.edit` | `ProfileController` | `edit` |
| PUT | `/profile` | `profile.update` | `ProfileController` | `update` |
| GET | `/portfolio` | `portfolio.index` | `PortfolioController` | `index` |
| GET | `/portfolio/create` | `portfolio.create` | `PortfolioController` | `create` |
| POST | `/portfolio` | `portfolio.store` | `PortfolioController` | `store` |
| GET | `/portfolio/{id}` | `portfolio.show` | `PortfolioController` | `show` |
| GET | `/portfolio/{id}/edit` | `portfolio.edit` | `PortfolioController` | `edit` |
| PUT | `/portfolio/{id}` | `portfolio.update` | `PortfolioController` | `update` |
| DELETE | `/portfolio/{id}` | `portfolio.destroy` | `PortfolioController` | `destroy` |
| GET | `/portfolio/{id}/add-stock` | `portfolio.add-stock` | `PortfolioController` | `addStock` |
| POST | `/portfolio/{id}/add-stock` | `portfolio.store-stock` | `PortfolioController` | `storeStock` |
| PUT | `/portfolio/item/{itemId}` | `portfolio.update-item` | `PortfolioController` | `updateItem` |
| DELETE | `/portfolio/item/{itemId}` | `portfolio.remove-stock` | `PortfolioController` | `removeStock` |
| POST | `/portfolio/{id}/update-prices` | `portfolio.update-prices` | `PortfolioController` | `updatePrices` |
| GET | `/portfolio/add?symbol=` | `portfolio.quick-add` | `PortfolioController` | `quickAdd` (entry from stock/company pages: 0 portfolios → create, 1 → add form, several → chooser) |
| GET | `/portfolio/quote/{symbol}` | `portfolio.quote` | `PortfolioController` | `quote` (name + latest price in VND, JSON) |
| GET | `/portfolio/{id}/export` | `portfolio.export` | `PortfolioController` | `export` (CSV, UTF-8 BOM) |
| GET | `/portfolio/{id}/rebalance-suggestions` | `portfolio.rebalance-suggestions` | `PortfolioController` | `getRebalanceSuggestions` |

## Backend / Admin Routes (prefix: `/admin`, middleware: `auth:web` + `admin`)

> All controllers are in `App\Backend\Controllers\`

| Method | URI | Route Name | Controller | Action | Gate Required |
|---|---|---|---|---|---|
| GET | `/admin/login` | `admin.login` | `AdminAuthController` | `showLoginForm` | *(public)* |
| POST | `/admin/login` | `admin.login.post` | `AdminAuthController` | `login` | *(public)* |
| POST | `/admin/logout` | `admin.logout` | `AdminAuthController` | `logout` | any backend role |
| GET | `/admin` | `admin.dashboard` | `DashboardController` | `index` | any backend role |
| GET | `/admin/account` | `admin.account.edit` | `AccountController` | `edit` | any backend role (self-service) |
| PUT | `/admin/account` | `admin.account.update` | `AccountController` | `update` | any backend role (self-service) |
| GET | `/admin/timeline` | `admin.timeline` | `TimelineController` | `index` | `view-timeline` |
| GET | `/admin/timeline/stats` | `admin.timeline.stats` | `TimelineController` | `stats` | `view-timeline` |
| GET/POST/PUT/DELETE | `/admin/users` | `admin.users.*` | `UserController` | *(resource)* | `manage-users` |
| POST | `/admin/users/{user}/verify` | `admin.users.verify` | `EmailVerificationController` | `adminVerify` | `manage-users` |
| POST | `/admin/users/{user}/unverify` | `admin.users.unverify` | `EmailVerificationController` | `adminUnverify` | `manage-users` |
| GET/POST/PUT/DELETE | `/admin/roles` | `admin.roles.*` | `RoleController` | *(resource)* | `manage-roles` |
| GET/POST/PUT/DELETE | `/admin/permissions` | `admin.permissions.*` | `PermissionController` | *(resource)* | `manage-permissions` |
| GET/POST/PUT/DELETE | `/admin/stocks` | `admin.stocks.*` | `StockController` | *(resource)* | `manage-features` |
| POST | `/admin/stocks/update-prices` | `admin.stocks.update-prices` | `StockController` | `updatePrices` | `manage-features` |
| GET | `/admin/news` | `admin.news.index` | `NewsController` | `index` | `manage-features` |
| POST | `/admin/news/update-rss` | `admin.news.update-rss` | `NewsController` | `updateRss` | `manage-features` |
| GET/POST/PUT/DELETE | `/admin/news-categories` | `admin.news-categories.*` | `NewsCategoryController` | *(resource, no `show`)* | `manage-features` |
| GET | `/admin/portfolios` | `admin.portfolios.index` | `PortfolioController` | `index` | `manage-features` |
| GET | `/admin/portfolios/{portfolio}` | `admin.portfolios.show` | `PortfolioController` | `show` | `manage-features` |
| PATCH | `/admin/portfolios/{portfolio}/toggle-status` | `admin.portfolios.toggle-status` | `PortfolioController` | `toggleStatus` | `manage-features` |
| DELETE | `/admin/portfolios/{portfolio}` | `admin.portfolios.destroy` | `PortfolioController` | `destroy` | `manage-features` |
| GET | `/admin/portfolios-stats` | `admin.portfolios.stats` | `PortfolioController` | `stats` | `manage-features` |
| GET | `/admin/sync-status` | `admin.sync-status` | `SyncStatusController` | `index` | `manage-features` |
| POST | `/admin/sync-status/trigger/{key}` | `admin.sync-status.trigger` | `SyncStatusController` | `trigger` | `manage-features` |
| GET | `/admin/queue` | `admin.queue.index` | `QueueMonitorController` | `index` | `manage-queue` |
| GET | `/admin/queue/stats` | `admin.queue.stats` | `QueueMonitorController` | `stats` | `manage-queue` |
| POST | `/admin/queue/failed/retry-all` | `admin.queue.retry-all` | `QueueMonitorController` | `retryAll` | `manage-queue` |
| DELETE | `/admin/queue/failed` | `admin.queue.destroy-all` | `QueueMonitorController` | `destroyAll` | `manage-queue` |
| POST | `/admin/queue/failed/{uuid}/retry` | `admin.queue.retry` | `QueueMonitorController` | `retry` | `manage-queue` |
| DELETE | `/admin/queue/failed/{uuid}` | `admin.queue.destroy` | `QueueMonitorController` | `destroy` | `manage-queue` |

> **Note**: Gate checks are enforced at the controller action level via `Gate::authorize()`, not at the route level (the route only checks that the user can access the backend at all).

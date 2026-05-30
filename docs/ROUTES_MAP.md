# Routes Map

> Complete route → controller → action mapping. Run `php artisan route:list` to verify live.

## Frontend Routes (Public)

| Method | URI | Route Name | Controller | Action |
|---|---|---|---|---|
| GET | `/` | `home` | `StockController` | `home` |
| GET | `/stock` | `stock.index` | `StockController` | `index` |
| GET | `/stock/compare` | `stock.compare` | `StockController` | `compare` |
| GET | `/stock/compare-data` | `stock.compare-data` | `StockController` | `compareData` |
| GET | `/stocks-list` | *(none)* | `StockController` | `getStockSymbols` |
| POST | `/search` | `stock.search` | `StockController` | `search` |
| GET | `/news` | `news.index` | `NewsController` (Frontend) | `index` |
| GET | `/news/category/{slug}` | `news.category` | `NewsController` (Frontend) | `index` |
| GET | `/exchange-rate` | `exchange-rate.index` | `ExchangeRateController` | `index` |
| GET | `/exchange-rate/search` | `exchange-rate.search` | `ExchangeRateController` | `search` |
| POST | `/ai-chat` | *(none)* | `StockController` | `aiChat` |
| POST | `/ai-predict` | *(none)* | `AiController` | `predict` |

**Throttle notes**: `/ai-chat` and `/ai-predict` → 10 req/min; `/login` and `/register` → 5 req/min

## Auth Routes

| Method | URI | Route Name | Controller | Action |
|---|---|---|---|---|
| GET | `/login` | `login` | `AuthController` | `showLoginForm` |
| POST | `/login` | *(none)* | `AuthController` | `login` |
| GET | `/register` | `register` | `AuthController` | `showRegisterForm` |
| POST | `/register` | *(none)* | `AuthController` | `register` |
| POST | `/logout` | `logout` | `AuthController` | `logout` |

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
| GET | `/portfolio/{id}/rebalance-suggestions` | `portfolio.rebalance-suggestions` | `PortfolioController` | `getRebalanceSuggestions` |

## Backend / Admin Routes (prefix: `/admin`, middleware: `auth:web` + `admin`)

> All controllers are in `App\Backend\Controllers\`

| Method | URI | Route Name | Controller | Action | Gate Required |
|---|---|---|---|---|---|
| GET | `/admin/login` | `admin.login` | `AdminAuthController` | `showLoginForm` | *(public)* |
| POST | `/admin/login` | `admin.login.post` | `AdminAuthController` | `login` | *(public)* |
| POST | `/admin/logout` | `admin.logout` | `AdminAuthController` | `logout` | any backend role |
| GET | `/admin` | `admin.dashboard` | `DashboardController` | `index` | any backend role |
| GET | `/admin/timeline` | `admin.timeline` | `TimelineController` | `index` | `view-timeline` |
| GET | `/admin/timeline/stats` | `admin.timeline.stats` | `TimelineController` | `stats` | `view-timeline` |
| GET/POST/PUT/DELETE | `/admin/users` | `admin.users.*` | `UserController` | *(resource)* | `manage-users` |
| POST | `/admin/users/{user}/verify` | `admin.users.verify` | `EmailVerificationController` | `adminVerify` | `manage-users` |
| POST | `/admin/users/{user}/unverify` | `admin.users.unverify` | `EmailVerificationController` | `adminUnverify` | `manage-users` |
| GET/POST/PUT/DELETE | `/admin/stocks` | `admin.stocks.*` | `StockController` | *(resource)* | `manage-features` |
| POST | `/admin/stocks/update-prices` | `admin.stocks.update-prices` | `StockController` | `updatePrices` | `manage-features` |
| GET | `/admin/news` | `admin.news.index` | `NewsController` | `index` | `manage-features` |
| POST | `/admin/news/update-rss` | `admin.news.update-rss` | `NewsController` | `updateRss` | `manage-features` |
| GET | `/admin/portfolios` | `admin.portfolios.index` | `PortfolioController` | `index` | `manage-features` |
| GET | `/admin/portfolios/{portfolio}` | `admin.portfolios.show` | `PortfolioController` | `show` | `manage-features` |
| PATCH | `/admin/portfolios/{portfolio}/toggle-status` | `admin.portfolios.toggle-status` | `PortfolioController` | `toggleStatus` | `manage-features` |
| DELETE | `/admin/portfolios/{portfolio}` | `admin.portfolios.destroy` | `PortfolioController` | `destroy` | `manage-features` |
| GET | `/admin/portfolios-stats` | `admin.portfolios.stats` | `PortfolioController` | `stats` | `manage-features` |

> **Note**: Gate checks are enforced at the controller action level via `Gate::authorize()`, not at the route level (the route only checks that the user can access the backend at all).

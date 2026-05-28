# Project Structure & Architecture

## Overview
This is a Laravel 12 stock application with strict separation between Frontend (user-facing) and Backend (admin) layers.

## Directory Structure

### Frontend (User Interface)
- **Controllers**: `app/Frontend/Controllers/` - Handle requests from regular users
  - `Controller.php` - Base controller (extends `Illuminate\Routing\Controller`)
  - `StockController.php` - Homepage, stock chart view, stock search, AI chat, compare
  - `AuthController.php` - User login/registration/logout with validation
  - `EmailVerificationController.php` - Email verification flow (notice, resend, verify, admin verify/unverify)
  - `ProfileController.php` - User profile management (show/edit/update)
  - `PortfolioController.php` - Portfolio management (full CRUD + stock management + AJAX price update)
  - `ExchangeRateController.php` - View & search exchange rates
  - `AiController.php` - AI market prediction

- **Services**: `app/Frontend/Services/` - Business logic for Frontend
  - `StockService.php` - Call Python scripts to fetch/sync stock data, hot industries
  - `ExchangeRateService.php` - Handle exchange rate data
  - `AiService.php` - AI chat/prediction integration
  - `NewsService.php` - RSS news from VnExpress
  - `PortfolioService.php` - Portfolio management business logic

- **Repositories**: `app/Frontend/Repositories/` - Database access for Frontend
  - `StockRepository.php` - CRUD operations for stock data
  - `ExchangeRateRepository.php` - CRUD operations for exchange rate data
  - `UserProfileRepository.php` - CRUD operations for user profiles
  - `PortfolioRepository.php` - CRUD operations for portfolios and portfolio items

- **Interfaces**: `app/Frontend/Interfaces/` - Contracts for Frontend
  - `StockRepositoryInterface.php`
  - `ExchangeRateRepositoryInterface.php`
  - `UserProfileRepositoryInterface.php`
  - `PortfolioRepositoryInterface.php`
  - `NewsServiceInterface.php`

### Backend (Administration)
- **Controllers**: `app/Backend/Controllers/` - Handle admin requests
  - `Controller.php` - Base controller for Backend
  - `AdminAuthController.php` - Separate admin login/logout
  - `DashboardController.php` - Admin dashboard with system statistics
  - `UserController.php` - Admin user management (CRUD, role assignment)
  - `StockController.php` - Admin stock management (list, update prices)
  - `NewsController.php` - Admin news management (list, update RSS)
  - `PortfolioController.php` - Admin portfolio management (list, toggle status, destroy)
  - `TimelineController.php` - Admin timeline/activity log viewer

- **Services**: `app/Backend/Services/` - *(empty, business logic lives in models/controllers for now)*
- **Repositories**: `app/Backend/Repositories/` - *(empty)*
- **Interfaces**: `app/Backend/Interfaces/` - *(empty)*

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

### Middleware
- `app/Http/Middleware/AdminAccess.php` - Blocks non-backend users; registered as alias `admin` in `bootstrap/app.php`

### Console Commands
- `app/Console/Commands/SyncStockData.php` - Artisan command to sync stock symbols/details from Python
- `app/Console/Commands/SyncStockPrices.php` - Artisan command to sync historical stock prices
- `app/Console/Commands/RegisterVnstockApiKey.php` - Register vnstock API key via Python script

### Jobs
- `app/Jobs/ProcessStockPriceSync.php` - Queued job for async stock price synchronization

### Routes (`routes/web.php`)
- **Public**: homepage, stock index/compare, exchange rate, AI chat/predict, stock search
- **Auth** (throttled): login, register, logout
- **Email Verification** (`auth` middleware): verify email, resend
- **User Protected** (`auth` + `verified`): profile, portfolio CRUD
- **Admin** (`/admin` prefix, `admin` middleware): dashboard, users, stocks, news, portfolios, timeline

### Views (`resources/views/`)
- **Frontend**: `index.blade.php`, `stock/`, `exchange_rate/`, `portfolio/`, `profile/`, `auth/`
- **Backend (admin)**: `backend/dashboard/`, `backend/users/`, `backend/stocks/`, `backend/news/`, `backend/portfolios/`, `backend/timeline/`, `backend/auth/`, `backend/layouts/`
- **Shared**: `layouts/`, `partials/`

### Python Scripts (`py/`)
- `get_stock.py` - Fetch historical price data for one or more symbols (via vnstock, VCI source, 1 year)
- `get_exchange_rate.py` - Fetch VCB exchange rates by date or last N days
- `get_hot_industries.py` - Fetch hot industry stocks (Banking, Real Estate, IT)
- `get_stock_list.py` - Fetch full list of stock symbols from vnstock
- `register_api_key.py` - Register/configure vnstock API key

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

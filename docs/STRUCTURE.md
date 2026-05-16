# Project Structure & Architecture

## Overview
This is a Laravel stock application with clear separation between Frontend and Backend.

## Directory Structure

### Frontend (User Interface)
- **Controllers**: `app/Frontend/Controllers/` - Handle requests from regular users
  - `StockController.php` - Manage homepage, view stock charts
  - `AuthController.php` - User login/registration/logout with validation
  - `ProfileController.php` - User profile management (show/edit/update)
  - `PortfolioController.php` - Portfolio management (CRUD portfolios & stocks)
  - `ExchangeRateController.php` - View exchange rates
  - `AiController.php` - AI market prediction

- **Services**: `app/Frontend/Services/` - Business logic for Frontend
  - `StockService.php` - Call Python scripts to fetch stock data
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
  - Define interfaces for Repository and Service classes

### Backend (Administration)
- **Controllers**: `app/Backend/Controllers/` - Handle admin requests
  - `AdminAuthController.php` - Admin authentication
  - `DashboardController.php` - Admin dashboard

- **Services**: `app/Backend/Services/` - Business logic for Backend
- **Repositories**: `app/Backend/Repositories/` - Database access for Backend
- **Interfaces**: `app/Backend/Interfaces/` - Contracts for Backend

### Models (Shared)
- `app/Models/` - Eloquent models shared between Frontend/Backend
  - `Stock.php`, `StockPrice.php`, `StockSymbol.php` - Stock data
  - `ExchangeRate.php` - Exchange rate data
  - `User.php`, `UserProfile.php` - User data
  - `Portfolio.php`, `PortfolioItem.php` - Portfolio management

### Routes
- `routes/web.php` - Define all routes
  - Frontend routes use `App\Frontend\Controllers`
  - Auth routes: login, register, logout with proper validation
  - Profile routes: show, edit, update (auth middleware required)
  - Portfolio routes: full CRUD + stock management (auth middleware required)
  - Backend (admin) routes use `App\Backend\Controllers`

### Views
- `resources/views/` - Blade templates
  - Frontend UI: homepage, login, stock charts
  - Backend UI: admin dashboard

### Python Scripts
- `py/` - Python scripts for external data fetching
  - `get_stock.py` - Fetch historical stock data
  - `get_exchange_rate.py` - Fetch exchange rates from banks
  - `get_hot_industries.py` - Fetch hot industries data

## Namespace Convention
- Frontend: `App\Frontend\{Controllers|Services|Repositories|Interfaces}`
- Backend: `App\Backend\{Controllers|Services|Repositories|Interfaces}`
- Models: `App\Models`

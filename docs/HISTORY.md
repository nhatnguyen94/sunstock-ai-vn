# Feature Update History

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

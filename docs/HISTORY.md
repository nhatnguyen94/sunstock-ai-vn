# Feature Update History

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

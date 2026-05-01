# AGENTS.md - Stock App Project Structure Guide

## Overview
This is a Laravel stock application with clear separation between Frontend and Backend.

## Directory Structure

### Frontend (User Interface)
- **Controllers**: `app/Frontend/Controllers/` - Handle requests from regular users
  - `StockController.php` - Manage homepage, view stock charts
  - `AuthController.php` - User login/registration
  - `ExchangeRateController.php` - View exchange rates
  - `AiController.php` - AI market prediction

- **Services**: `app/Frontend/Services/` - Business logic for Frontend
  - `StockService.php` - Call Python scripts to fetch stock data
  - `ExchangeRateService.php` - Handle exchange rate data
  - `AiService.php` - AI chat/prediction integration
  - `NewsService.php` - RSS news from VnExpress

- **Repositories**: `app/Frontend/Repositories/` - Database access for Frontend
  - `StockRepository.php` - CRUD operations for stock data
  - `ExchangeRateRepository.php` - CRUD operations for exchange rate data
  - `UserProfileRepository.php` - CRUD operations for user profiles

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

### Routes
- `routes/web.php` - Define all routes
  - Frontend routes use `App\Frontend\Controllers`
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

## Development Rules

1. **Frontend**: For regular users to view stocks, exchange rates, AI chat
2. **Backend**: For admin system management
3. **Models**: Shared between Frontend/Backend, no separation
4. **Dependency Injection**: Use Interface and Repository pattern
5. **Service Binding**: Define bindings in `AppServiceProvider.php`

## Namespace Convention
- Frontend: `App\Frontend\{Controllers|Services|Repositories|Interfaces}`
- Backend: `App\Backend\{Controllers|Services|Repositories|Interfaces}`
- Models: `App\Models`

## When Adding New Features

### Frontend (Users)
1. Create Controller in `app/Frontend/Controllers/`
2. Create Service in `app/Frontend/Services/` (if needed)
3. Create Repository and Interface (if needed)
4. Register binding in `AppServiceProvider`
5. Add routes to `web.php` with Frontend namespace

### Backend (Admin)
1. Create Controller in `app/Backend/Controllers/`
2. Create Service in `app/Backend/Services/` (if needed)
3. Create Repository and Interface (if needed)
4. Add admin routes with auth and permission middleware
5. Create views in `resources/views/backend/`

## Important Notes
- Frontend and Backend are completely separated in namespace and directories
- Models are shared between Frontend/Backend
- Use Repository pattern and Dependency Injection
- Backend requires authentication and permission middleware

## 🤖 AI Development Guidelines

**MANDATORY**: When implementing new features, AI agents MUST:

### 📝 Documentation Requirements
1. **UPDATE THIS FILE** after adding any new feature
2. **Document new files** in the appropriate section above
3. **Update namespace mappings** if new patterns are introduced
4. **Record dependencies** and service bindings added

### 🔄 Feature Implementation Checklist
When adding new features, always:
- [ ] Follow the established Frontend/Backend separation
- [ ] Use correct namespace convention
- [ ] Add proper Interface definitions
- [ ] Register service bindings in AppServiceProvider
- [ ] Update this AGENTS.md file with new components
- [ ] Test that routes work correctly with `php artisan route:list`

### 📋 Update Template
When updating this file, use this format:
```
## Feature Update: [FEATURE_NAME] - [DATE]
### Added:
- **Controller**: `[path]` - [description]
- **Service**: `[path]` - [description] 
- **Repository**: `[path]` - [description]
- **Interface**: `[path]` - [description]
- **Routes**: [route descriptions]
- **Views**: [view descriptions]

### Modified:
- **AppServiceProvider**: [binding changes]
- **Routes**: [route changes]
- **Other**: [other modifications]
```

### ⚠️ Critical Rules
- **NEVER** create files in old `app/Http/Controllers`, `app/Services`, `app/Repositories`, `app/Interfaces` 
- **ALWAYS** use `App\Frontend\*` or `App\Backend\*` namespaces
- **ALWAYS** run `composer dump-autoload` after namespace changes
- **ALWAYS** verify with `php artisan route:list` after route changes
- **DOCUMENT IMMEDIATELY** - Don't postpone documentation updates

This ensures project consistency and helps future AI interactions understand the current state.

## 🧠 AI Behavioral Guidelines

**CRITICAL**: Follow these guidelines to reduce common LLM coding mistakes. These bias toward caution over speed.

### 1. Think Before Coding
Don't assume. Don't hide confusion. Surface tradeoffs.

**Before implementing:**
- State your assumptions explicitly. If uncertain, ask.
- If multiple interpretations exist, present them - don't pick silently.
- If a simpler approach exists, say so. Push back when warranted.
- If something is unclear, stop. Name what's confusing. Ask.

### 2. Simplicity First
Minimum code that solves the problem. Nothing speculative.

- No features beyond what was asked.
- No abstractions for single-use code.
- No "flexibility" or "configurability" that wasn't requested.
- No error handling for impossible scenarios.
- If you write 200 lines and it could be 50, rewrite it.
- Ask yourself: "Would a senior engineer say this is overcomplicated?" If yes, simplify.

### 3. Surgical Changes
Touch only what you must. Clean up only your own mess.

**When editing existing code:**
- Don't "improve" adjacent code, comments, or formatting.
- Don't refactor things that aren't broken.
- Match existing style, even if you'd do it differently.
- If you notice unrelated dead code, mention it - don't delete it.

**When your changes create orphans:**
- Remove imports/variables/functions that YOUR changes made unused.
- Don't remove pre-existing dead code unless asked.

**The test:** Every changed line should trace directly to the user's request.

### 4. Goal-Driven Execution
Define success criteria. Loop until verified.

**Transform tasks into verifiable goals:**
- "Add validation" → "Write tests for invalid inputs, then make them pass"
- "Fix the bug" → "Write a test that reproduces it, then make it pass"
- "Refactor X" → "Ensure tests pass before and after"

**For multi-step tasks, state a brief plan:**
```
1. [Step] → verify: [check]
2. [Step] → verify: [check]  
3. [Step] → verify: [check]
```

Strong success criteria let you loop independently. Weak criteria ("make it work") require constant clarification.

**These guidelines are working if:** fewer unnecessary changes in diffs, fewer rewrites due to overcomplication, and clarifying questions come before implementation rather than after mistakes.

## Feature Update: CLEANUP_OLD_FILES - May 1, 2026
### Removed:
- **Old Controllers**: `app/Http/Controllers/` - Removed duplicate controllers (StockController, AuthController, ExchangeRateController, AiController)
- **Old Services**: `app/Services/` - Removed duplicate services (StockService, ExchangeRateService, AiService, NewsService) 
- **Old Repositories**: `app/Repositories/` - Removed duplicate repositories (StockRepository, ExchangeRateRepository, UserProfileRepository)
- **Old Interfaces**: `app/Interfaces/` - Removed duplicate interfaces (StockRepositoryInterface, ExchangeRateRepositoryInterface, NewsServiceInterface, UserProfileRepositoryInterface)

### Modified:
- **Base Controllers**: Created proper base Controller classes for Frontend and Backend extending Illuminate\Routing\Controller
- **Controller Inheritance**: All controllers now properly extend their respective base Controller (Laravel standard)
- **Autoloader**: Updated with `composer dump-autoload` - optimized to 6518 classes
- **Directory Structure**: Cleaned up old namespaces, removed unnecessary Http/Controllers folder

### Notes:
- All controllers now properly extend base Controller class (Laravel 11 standard)
- Frontend controllers extend App\Frontend\Controllers\Controller
- Backend controllers extend App\Backend\Controllers\Controller  
- All routes tested and working correctly with `php artisan route:list`
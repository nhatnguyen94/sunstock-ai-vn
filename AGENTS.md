# AGENTS.md - Stock App Project Structure Guide

## 📋 Quick Reference
- **Project Type**: Laravel Stock Application 
- **Architecture**: Frontend/Backend Separation
- **Frontend Namespace**: `App\Frontend\{Controllers|Services|Repositories|Interfaces}`
- **Backend Namespace**: `App\Backend\{Controllers|Services|Repositories|Interfaces}`
- **Shared**: `App\Models` (used by both Frontend/Backend)
- **Critical Rule**: NEVER use old `app/Http/Controllers`, `app/Services`, `app/Repositories`, `app/Interfaces`

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

## Development Rules

1. **Frontend**: For regular users to view stocks, exchange rates, AI chat
2. **Backend**: For admin system management
3. **Models**: Shared between Frontend/Backend, no separation
4. **Dependency Injection**: Use Interface and Repository pattern
5. **Service Binding**: Define bindings in `AppServiceProvider.php`

## Important Notes
- Frontend and Backend are completely separated in namespace and directories
- Models are shared between Frontend/Backend
- Use Repository pattern and Dependency Injection
- Backend requires authentication and permission middleware

## Important Notes
- Frontend and Backend are completely separated in namespace and directories
- Models are shared between Frontend/Backend
- Use Repository pattern and Dependency Injection
- Backend requires authentication and permission middleware

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

## ⚡ Quick Checklist for AI
When adding ANY new feature:
- [ ] Use correct `App\Frontend\*` or `App\Backend\*` namespace
- [ ] Create Interface for Repository/Service
- [ ] Register binding in `AppServiceProvider`
- [ ] Run `composer dump-autoload`
- [ ] Test with `php artisan route:list`
- [ ] Update this documentation immediately

## 🤖 AI Development Guidelines

**MANDATORY**: When implementing new features, AI agents MUST:

### 📝 Documentation & Verification Requirements
1. **UPDATE THIS FILE** after adding any new feature
2. **Document new files** in the appropriate section above
3. **Register service bindings** in AppServiceProvider
4. **Test routes** with `php artisan route:list`
5. **Run** `composer dump-autoload` after namespace changes

### 🔄 Feature Implementation Checklist
- [ ] Follow Frontend/Backend separation
- [ ] Use correct namespace convention
- [ ] Add proper Interface definitions
- [ ] Update this AGENTS.md file with new components

### ⚠️ Critical Rules
- **NEVER** create files in old namespaces (`app/Http/Controllers`, `app/Services`, `app/Repositories`, `app/Interfaces`)
- **ALWAYS** use `App\Frontend\*` or `App\Backend\*` namespaces
- **DOCUMENT IMMEDIATELY** - Don't postpone documentation updates

### 📋 Update Template
```
## Feature Update: [FEATURE_NAME] - [DATE]
### Added:
- **Controller**: `[path]` - [description]
- **Service**: `[path]` - [description] 
- **Repository**: `[path]` - [description]
- **Interface**: `[path]` - [description]
- **Routes**: [route descriptions]

### Modified:
- **AppServiceProvider**: [binding changes]
- **Routes**: [route changes]
```

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

## Feature Update History

## Feature Update: PORTFOLIO_MANAGEMENT_FEATURE - January 2025
### Added:
- **Models**: 
  - `app/Models/Portfolio.php` - Portfolio management with relationships and calculations
  - `app/Models/PortfolioItem.php` - Individual stock holdings with P&L calculations
- **Database**: 
  - `portfolios` table with user relationships and portfolio stats
  - `portfolio_items` table with stock details, prices, targets, and stop losses
- **Controller**: `app/Frontend/Controllers/PortfolioController.php` - Full CRUD portfolio management
- **Service**: `app/Frontend/Services/PortfolioService.php` - Business logic for portfolio operations
- **Repository**: `app/Frontend/Repositories/PortfolioRepository.php` - Database operations with analytics
- **Interface**: `app/Frontend/Interfaces/PortfolioRepositoryInterface.php` - Repository contract
- **Views**: 
  - `portfolio/index.blade.php` - Portfolio dashboard with statistics
  - `portfolio/show.blade.php` - Detailed portfolio view with holdings
  - `portfolio/create.blade.php` - Create new portfolio form
  - `portfolio/edit.blade.php` - Edit portfolio settings
  - `portfolio/add-stock.blade.php` - Add stocks to portfolio
- **Routes**: 13 portfolio routes with authentication middleware
- **Navigation**: Added portfolio navigation to authenticated users

### Features:
- **Portfolio CRUD**: Create, read, update, delete portfolios
- **Stock Management**: Add/remove stocks with quantity, buy price, targets
- **Real-time P&L**: Automatic profit/loss calculations and percentages
- **Portfolio Analytics**: Holdings allocation, statistics, alerts
- **Price Alerts**: Target price and stop loss notifications
- **Rebalance Suggestions**: AI-powered portfolio rebalancing recommendations
- **Portfolio Dashboard**: Overview of all portfolios with total statistics

### Modified:
- **User Model**: Added portfolio relationships (hasMany portfolios)
- **AppServiceProvider**: Added PortfolioRepositoryInterface binding
- **Navigation**: Added portfolio link for authenticated users
- **Routes**: Added comprehensive portfolio route group with middleware

### Verified:
- **Routes**: All 13 portfolio routes tested with `php artisan route:list`
- **Migrations**: Successfully created portfolios and portfolio_items tables
- **Autoloader**: Updated to 6525 classes, all portfolio classes properly loaded
- **Authentication**: All portfolio routes protected with auth middleware

## Feature Update: AUTHENTICATION_SYSTEM - January 2025
### Added:
- **Controller**: `app/Frontend/Controllers/ProfileController.php` - User profile management (show/edit/update)
- **Views**: 
  - `resources/views/profile/show.blade.php` - Display user profile with responsive design
  - `resources/views/profile/edit.blade.php` - Edit profile form with password change
- **Routes**: Profile routes with auth middleware (profile.show, profile.edit, profile.update)
- **Navigation**: Added profile link to user dropdown menu in navbar

### Modified:
- **AuthController**: Enhanced with comprehensive validation, security features, session management
- **Layout**: Updated navbar to include profile navigation for authenticated users
- **Routes**: Added profile routes group with authentication middleware

### Verified:
- **Routes**: All auth and profile routes tested with `php artisan route:list`
- **Autoloader**: Updated to 6519 classes, all controllers properly loaded
- **Authentication Flow**: Complete login/register/profile/logout functionality working

## Feature Update: RBAC_SYSTEM_MULTIPLE_ROLES_AND_SEPARATE_AUTH - January 3, 2025
### Added:
- **Multiple Roles Support**: 
  - Updated User model methods to support multiple roles per user (assignRole, removeRole, syncRoles, getRoleNames)
  - Modified user creation/editing validation to accept roles[] array
  - Enhanced AdminAuthController with comprehensive admin authentication flow
- **Separate Authentication Systems**:
  - Created dedicated admin login at `/admin/login` route
  - AdminAccess middleware now redirects to admin.login instead of regular login
  - Separate admin logout functionality to prevent session conflicts
- **Enhanced User Management**:
  - Multiple role selection UI with professional checkbox cards in create/edit views
  - Role badges with color coding (Admin=red, Webadmin=blue, AdminSupport=yellow, User=gray)
  - Enhanced user index view already supported multiple roles display
- **Professional Admin Login**:
  - Standalone admin login page with Tabler UI design
  - Password visibility toggle, remember me functionality
  - Proper error handling and flash messages
  - Direct link back to frontend website

### Features:
- **True Multiple Roles**: Users can now have multiple roles simultaneously (e.g., admin + webadmin)
- **Role Management UI**: Professional checkbox-based role selection with descriptions
- **Separated Login Systems**: Admin login at `/admin/login`, regular user login at `/login`
- **Enhanced Security**: AdminAccess middleware prevents unauthorized access to admin area
- **Flexible Role Assignment**: syncRoles() method for complete role replacement, assignRole()/removeRole() for incremental changes
- **User Experience**: Clear role badges, intuitive interface, responsive design

### Modified:
- **User Model**: Added true multiple roles support methods (assignRole supports string|array, added syncRoles, getRoleNames)
- **UserController**: Updated validation to accept roles[] array, replaced single role sync with syncRoles method
- **AdminAuthController**: Complete rewrite with proper authentication flow, backend access validation, session security
- **AdminAccess Middleware**: Changed redirect from login to admin.login route
- **Admin Layout**: Updated logout forms to use admin.logout route instead of regular logout
- **User Views**: 
  - create.blade.php: Replaced single select with professional checkbox UI
  - edit.blade.php: Updated to show current roles with multiple selection support
  - index.blade.php: Already supported multiple roles display with foreach loop

### Technical Implementation:
- **Database**: Existing many-to-many relationship working perfectly
- **Validation**: Changed from 'role' => 'required|exists:roles,name' to 'roles' => 'required|array|min:1', 'roles.*' => 'exists:roles,name'
- **Role Assignment**: 
  - CREATE: `$user->syncRoles($request->roles)`
  - UPDATE: `$user->syncRoles($request->roles)`
  - DISPLAY: `$user->roles->pluck('name')->toArray()` in edit view
- **Authentication Flow**:
  - Admin login: /admin/login → validate credentials → check canAccessBackend() → redirect to admin.dashboard
  - Admin logout: /admin/logout → invalidate session → redirect to admin.login
  - Middleware protection: AdminAccess checks Auth::check() and user->canAccessBackend()

### Verified:
- **Routes**: All 28 admin routes verified with `php artisan route:list | findstr admin`
- **Autoloader**: Updated to 6534 classes with `composer dump-autoload`
- **Server**: Running on localhost:8000 successfully
- **UI Components**: Professional role selection UI with Tabler styling
- **Role Logic**: Multiple roles per user supported at database and application level

### Benefits:
- **Scalability**: Users can have multiple roles for complex permission scenarios
- **Security**: Separated authentication prevents admin/user login conflicts
- **User Experience**: Clear role management interface with visual role badges
- **Maintainability**: Clean separation between admin and user authentication flows
- **Future-Proof**: Architecture supports easy addition of new roles and permissions

## Feature Update: RBAC_SYSTEM_AND_ADMIN_DASHBOARD - May 2, 2026
### Added:
- **Database**: 
  - `roles` table với name (unique slug) và display_name
  - `role_user` pivot table với cascade on delete
- **Models**: 
  - `app/Models/Role.php` - Role model với constants và helper methods
  - Cập nhật `app/Models/User.php` với roles relationship và helper methods (hasRole, hasAnyRole, canAccessBackend, assignRole, removeRole)
- **Migrations**: 
  - `2026_05_02_024713_create_roles_table.php` - Tạo bảng roles
  - `2026_05_02_024724_create_role_user_table.php` - Tạo bảng trung gian role_user
- **Seeder**: 
  - `app/Models/RoleSeeder.php` - Khởi tạo 4 role: Admin, Webadmin, AdminSupport, User
  - Cập nhật `DatabaseSeeder.php` để chạy RoleSeeder
- **Middleware**: 
  - `app/Http/Middleware/AdminAccess.php` - Chặn user không có quyền backend
  - Đăng ký middleware 'admin' trong `bootstrap/app.php`
- **Authorization**: 
  - Gates trong `AppServiceProvider.php`: manage-users (Admin only), manage-features (Admin+AdminSupport), view-timeline (Admin+Webadmin+AdminSupport), access-backend
- **Backend Controllers**: 
  - `app/Backend/Controllers/DashboardController.php` - Trang dashboard với thống kê
  - `app/Backend/Controllers/UserController.php` - CRUD users (Admin only)
  - `app/Backend/Controllers/TimelineController.php` - Xem timeline (3 role backend)
  - `app/Backend/Controllers/StockController.php` - Quản lý stocks (Admin+AdminSupport)
  - `app/Backend/Controllers/NewsController.php` - Quản lý news (Admin+AdminSupport)
  - `app/Backend/Controllers/PortfolioController.php` - Quản lý portfolios (Admin+AdminSupport)
- **Admin Layout**: 
  - `resources/views/layouts/admin.blade.php` - Layout admin với Tabler UI, sidebar động, breadcrumbs, flash messages
- **Routes**: 
  - Admin routes group với middleware 'admin'
  - Phân quyền routes theo Gates
- **Authentication**: 
  - Cập nhật `AuthController.php` để tự động gán role 'user' sau register

### Features:
- **RBAC System**: Hệ thống phân quyền 4 role với many-to-many relationship
- **Admin Dashboard**: Giao diện admin chuyên nghiệp với Tabler UI
- **Permission Gates**: Phân quyền chi tiết với Laravel Gates
- **Middleware Protection**: Bảo vệ backend với AdminAccess middleware
- **Role Management**: Helper methods cho User model
- **Auto Role Assignment**: Tự động gán role 'user' khi đăng ký

### Roles & Permissions:
- **Admin**: Toàn quyền (manage-users, manage-features, view-timeline, access-backend)
- **Webadmin**: Chỉ xem timeline (view-timeline, access-backend)
- **AdminSupport**: Quản lý tính năng nhưng không được sửa user (manage-features, view-timeline, access-backend)
- **User**: Role mặc định (không có quyền backend)

### UI Features:
- **Responsive Design**: Sidebar và layout responsive
- **Dynamic Menu**: Menu sidebar sử dụng @can directive
- **Professional Design**: Tabler UI với theme tài chính
- **Flash Messages**: Thông báo tự động ẩn
- **Breadcrumbs**: Navigation breadcrumb
- **User Dropdown**: Info user với role badges

### Modified:
- **bootstrap/app.php**: Đăng ký middleware AdminAccess
- **routes/web.php**: Thêm admin routes group với middleware và phân quyền
- **AppServiceProvider**: Thêm Gates cho authorization
- **User Model**: Thêm relationships và helper methods
- **AuthController**: Auto assign role khi register
- **DatabaseSeeder**: Include RoleSeeder

### Verified:
- **Migrations**: Chạy thành công tạo tables roles và role_user
- **Seeder**: Tạo thành công 4 roles với display_name tiếng Việt
- **Routes**: Tất cả admin routes hoạt động với middleware phân quyền
- **Authorization**: Gates hoạt động đúng theo role
- **UI**: Layout admin responsive với Tabler UI chuyên nghiệp

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
- All controllers now properly extend base Controller class (Laravel 12 standard)
- Frontend controllers extend App\Frontend\Controllers\Controller
- Backend controllers extend App\Backend\Controllers\Controller  
- All routes tested and working correctly with `php artisan route:list`

---

**This file ensures project consistency and helps future AI interactions understand the current state and established patterns.**
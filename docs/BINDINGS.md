# DI Bindings & Gates

> All bindings are defined in `app/Providers/AppServiceProvider.php`

## Interface → Implementation Bindings (`register()`)

| Interface | Implementation | Usage |
|---|---|---|
| `App\Frontend\Interfaces\StockRepositoryInterface` | `App\Frontend\Repositories\StockRepository` | Injected into `StockController` (Frontend) |
| `App\Frontend\Interfaces\ExchangeRateRepositoryInterface` | `App\Frontend\Repositories\ExchangeRateRepository` | Injected into `ExchangeRateController` |
| `App\Frontend\Interfaces\NewsRepositoryInterface` | `App\Frontend\Repositories\NewsRepository` | Injected into `App\Frontend\Services\NewsService` |
| `App\Frontend\Interfaces\NewsServiceInterface` | `App\Frontend\Services\NewsService` | Injected into `StockController@home`, `NewsController` (Frontend) |
| `App\Frontend\Interfaces\UserProfileRepositoryInterface` | `App\Frontend\Repositories\UserProfileRepository` | Injected into `ProfileController` |
| `App\Frontend\Interfaces\PortfolioRepositoryInterface` | `App\Frontend\Repositories\PortfolioRepository` | Injected into `PortfolioController` |
| `App\Frontend\Interfaces\CompanyFinancialRepositoryInterface` | `App\Frontend\Repositories\CompanyFinancialRepository` | Injected into `CompanyFinancialService` |
| `App\Frontend\Interfaces\CompanyProfileRepositoryInterface` | `App\Frontend\Repositories\CompanyProfileRepository` | Injected into `CompanyProfileService` |
| `App\Frontend\Interfaces\FundRepositoryInterface` | `App\Frontend\Repositories\FundRepository` | Injected into `FundService` |
| `App\Frontend\Interfaces\GoldPriceRepositoryInterface` | `App\Frontend\Repositories\GoldPriceRepository` | Injected into `GoldPriceService` |
| `App\Frontend\Interfaces\MarketSnapshotRepositoryInterface` | `App\Frontend\Repositories\MarketSnapshotRepository` | Injected into `MarketOverviewService` |
| `App\Frontend\Interfaces\WatchlistRepositoryInterface` | `App\Frontend\Repositories\WatchlistRepository` | Injected into `WatchlistService` |
| `App\Frontend\Interfaces\PortfolioTransactionRepositoryInterface` | `App\Frontend\Repositories\PortfolioTransactionRepository` | Injected into `PortfolioLedgerService` |
| `App\Backend\Interfaces\NewsRepositoryInterface` | `App\Backend\Repositories\NewsRepository` | Injected into `App\Backend\Services\NewsService` (Admin) |
| `App\Backend\Interfaces\NewsServiceInterface` | `App\Backend\Services\NewsService` | Injected into `App\Backend\Controllers\NewsController` (Admin) |
| `App\Backend\Interfaces\StockRepositoryInterface` | `App\Backend\Repositories\StockRepository` | Injected into `App\Backend\Services\StockService` (Admin) |
| `App\Backend\Interfaces\StockServiceInterface` | `App\Backend\Services\StockService` | Injected into `App\Backend\Controllers\StockController` (Admin) |
| `App\Backend\Interfaces\UserRepositoryInterface` | `App\Backend\Repositories\UserRepository` | Injected into `App\Backend\Services\UserService` (Admin) |
| `App\Backend\Interfaces\UserServiceInterface` | `App\Backend\Services\UserService` | Injected into `App\Backend\Controllers\UserController` (Admin) |
| `App\Backend\Interfaces\ActivityLogRepositoryInterface` | `App\Backend\Repositories\ActivityLogRepository` | Injected into `TimelineController`, `DashboardController` (Admin) |
| `App\Backend\Interfaces\RoleRepositoryInterface` | `App\Backend\Repositories\RoleRepository` | Injected into `App\Backend\Services\RoleService` (Admin) |
| `App\Backend\Interfaces\RoleServiceInterface` | `App\Backend\Services\RoleService` | Injected into `App\Backend\Controllers\RoleController` (Admin) |
| `App\Backend\Interfaces\PermissionRepositoryInterface` | `App\Backend\Repositories\PermissionRepository` | Injected into `App\Backend\Services\PermissionService` (Admin) |
| `App\Backend\Interfaces\PermissionServiceInterface` | `App\Backend\Services\PermissionService` | Injected into `App\Backend\Controllers\PermissionController` (Admin) |

> **Note**: `StockService`, `AiService`, `ExchangeRateService`, `PortfolioService`, `CompanyFinancialService`, `CompanyProfileService`, `FundService`, `GoldPriceService`, `MarketOverviewService`, `WatchlistService`, `PortfolioLedgerService`, `StockPriceFreshness` are **not** bound via interfaces — they are injected directly as concrete classes.

## Adding a New Binding

1. Create `app/Frontend/Interfaces/MyRepositoryInterface.php`
2. Create `app/Frontend/Repositories/MyRepository.php` implementing the interface
3. Add to `AppServiceProvider::register()`:
```php
$this->app->bind(
    \App\Frontend\Interfaces\MyRepositoryInterface::class,
    \App\Frontend\Repositories\MyRepository::class
);
```
4. Run `composer dump-autoload`

## Gate Definitions (`boot()`)

There is no per-ability `Gate::define()` anymore. A single `Gate::before()` hook resolves every ability against the DB-driven permission system (`App\Models\Permission` + `permission_role` pivot — see `docs/RBAC.md`):

```php
Gate::before(function ($user, string $ability) {
    return $user->hasPermission($ability);
});
```

`PermissionSeeder` reproduces the same 6 abilities that used to be hardcoded here (`manage-users`, `manage-features`, `view-timeline`, `access-backend`, plus the new `manage-roles`/`manage-permissions`), attached to the same 4 roles as before. New abilities are added purely through the backend UI (**Admin > Vai trò / Quyền hạn**) — no code change to this file needed.

## Using Gates in Controllers

```php
// Throws 403 if unauthorized
Gate::authorize('manage-users');

// Check and handle manually
if (Gate::denies('manage-features')) {
    abort(403);
}

// In controller using AuthorizesRequests trait
$this->authorize('view-timeline');
```

## Middleware Alias

Registered in `bootstrap/app.php`:
```php
$middleware->alias([
    'admin' => \App\Http\Middleware\AdminAccess::class,
]);
```

`AdminAccess` checks `Auth::user()->canAccessBackend()` directly — this is a separate, still-hardcoded (role-name) coarse check, independent of the `access-backend` permission/Gate. See "Two-layer authorization" in `docs/RBAC.md`.

> **Explicit closure binding — `PortfolioService`**: Laravel does **not** resolve `?Service $x = null` constructor parameters (it just uses the default), so `PortfolioService`'s optional `CompanyProfileService` and `PortfolioLedgerService` used to be `null` in production (the portfolio's upcoming-events list was always empty and no ledger was written). `AppServiceProvider` therefore binds it with a closure that passes all four collaborators; unit tests keep constructing it with just the two repositories. Any future optional collaborator needs the same treatment (regression test: `PortfolioLedgerServiceTest::test_the_container_gives_portfolio_service_its_optional_collaborators`).

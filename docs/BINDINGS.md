# DI Bindings & Gates

> All bindings are defined in `app/Providers/AppServiceProvider.php`

## Interface → Implementation Bindings (`register()`)

| Interface | Implementation | Usage |
|---|---|---|
| `App\Frontend\Interfaces\StockRepositoryInterface` | `App\Frontend\Repositories\StockRepository` | Injected into `StockController` |
| `App\Frontend\Interfaces\ExchangeRateRepositoryInterface` | `App\Frontend\Repositories\ExchangeRateRepository` | Injected into `ExchangeRateController` |
| `App\Frontend\Interfaces\NewsServiceInterface` | `App\Frontend\Services\NewsService` | Injected into `StockController@home` |
| `App\Frontend\Interfaces\UserProfileRepositoryInterface` | `App\Frontend\Repositories\UserProfileRepository` | Injected into `ProfileController` |
| `App\Frontend\Interfaces\PortfolioRepositoryInterface` | `App\Frontend\Repositories\PortfolioRepository` | Injected into `PortfolioController` |

> **Note**: `StockService`, `AiService`, `ExchangeRateService`, `PortfolioService` are **not** bound via interfaces — they are injected directly as concrete classes.

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

| Gate Name | Allowed Roles | Typical Usage |
|---|---|---|
| `manage-users` | `admin` | `UserController`, admin user management actions |
| `manage-features` | `admin`, `adminsupport` | `StockController`, `NewsController`, `PortfolioController` (admin side) |
| `view-timeline` | `admin`, `webadmin`, `adminsupport` | `TimelineController` |
| `access-backend` | `admin`, `webadmin`, `adminsupport` | General backend access check (used by `AdminAccess` middleware) |

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

`AdminAccess` checks `Auth::user()->canAccessBackend()` which relies on `access-backend` gate logic.

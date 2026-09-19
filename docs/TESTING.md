# Testing Guide

## Mandatory rule (see AGENTS.md)

Every feature you write or change — new or already existing — MUST have unit/feature tests, and you MUST run those tests before considering the task done. You do **not** need to run the full suite each time — only the **group** for the feature you touched.

## Folder structure — mirrors `app/`

Tests are split into `tests/Unit/` and `tests/Feature/`, and inside each, folders mirror the `app/` structure so a test is always easy to find next to the code it covers:

```
tests/
├── Unit/                                  # No Laravel app booted — plain PHPUnit + Mockery only
│   └── Frontend/
│       └── Services/
│           └── PortfolioServiceTest.php   # mirrors app/Frontend/Services/PortfolioService.php
│
└── Feature/                               # Laravel app IS booted (needed for facades: Cache, Notification, url(), artisan())
    ├── Frontend/
    │   └── Services/
    │       ├── PortfolioServiceTest.php           # mirrors app/Frontend/Services/PortfolioService.php
    │       └── CompanyFinancialServiceTest.php     # mirrors app/Frontend/Services/CompanyFinancialService.php
    ├── Notifications/
    │   └── PortfolioAlertNotificationTest.php      # mirrors app/Notifications/PortfolioAlertNotification.php
    └── Console/
        └── Commands/
            └── SyncPortfolioPricesTest.php         # mirrors app/Console/Commands/SyncPortfolioPrices.php
```

**Rule of thumb for Unit vs Feature** (this is what decides the folder, not personal preference):
- **`tests/Unit/...`** — the code under test does NOT call any Laravel facade (`Cache::`, `Notification::`, `now()`†, `url()`, `route()`...) and does NOT need `$this->artisan(...)` or the container. Extends plain `PHPUnit\Framework\TestCase`.
- **`tests/Feature/...`** — anything else: the code calls a facade, sends a notification, renders a URL, or you need `$this->artisan(...)`. Extends `Tests\TestCase` (boots the full app). This does **not** mean it touches the database — see below.

† `now()` is fine to use *inside a test* to build fixture data even in a Unit test (it's just `\Carbon\Carbon::now()` under the hood when called directly), but if the **code under test** calls the `now()` *helper function*, that helper resolves through the `Date` facade, which needs a booted app.

## Prefer mocking over a real database — but `RefreshDatabase` does work now

`phpunit.xml` points `DB_CONNECTION` at `sqlite` / `:memory:`, and **`php artisan migrate` / `RefreshDatabase` now runs cleanly against it**: `database/migrations/2026_05_29_000001_partition_stock_prices_by_year.php` used to be raw MySQL-only SQL (`ALTER TABLE ... DROP FOREIGN KEY`, `PARTITION BY RANGE`), which halted every migration after it on SQLite. That migration is now a no-op on any driver other than `mysql` (`DB::connection()->getDriverName() !== 'mysql'` guard) — partitioning is a MySQL-only optimization, irrelevant at test data volumes, and production behavior is unchanged.

That said, **every test in this suite still mocks its `*RepositoryInterface` dependencies instead of using `RefreshDatabase`**, and that stays the default going forward — it's faster (no migration cost per test), and forces the Service under test to go through the Repository pattern (per `docs/GUIDELINES.md`) rather than touching Eloquent directly. Eloquent models (`PortfolioItem`, `Portfolio`, `CompanyFinancial`, `User`...) can still be constructed in plain PHP (`new PortfolioItem([...])`) and passed around — that's just object construction, no DB involved, as long as you never call `->save()`/`->find()`/etc. on them.

Reach for `RefreshDatabase` only when a test genuinely needs a real end-to-end DB round-trip (e.g. an HTTP route that queries several tables together, like `tests/Feature/ExampleTest.php`). If you do:
- Add `use RefreshDatabase;` to the test class — it migrates a fresh sqlite `:memory:` DB per test.
- **Seed only what the code path needs**, or you may unknowingly trigger a real external call. `tests/Feature/ExampleTest.php` is the cautionary example: hitting `/` with an otherwise-empty DB made `StockController::home()` fall through to a *real* Python/vnstock subprocess call for hot industries and exchange rates (each table's "first-run, DB is empty → fetch live" fallback) — the test still passed, just slowly (~12s) and with a live network dependency. Seeding one `HotIndustry` row and one `ExchangeRate` row (for today's date) avoids both fallbacks entirely; see that file for the pattern.

**A second, separate trap**: a real `$this->get(...)`/`$this->post(...)` HTTP request to **any route whose view extends `layouts.app`** touches the DB regardless of whether the route itself needs to, because `layouts/app.blade.php`'s navbar runs `NewsCategory::orderBy('name')->get()` directly in the Blade template on every page load. Without `RefreshDatabase`, that 500s (no such table); with it, it just runs against an empty (or seeded) `news_categories` table — no crash, but still a query you didn't ask for. Validation-failure tests are unaffected either way (a failed `$request->validate()` redirects with a 302, never rendering the layout). If you don't want `RefreshDatabase` for a "show me the page and assert 200" test, call the controller method directly (e.g. `(new SomeController())->showThing()`) and assert on the returned `View`'s name/data without calling `->render()` — see `tests/Feature/Frontend/Controllers/PasswordResetControllerTest.php` for that pattern.

## The `#[Group('featureName')]` convention

Every test method (or class) must be tagged with a PHPUnit Group attribute:

```php
use PHPUnit\Framework\Attributes\Group;

#[Group('portfolioAlerts')]
public function test_notification_sent_once_when_item_crosses_target_price(): void
{
    // ...
}
```

- **camelCase — mandatory, no exceptions.** `portfolioAlerts`, not `portfolio-alerts` or `portfolio_alerts` or `PortfolioAlerts`.
- One group name per feature, reused across every test method/class that belongs to it — regardless of which folder (Unit or Feature) they live in.
- Matches the feature name used in `docs/HISTORY.md` where practical (just camelCased instead of the doc's own casing there).

### Running a group

```bash
# Inside the php container (recommended — matches the app's real PHP/extension versions)
docker exec stock-app-php-1 php artisan test --group=portfolioAlerts

# Or, if running PHP directly on the host
php artisan test --group=portfolioAlerts
```

Run only the group for the feature you just touched. Don't run the whole suite unless the user explicitly asks for it.

## Current groups

| Group | Covers | Test files |
|---|---|---|
| `portfolioPrices` | `PortfolioService::fetchCurrentPrices()` sourcing real prices from `StockRepositoryInterface` (not `rand()`) | `tests/Unit/Frontend/Services/PortfolioServiceTest.php` |
| `portfolioAlerts` | Target/stop-loss crossing detection, one-shot notify + reset, `sync:portfolio-prices` command, `PortfolioAlertNotification` mail content | `tests/Feature/Frontend/Services/PortfolioServiceTest.php`, `tests/Feature/Notifications/PortfolioAlertNotificationTest.php`, `tests/Feature/Console/Commands/SyncPortfolioPricesTest.php` |
| `stockScreener` | `CompanyFinancialService::screenStocks()` — latest-year extraction, filtering, sorting | `tests/Feature/Frontend/Services/CompanyFinancialServiceTest.php` |
| `exchangeRate` | `ExchangeRateService` — parsing Python stdout (banner-noise regression), DB-first caching, Python fallback + persist | `tests/Unit/Frontend/Services/ExchangeRateServiceTest.php`, `tests/Feature/Frontend/Services/ExchangeRateServiceTest.php` |
| `auth` | `User`/`Role` role-name checks (`hasRole`/`hasAnyRole`/`canAccessBackend`), `AdminAccess` middleware (guest/non-backend/backend-role paths), forgot/reset-password validation + status messages | `tests/Unit/Models/UserTest.php`, `tests/Unit/Models/RoleTest.php`, `tests/Feature/Http/Middleware/AdminAccessTest.php`, `tests/Unit/Frontend/Controllers/PasswordResetControllerTest.php`, `tests/Feature/Frontend/Controllers/PasswordResetControllerTest.php` |
| `permissions` | DB-driven permission system: `Role::hasPermission()`/`User::hasPermission()`, the single `Gate::before()` hook (incl. an arbitrary/never-code-defined permission name resolving correctly), `RoleController`/`PermissionController` CRUD + destroy guards (system role, role still assigned to a user, core permission) | `tests/Unit/Models/RoleTest.php`, `tests/Unit/Models/UserTest.php`, `tests/Feature/Providers/GatesTest.php`, `tests/Feature/Backend/Controllers/RoleControllerTest.php`, `tests/Feature/Backend/Controllers/PermissionControllerTest.php` |
| `queueMonitor` | Custom queue dashboard: `manage-queue` gate, per-queue Redis stats, `failed_jobs` retry/delete/retry-all/delete-all (incl. 404 for unknown uuid, delete-all forbidden without permission), `stats` JSON includes processing/recent/processedToday; `QueueJobLogger`'s processing→completed/failed transitions + `queueSummary()` extraction for all 3 job types (via a hand-built fake `Illuminate\Contracts\Queue\Job`, not Mockery, since it needs a genuinely-serialized command for `extractSummary()` to unserialize); `queue-logs:prune` staleness/deletion thresholds | `tests/Feature/Backend/Controllers/QueueMonitorControllerTest.php`, `tests/Feature/Support/QueueJobLoggerTest.php`, `tests/Feature/Console/Commands/PruneQueueJobLogsTest.php` |
| `pythonRunner` | `App\Support\PythonRunner`: a fast script returns output/exit_code correctly, a hanging script actually gets killed at the configured ceiling (asserted via wall-clock elapsed time, not just the returned flag — proves the OS-level `timeout` really interrupts it), `runAndDecodeJson()` parsing (incl. returning `null` when the script timed out before printing anything), stderr never corrupts stdout/JSON parsing. Genuinely spawns a real Python process (`tests/Fixtures/python/sleep_and_print.py`) — the whole point is proving a real subprocess actually gets interrupted, which mocking `exec()` cannot verify | `tests/Feature/Support/PythonRunnerTest.php` |
| `adminAccount` | Self-service password change (`/admin/account`): reachable with no `manage-*` permission (any backend role), correct current password required, new password hashed correctly (not double-hashed by the `'hashed'` Eloquent cast), validation (min length, confirmation match) | `tests/Feature/Backend/Controllers/AccountControllerTest.php` |
| `newsCategories` | News Category CRUD (`manage-features` gate), auto-slug from name when not provided, unique name/slug validation, destroy guards: category has `news` rows, or its `id` is one of `NewsService::usedCategoryIds()` (referenced by the hardcoded RSS `SOURCES` — the `create_news_categories_table` migration itself seeds ids 1-4, exactly these) | `tests/Feature/Backend/Controllers/NewsCategoryControllerTest.php` |
| `portfolioTotals` | `Portfolio::calculateCurrentValue()`/`calculateTotalInvested()`/`recalculateTotals()` and the profit/loss accessors (incl. the empty-portfolio div-by-zero guard) | `tests/Unit/Models/PortfolioTest.php` |
| `profile` | `ProfileController::show()`/`edit()` degrade gracefully (no crash) when the authenticated user has no `UserProfile` row yet; `storeAvatar()` upload/replace/remove/keep-existing branches (`Storage::fake()`) | `tests/Feature/Frontend/Controllers/ProfileControllerTest.php` |

## Adding a new feature's tests

1. Find (or create) the folder under `tests/Unit/` or `tests/Feature/` that mirrors where the code lives in `app/`.
2. Name the test class `<ClassName>Test.php`, same name as the class under test.
3. Pick ONE **camelCase** group name for the feature; tag every test method for it with `#[Group('yourFeatureName')]`.
4. Mock every `*RepositoryInterface` dependency with Mockery — never let a test hit the database (see above).
5. Run just that group and make sure it's green before moving on: `docker exec stock-app-php-1 php artisan test --group=yourFeatureName`.
6. Add a row to the "Current groups" table above.

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

## Why tests here never touch a real database

`phpunit.xml` points `DB_CONNECTION` at `sqlite` / `:memory:`. That's fine for a fresh Laravel app, but **this project can't run `php artisan migrate` against SQLite**: `database/migrations/2026_05_29_000001_partition_stock_prices_by_year.php` uses raw MySQL-only SQL (`ALTER TABLE ... DROP FOREIGN KEY`, `PARTITION BY RANGE`), which SQLite doesn't support. So `RefreshDatabase` / `$this->artisan('migrate')` will blow up on that migration.

**Workaround used everywhere in this suite**: never touch the DB in a test. Every Service depends only on `*RepositoryInterface` contracts (per `docs/GUIDELINES.md`'s Repository pattern) — mock the interface with Mockery and the Service under test never issues a real query. Eloquent models (`PortfolioItem`, `Portfolio`, `CompanyFinancial`, `User`...) can still be constructed in plain PHP (`new PortfolioItem([...])`) and passed around — that's just object construction, no DB involved, as long as you never call `->save()`/`->find()`/etc. on them.

If a future feature genuinely needs to hit the database in a test, fix the partition migration to be SQLite-compatible first (or write a lightweight `Schema::create()` setup in the test itself for just the tables you need) — don't reach for `RefreshDatabase` and expect it to work today.

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

Run only the group for the feature you just touched. Don't run the whole suite unless the user explicitly asks for it (and remember `tests/Feature/ExampleTest.php` will fail regardless — see Known Issues below, it's unrelated to your change).

## Current groups

| Group | Covers | Test files |
|---|---|---|
| `portfolioPrices` | `PortfolioService::fetchCurrentPrices()` sourcing real prices from `StockRepositoryInterface` (not `rand()`) | `tests/Unit/Frontend/Services/PortfolioServiceTest.php` |
| `portfolioAlerts` | Target/stop-loss crossing detection, one-shot notify + reset, `sync:portfolio-prices` command, `PortfolioAlertNotification` mail content | `tests/Feature/Frontend/Services/PortfolioServiceTest.php`, `tests/Feature/Notifications/PortfolioAlertNotificationTest.php`, `tests/Feature/Console/Commands/SyncPortfolioPricesTest.php` |
| `stockScreener` | `CompanyFinancialService::screenStocks()` — latest-year extraction, filtering, sorting | `tests/Feature/Frontend/Services/CompanyFinancialServiceTest.php` |
| `exchangeRate` | `ExchangeRateService` — parsing Python stdout (banner-noise regression), DB-first caching, Python fallback + persist | `tests/Unit/Frontend/Services/ExchangeRateServiceTest.php`, `tests/Feature/Frontend/Services/ExchangeRateServiceTest.php` |

## Adding a new feature's tests

1. Find (or create) the folder under `tests/Unit/` or `tests/Feature/` that mirrors where the code lives in `app/`.
2. Name the test class `<ClassName>Test.php`, same name as the class under test.
3. Pick ONE **camelCase** group name for the feature; tag every test method for it with `#[Group('yourFeatureName')]`.
4. Mock every `*RepositoryInterface` dependency with Mockery — never let a test hit the database (see above).
5. Run just that group and make sure it's green before moving on: `docker exec stock-app-php-1 php artisan test --group=yourFeatureName`.
6. Add a row to the "Current groups" table above.

## Known issues (pre-existing, not caused by any of the above)

- `tests/Feature/ExampleTest.php` (Laravel's default scaffold test) fails: it hits `/`, which queries the `stocks` table, but no migrations have run against the in-memory SQLite DB (and can't — see "Why tests here never touch a real database"). This predates the groups above; not fixed as part of adding them since it's a pre-existing infra gap, not a regression.

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
| `portfolioPrices` | `PortfolioService` sourcing real quotes from `StockRepositoryInterface::getLatestQuotes()` (not `rand()`), converted from the feed's thousands-of-VND to whole VND via `App\Support\PriceUnit`; `PortfolioPerformance::series()` (buy-date gating, carry-forward, buy-price fallback, thinning); `StockRepository` quotes / close history / `ensureTracked()` (real SQL) | `tests/Unit/Frontend/Services/PortfolioServiceTest.php`, `tests/Unit/Support/PortfolioPerformanceTest.php`, `tests/Feature/Frontend/Repositories/StockRepositoryQuotesTest.php` |
| `portfolioAlerts` | Target/stop-loss crossing detection, one-shot notify + reset, `sync:portfolio-prices` command, `PortfolioAlertNotification` mail content | `tests/Feature/Frontend/Services/PortfolioServiceTest.php`, `tests/Feature/Notifications/PortfolioAlertNotificationTest.php`, `tests/Feature/Console/Commands/SyncPortfolioPricesTest.php` |
| `stockScreener` | `CompanyFinancialService::screenStocks()` — latest-year extraction, filtering, sorting | `tests/Feature/Frontend/Services/CompanyFinancialServiceTest.php` |
| `exchangeRate` | `ExchangeRateService` — parsing Python stdout (banner-noise regression), DB-first caching, Python fallback + persist | `tests/Unit/Frontend/Services/ExchangeRateServiceTest.php`, `tests/Feature/Frontend/Services/ExchangeRateServiceTest.php` |
| `auth` | `User`/`Role` role-name checks (`hasRole`/`hasAnyRole`/`canAccessBackend`), `AdminAccess` middleware (guest/non-backend/backend-role paths), forgot/reset-password validation + status messages | `tests/Unit/Models/UserTest.php`, `tests/Unit/Models/RoleTest.php`, `tests/Feature/Http/Middleware/AdminAccessTest.php`, `tests/Unit/Frontend/Controllers/PasswordResetControllerTest.php`, `tests/Feature/Frontend/Controllers/PasswordResetControllerTest.php` |
| `permissions` | DB-driven permission system: `Role::hasPermission()`/`User::hasPermission()`, the single `Gate::before()` hook (incl. an arbitrary/never-code-defined permission name resolving correctly), `RoleController`/`PermissionController` CRUD + destroy guards (system role, role still assigned to a user, core permission) | `tests/Unit/Models/RoleTest.php`, `tests/Unit/Models/UserTest.php`, `tests/Feature/Providers/GatesTest.php`, `tests/Feature/Backend/Controllers/RoleControllerTest.php`, `tests/Feature/Backend/Controllers/PermissionControllerTest.php` |
| `queueMonitor` | Custom queue dashboard: `manage-queue` gate, per-queue Redis stats, `failed_jobs` retry/delete/retry-all/delete-all (incl. 404 for unknown uuid, delete-all forbidden without permission), `stats` JSON includes processing/recent/processedToday; `QueueJobLogger`'s processing→completed/failed transitions + `queueSummary()` extraction for all 3 job types (via a hand-built fake `Illuminate\Contracts\Queue\Job`, not Mockery, since it needs a genuinely-serialized command for `extractSummary()` to unserialize); `queue-logs:prune` staleness/deletion thresholds | `tests/Feature/Backend/Controllers/QueueMonitorControllerTest.php`, `tests/Feature/Support/QueueJobLoggerTest.php`, `tests/Feature/Console/Commands/PruneQueueJobLogsTest.php` |
| `pythonRunner` | `App\Support\PythonRunner`: a fast script returns output/exit_code correctly, a hanging script actually gets killed at the configured ceiling (asserted via wall-clock elapsed time, not just the returned flag — proves the OS-level `timeout` really interrupts it), `runAndDecodeJson()` parsing (incl. returning `null` when the script timed out before printing anything), stderr never corrupts stdout/JSON parsing, and the **HOME override** (a process whose home is unwritable — PHP-FPM's www-data — gets a writable `HOME` so vnstock can create `~/.vnstock`; a writable HOME is left alone; `tests/Fixtures/python/print_home.py`). Genuinely spawns a real Python process (`tests/Fixtures/python/sleep_and_print.py`) — the whole point is proving a real subprocess actually gets interrupted, which mocking `exec()` cannot verify | `tests/Feature/Support/PythonRunnerTest.php` |
| `adminAccount` | Self-service password change (`/admin/account`): reachable with no `manage-*` permission (any backend role), correct current password required, new password hashed correctly (not double-hashed by the `'hashed'` Eloquent cast), validation (min length, confirmation match) | `tests/Feature/Backend/Controllers/AccountControllerTest.php` |
| `newsCategories` | News Category CRUD (`manage-features` gate), auto-slug from name when not provided, unique name/slug validation, destroy guards: category has `news` rows, or its `id` is one of `NewsService::usedCategoryIds()` (referenced by the hardcoded RSS `SOURCES` — the `create_news_categories_table` migration itself seeds ids 1-4, exactly these) | `tests/Feature/Backend/Controllers/NewsCategoryControllerTest.php` |
| `portfolioTotals` | `Portfolio::calculateCurrentValue()`/`calculateTotalInvested()`/`recalculateTotals()` and the profit/loss accessors (incl. the empty-portfolio div-by-zero guard) | `tests/Unit/Models/PortfolioTest.php` |
| `profile` | `ProfileController::show()`/`edit()` degrade gracefully (no crash) when the authenticated user has no `UserProfile` row yet; `storeAvatar()` upload/replace/remove/keep-existing branches (`Storage::fake()`) | `tests/Feature/Frontend/Controllers/ProfileControllerTest.php` |
| `companyProfile` | Company profile page (vnstock `Company`): symbol normalisation (nothing non-ticker ever reaches a shell), DB-first + **stale-while-revalidate** (stale copy served, exactly one deduplicated `SyncCompanyProfileJob`), first-visit `load()` via `SingleFlight`, negative cache for unknown symbols (timeouts/outages are **not** cached), forced-refresh cooldown (429), `present()` bucketing (upcoming events by key date, insider trades, officer groups, donut series incl. the "others" slice edge cases), repository upsert/stale queries, `sync:company-profiles` (`--symbol`/`--seed`/`--limit`/`--dispatch`), controller status codes (404/429/502), Admin > Sync Status entries. Python boundary (`runScript()`) is stubbed with a partial Mockery mock — no subprocess | `tests/Feature/Frontend/Services/CompanyProfileServiceTest.php`, `tests/Feature/Frontend/Controllers/CompanyProfileControllerTest.php`, `tests/Feature/Frontend/Repositories/CompanyProfileRepositoryTest.php`, `tests/Feature/Console/Commands/SyncCompanyProfilesTest.php`, `tests/Feature/Backend/Controllers/SyncStatusControllerTest.php`, `tests/Unit/Support/VnFormatTest.php`, `tests/Feature/Support/SingleFlightTest.php` |
| `fundCatalog` | Open-ended fund catalog (vnstock `Fund`/Fmarket): `Fund::typeCodeFromLabel()`, filter/sort whitelisting (an injection-shaped `sort` never reaches `ORDER BY`), NULL returns always sorted last (real SQL, `RefreshDatabase`), LIKE-wildcard escaping, upsert-by-`short_name`, listing → rows mapping (`type_code` derived, non-column keys dropped), detail cached so Python runs once and a **failed fetch is not cached**, `FundMetrics` maths (window slicing, return, max drawdown, annualised volatility, null for `ALL`), `/funds/compare` not swallowed by `/funds/{code}`, `sync:funds` | `tests/Unit/Support/FundMetricsTest.php`, `tests/Unit/Models/FundTest.php`, `tests/Feature/Frontend/Services/FundServiceTest.php`, `tests/Feature/Frontend/Repositories/FundRepositoryTest.php`, `tests/Feature/Frontend/Controllers/FundControllerTest.php`, `tests/Feature/Console/Commands/SyncFundsTest.php`, `tests/Feature/Support/SingleFlightTest.php`, `tests/Unit/Support/VnFormatTest.php` |
| `goldPrice` | Gold price page: sync stores SJC/BTMC/silver with the world price on the newest publication only, second sync is idempotent (ISO `...Z` timestamps normalised — regression for a unique-index crash) and SJC only stores a changed price, python error/none handled, first-visit live load, DB-only page + one deduplicated queued refresh when stale, newest quote per product, day change vs yesterday's close, uniform SJC branches, USD/oz → VND/lượng and premium math (and graceful without a USD rate), chart options, history window + carry-forward, refresh cooldown (429) / failure (502), job throws on error, navbar groups Tỷ giá + Giá vàng under **Thị trường**, admin sync-status source + trigger |
| `marketOverview` | Market overview: market clock (weekday 09:00–15:00 VN), stale thresholds, sync stores one row per session and drops old quote maps, same-session upsert, a half-empty payload never overwrites a good snapshot, first-visit live load / error state, breadth + liquidity sums and the previous-session comparison (only once the session is complete), one deduplicated refresh job, quotes, ticker (incl. empty table), sparkline maths; home page section (data, guest vs signed-in card, empty state, service throwing), real ticker tape vs static fallback, `/market/data` (incl. the user's own watchlist only, 503), admin Sync Status entry + trigger, command/job behaviour, `Node`: format helpers | `tests/Feature/Frontend/Services/MarketOverviewServiceTest.php`, `tests/Feature/Frontend/Controllers/MarketHomeAndWatchlistTest.php`, `tests/Feature/Console/Commands/SyncMarketOverviewTest.php`, `tests/js/market.test.mjs` |
| `watchlist` | Follow list: ticker normalisation, idempotent add, unknown/invalid symbols, 50-item cap per user, owner-only remove, rows priced from the snapshot (newest first, ceiling flag) with last-close fallback and no-data rows, limit, auth-only routes (no verified-email wall), follow/unfollow round trip, 422 messages, page only renders the user's own rows, ★ state on the company page | `tests/Feature/Frontend/Services/WatchlistServiceTest.php`, `tests/Feature/Frontend/Controllers/MarketHomeAndWatchlistTest.php` |
| `portfolioLedger` | Buy/sell ledger: first buy (fee inside the cost), weighted average + earliest date, partial sell freezes realised P&L and keeps the average, later buys never rewrite history, full sell closes the position, over-sell / unknown symbol / bad input / foreign portfolio rejected without writing anything, undo of a sell (incl. a closed position) and of a buy (exact reversal), only the newest transaction undoable and only by its owner, edit-below-quantity guard, overview (realised, win rate, best/worst, fees, undoable rows), add-stock form writes the ledger, validation (price in thousands, symbol, future date), page sections, CSV (BOM, order), **container injects `PortfolioService`'s optional collaborators**, `Node`: fee estimate + trade preview | `tests/Feature/Frontend/Services/PortfolioLedgerServiceTest.php`, `tests/Feature/Frontend/Controllers/PortfolioLedgerControllerTest.php`, `tests/js/market.test.mjs` |
| `stockFreshness` | Stock page speed: `TradingCalendar` (last completed session incl. weekends, Monday morning, timezone), `StockPriceFreshness` (current history costs nothing; stale → served at once + ONE deduplicated incremental job with the 5-day overlap; never-synced → one short fetch with the 25 s ceiling; failure remembered; unknown codes never reach Python or create a `stocks` row; batched first loads; live bar in feed units, not added when stored / no volume / not in the snapshot, `running` flag), `storePriceData` (upsert idempotent, `time`→`date`, unknown symbols and bad bars skipped), `refreshPrices` (range + timeout passed on, per-symbol and hard errors), `RefreshStockPricesJob`, the real routes (`/stock`, `/stock/compare-data`: no Python for stale/up-to-date symbols, live-bar badge, failure message, 404 for malformed codes), and **the real `py/get_stock.py` against a local fake KBS server** (order, units, dates, start trimming, index endpoint, per-symbol errors, an empty answer must not fall back to the slow sources) | `tests/Unit/Support/TradingCalendarTest.php`, `tests/Feature/Frontend/Services/StockPriceFreshnessTest.php`, `tests/Feature/Frontend/Services/StockServicePriceRefreshTest.php`, `tests/Feature/Frontend/Controllers/StockPageFreshnessTest.php`, `tests/Feature/Support/GetStockScriptTest.php` |
| `adminShell` | Redesigned admin shell: sidebar + command palette only offer what the user's permissions allow, active item marked, theme/palette/toast/confirm markup present, flash messages escaped, failed-jobs badge (and no badge at 0), split login page without CDN, dashboard KPIs + a traffic-light status for every data source + unverified/failed counters, the expensive price-row count is cached, actions hidden without permission, timeline grouped by day with per-type counts and unknown event types, destructive actions use `data-confirm` (no `window.confirm`), no admin page loads assets from a CDN; `Node`: palette search (accent-insensitive, ranking, wrap-around selection) | `tests/Feature/Backend/Controllers/AdminShellTest.php`, `tests/js/admin.test.mjs` |
| `stockSearch` | Symbol search behind the autocomplete: relevance ranking (exact ticker → prefix → contains → name-only), trimmed `symbol/name/exchange` shape, LIKE wildcards treated literally, 20-row cap (real SQL, `RefreshDatabase`); `App\Support\Mojibake` repair of CP437-garbled company names | `tests/Feature/Frontend/Repositories/StockRepositorySearchTest.php`, `tests/Unit/Support/MojibakeTest.php` |
| `portfolioPage` | Portfolio feature end to end (real DB + routes): holdings valued in whole VND from `stock_prices` (feed thousands × 1000 — the `-99.97%` bug), today's move from the previous close, ownership isolation (404 / untouched rows for another user's portfolio/holding), `update-prices` JSON report + deduplicated background sync for symbols without data, edit/delete holding (incl. the redirect regression: item id used as portfolio id), add-stock (name from symbol list, market price at add, averaging on a repeat buy, VND/date/symbol validation), quote endpoint, quick-add routing (0/1/many portfolios), CSV export, deactivating a portfolio (hidden-0 checkbox), onboarding/empty state, navbar link for guests | `tests/Feature/Frontend/Controllers/PortfolioControllerTest.php` |
| `portfolioAdmin` | Admin > Portfolio: real totals instead of hard-coded placeholders, per-portfolio value/P&L/item count, search+status filter grouping (the ungrouped `OR`), detail page (used to 500: nested quotes in `@section`), stats page (had no view) | `tests/Feature/Backend/Controllers/PortfolioAdminControllerTest.php` |

## Adding a new feature's tests

1. Find (or create) the folder under `tests/Unit/` or `tests/Feature/` that mirrors where the code lives in `app/`.
2. Name the test class `<ClassName>Test.php`, same name as the class under test.
3. Pick ONE **camelCase** group name for the feature; tag every test method for it with `#[Group('yourFeatureName')]`.
4. Mock every `*RepositoryInterface` dependency with Mockery — never let a test hit the database (see above).
5. Run just that group and make sure it's green before moving on: `docker exec stock-app-php-1 php artisan test --group=yourFeatureName`.
6. Add a row to the "Current groups" table above.

## JavaScript tests (chart helpers & indicator maths)

Pure front-end logic lives in DOM-free modules so it can be tested without a browser:

```bash
npm test          # node --test "tests/js/*.test.mjs"  (Node's built-in runner, no extra dependency)
```

- `tests/js/indicators.test.mjs` → `resources/frontend/js/shared/indicators.js` (SMA/EMA/RSI/MACD/Bollinger).
- `tests/js/charts.test.mjs` → `resources/frontend/js/shared/charts.js` helpers (`toDay` timezone shift, `cleanSeries`, `sliceByDays`, `rebase`, price formatting).

The chart *rendering* (Lightweight Charts canvas, panes, legend) needs a real browser — verify visually after `npm run build`.

## Gotchas learned the hard way

- **The router caches the resolved controller on the `Route` object.** In a feature test, `$this->app->instance(SomeService::class, $stub)` only takes effect for the *first* request to a given route; re-binding between two requests to the same route is silently ignored. Bind the stub before the first request and keep one stubbed request per test.
- **`@json([...])` with commas inside a Blade template breaks** (`@json` splits its argument on commas to read the flags). Build the array in a `@php $x = [...]; @endphp` block and pass `@json($x)`.
- Mockery **partial mocks** (`Mockery::mock(Service::class, [$repo])->makePartial()->shouldAllowMockingProtectedMethods()`) are how services expose their Python boundary to tests (`runScript()`, `runListScript()`, `runDetailScript()`) without a subprocess.

## Never let a test reach the network

Anything that renders the home page needs a market snapshot, otherwise the first visit runs `py/get_market_overview.py` (a real Python + KBS call). Use the `Tests\Concerns\BuildsMarketPayload` trait: `marketPayload()` is a realistic script output for stubbing `runScript()`, and `seedMarketSnapshot()` writes the row directly (`ExampleTest` does this). `PythonRunnerTest` also covers the `VNSTOCK_API_KEY` passthrough with the `tests/Fixtures/python/print_env.py` fixture (it removes the process's inherited key first, because Laravel exports `.env` into the environment and children inherit it).

### Testing a Python script for real
`GetStockScriptTest` starts `php -S 127.0.0.1:<random port> tests/Fixtures/kbs_router.php` (a tiny fake of KBS's history endpoint, newest bar first, VND) and runs the real `py/get_stock.py` with `KBS_BASE_URL` pointing at it and `STOCK_SOURCES=KBS-direct`. That exercises parsing, unit conversion, date handling and the no-fallback rule without network or vnstock. Keep the router in step with the real endpoint's shape.

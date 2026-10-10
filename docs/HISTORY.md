# Feature Update History

The **latest two days** are kept here in full, newest first. Everything older lives verbatim in [docs/history/](history/) and is summarised in one line per entry under [Earlier work](#earlier-work-one-line-digest). The README changelog has one row per day.

**Maintaining this file:** add new entries at the top. When this file passes ~400 lines, move the entries that are more than two days old to `docs/history/<YYYY-MM>.md` (verbatim, keep the `## NAME - Month D, YYYY` title) and add a one-line digest for each below.

---

## DOCKER_OPTIMISATION - October 10, 2026

### Summary
The owner asked, as a "system dev ops", whether the Docker setup could be faster and lighter, then "làm tất cả" — after a backup, with the backup deleted only once everything is stable — and explicitly **excluded item 4** (`.wslconfig`, WSL limits, worker profile): not done.

### Findings (measured)
Project on the Windows disk is bind-mounted into three PHP containers: `stat` of 361 files 1.1 s vs 5 ms on the container's own disk; `opcache.revalidate_freq=0` re-stats ~150 scripts on every request (page 170–240 ms; with 2 s: 106–180 ms). MySQL ran with a 128 MB buffer pool on a 467 MB database, `performance_schema` on, a binary log (22 files, 283 MB) and an fsync per commit. Docker logs never rotated; the same Dockerfile was built three times.

### Done
php.ini (`revalidate_freq=2`, interned strings, realpath cache TTL, dev-only umask prepend); Laravel's compiled views and bootstrap caches moved to the container's own disk through env vars (`VIEW_COMPILED_PATH`, `APP_*_CACHE`, folder created at container start); MySQL dev tuning as server options (not a `my.cnf`: a file on the Windows mount looks world-writable and MySQL ignores it) — MySQL RAM 882 → ~540 MB, old binlogs purged; nginx gzip + open_file_cache; log rotation; one shared image for php / queue / scheduler. Details and the production caveats in docs/DOCKER.md §4b.

### Follow-up the same day: the image is rebuildable again, vnstock 4.0.9
Read the vnstock repository: vnstock left PyPI and is published on its own index (`vnstocks.com/api/simple`, only the two newest versions kept). Built a pinned image (digest-pinned base, `docker/python/requirements.txt`, vnstock 4.0.9 / vnai 2.6.3 from local wheels installed with `--no-index`; the wheels are git-ignored because of their personal-use / proprietary licences), checked it (`pip check` clean; the four real data scripts return identical JSON to the old image; full PHP suite on the new image: 1246 tests, 6342 assertions, green) and moved the stack onto it (the old image stays as `stock-app-php:before-vnstock4` for a rollback; nginx had to be restarted once because it cached the old php container's address and answered 502). The outdated third-party docs folder `docs/vnstock-agent/` (stopped at vnstock_data 3.0.0) and its pointer in AGENTS.md were deleted; the vnstock API key in `.env` is unaffected. Replaced by one maintained page, `docs/VNSTOCK.md` (installed version, data source per script, API-key rules, why vnstock's opt-in AI-agent file writing stays off — tried once in a throwaway folder: it writes a 4.6 KB generic block, nothing project-specific), linked from AGENTS.md and PYTHON_INTEGRATION.md. Details: docs/DOCKER.md §4c.

### Found on the way (before the fix)
**The PHP image could not be rebuilt**: `pip install vnstock` fails (PyPI no longer serves the package to that pip) and the unpinned base image moved to Python 3.13. The running image (vnstock 4.0.8, pandas 2.3.3) is fine, so the start-up commands create the cache folder instead of a Dockerfile change, and a `docker save` copy of the image was kept. Before any rebuild: pin vnstock / pandas and the base image tag.

---

## DEMO_BULK_DATA - October 10, 2026

### Summary
The owner wanted the site to look alive and every feature to be testable with real volume ("vài trăm records", not a handful per feature). `DemoBulkDataSeeder` fills the whole site with a few hundred realistic rows per feature; `DemoBulkDataCleanupSeeder` removes exactly that.

### What it creates (defaults; `DemoBulkDataSeeder::$members` scales it)
- **320 members** with Vietnamese names (weighted surnames, male / female middle and given names), `@bulk.sunstock.test` addresses, sign-ups over 150 days with growth towards today, 86 % active / 8 % pending / 5 % inactive / 2 % blocked, profiles (username, optional 555-pattern mobile, birthday), role `user`, the shared demo password.
- **~260 portfolios** in eight investing styles (banks, growth, real estate, brokers, ETF, dividend, day-trader, mixed), **~1,400 holdings and ~1,500 trades** made through `PortfolioLedgerService`: every buy and partial sell uses the **real closing price stored in `stock_prices` on a real trading day** (never before the member joined, never in the future), so profit / loss on the pages is real (on the dev data the median portfolio is −1.9 %, range −23 % … +16 %). Some positions have target / stop-loss prices.
- **~2,000 watchlist rows** (Zipf-weighted towards the most traded stocks), **~7,000 activity events** (sign-up, login, portfolio created, trades, watchlist), **~3,500 sign-ins** (typos before a success, 45 scanner addresses, four past password-guessers, **three guessers active right now** so Admin > Bảo mật flags them), **~950 AI calls** from a few heavy users (three models, errors, three accounts blocked from the AI with refused attempts), **~1,900 sync runs** and **600 queue jobs** over the last week following the real schedule shape (weekday-only market jobs, 2–10 % failures), 12 blocked addresses.
- Deterministic (`mt_srand(20261010)`) and idempotent: it first purges its own previous rows, so a second run gives the same data (checked by a test). Local / testing only. Takes about 4 minutes on the dev machine (the ledger writes ~1,500 trades one by one; the host was also busy).

### Use
```bash
php artisan db:seed --class=DemoBulkDataSeeder          # prints a table of counts and five accounts with the largest portfolios
php artisan db:seed --class=DemoBulkDataCleanupSeeder   # removes exactly what it created
```
Every page was rendered through the HTTP kernel for a member and for the admin after seeding: all answer 200.

### Tests
Group `demoBulkSeeder` (9 tests, a smaller crowd of 36): every feature gets rows, trades are at the stored close on a stored date, each holding equals its buys minus its sells and has a price, nothing before sign-up or in the future, exactly the three active guessers, blocked accounts only refused, repeatable, cleanup leaves unrelated rows alone, refuses outside local / testing.

---

## TRANSFORMER_RESPONSE - October 9, 2026

### Summary
The owner pointed out that status codes and return shapes were scattered all over the admin and the public side, and brought a `TransformerResponse` class from a real project: one "heart" that owns the codes, the stock messages and the response helpers, for JSON and for views alike.

### What was built
- **`App\Support\TransformerResponse`** (the project's shared-code folder, not a new top-level one). It **extends `JsonResponse`** rather than the plain `Response` of the original: it is still a `Response`, every existing `: JsonResponse` signature keeps compiling, and the body is always JSON. Envelope `{success, code, message, data, ...extra}` as in the original; `extra` rides on top-level so the page scripts that read `answer`, `result`, `error`, `login_url`… did not need a single change (a legacy key wins a clash).
- Beyond the original: `unauthorized`, `forbidden`, `unprocessable`, `tooManyRequests` (+ `Retry-After`), `serverError`, `badGateway`, `serviceUnavailable`, raw `json()`, `view()` with a status, flash / redirect helpers (`backSuccess`, `redirectError`, `backWithInput`, `redirectIntended`…), `negotiated()` for an action reached by both `fetch()` and a form, `abortWith / abortIf / abortUnless`, message builders `createdMessage()` etc. The messages are Vietnamese (they are what visitors read); the constant names follow the original.
- **Applied everywhere**: all 50 `response()->json()` calls, 19 `abort*()` calls, ~87 flash returns, the three AI refusal statuses, the watchlist and portfolio-ledger service statuses, the rate limiters, `EnsureUserIsActive`, `AdminOnlyForChanges`, `BlockedIps` and the auth exception renderer in `bootstrap/app.php`. Repeated sentences became constants (`NO_PERMISSION_MESSAGE`, `PORTFOLIO_NOT_FOUND_MESSAGE`…); the create / update / delete flashes use the builders. One side effect: the "xoá" / "xóa" spellings that had drifted apart are now one ("xóa").
- **Enforced**: `NoScatteredResponsesTest` (groups `transformerResponse`, `codeStyle`) fails when a raw `response()->json()`, `abort()`, `->with('success'…)` or a bare 4xx / 5xx status appears in `app/`, `routes/` or `bootstrap/app.php`; the scanner is itself tested on good and bad lines. AGENTS.md got a forbidden-pattern row and GUIDELINES a "Responses" section.

### Tests
Group `transformerResponse` (28 tests). The whole suite stayed green with no assertion changed — the point of keeping the legacy keys.

---
## ADMIN_HEALTH_SECURITY_USERS_CONTENT - October 9, 2026

### Summary
Second round of the owner's "admin day" ("làm hết luôn đi"): four more areas, then a seeder so everything can be tried with real clicks.

1. **Sức khỏe hệ thống** (`/admin/health`, `manage-features`) — a **scheduler heartbeat** (`bootstrap/app.php` writes `scheduler:heartbeat` to the cache every minute; ok ≤ 3 min, slow ≤ 10, stopped after that; "never beat" = nobody runs `schedule:work`), disk space, the **schedule table** (every task, cron, next run — `Artisan::all()` must be called first: `withSchedule()` runs only when the console app starts, so a web request saw 0 tasks), the **backups** found by `DatabaseBackup` with "Tạo ngay" (queued through `Artisan::queue('db:backup', --force)`, never inside the request) and a download whose id is a hash of the path (only files the scan itself found can be served). Taking and downloading a backup is **admin role only** (a dump holds every password hash).
2. **The alert bell** in the admin top bar (`AdminAlertsService`, cached 60 s, nothing counted on big tables): dead scheduler, late sources, failed jobs, a failing AI (≥ 5 calls in 24 h and < 70 % ok), no or old backup, disk < 10 %, `APP_DEBUG` on in production, addresses guessing passwords. Each alert names the permission needed to see it. `SyncSourcesService` was extracted from `SyncStatusController` so the page and the bell share the "late" rule (`build(false)` skips the row counts).
3. **Bảo mật** (`/admin/security`, read `manage-users`, changes admin-only via `admin.only`) — `login_attempts` filled by `LoginAuditor` from the framework's `Login` / `Failed` events (both doors, typed e-mail lower-cased, never a password, pruned after 90 days by `logins:prune`), suspicious addresses (≥ 10 failures in an hour), **blocked IPs** (`blocked_ips` + `BlockedIps` middleware first in the web group, cached 60 s, fails open). Refused: invalid addresses, private / reserved ranges (behind a proxy everybody can share one), the admin's own address, duplicates.
4. **Users** — the detail page shows last sign-in, failed attempts for that e-mail, watchlist, holdings, AI use and recent activity (`UserInsightsService`); **bulk actions** (verify / block / unblock / AI block / AI unblock, max 100, `UserBulkService`, every account through `AdminGuard` so one refused account never hides the others, one audit entry); **CSV export** of the list's own filters (`UserRepository::filtered()` is shared by screen and export; admin role only; BOM for Excel; cells starting `= + - @` neutralised against spreadsheet formulas).
5. **Nội dung & dữ liệu** — **featured stocks** of the home page chosen in Admin > Giao diện & Cache (`SiteSettings::featuredSymbols()`, 1–6 known symbols, cache key follows the list); **pin / hide news** (`news.is_hidden`, `news.pinned_at`, not mass-assignable, the RSS sync only inserts so they survive; pinned lead the home list, hidden are gone from every public list); **Chất lượng dữ liệu** (`/admin/data-quality`, 10-minute cache): tracked stocks without a price, stocks whose newest price is older than 10 days, impossible price rows of the last 30 days, symbols people follow / hold that the system does not know. First real result on the dev data: 317 stocks have no price since 2024-07-25 and 8 have none at all.

### Seeder
`php artisan db:seed --class=DemoAdminFeaturesSeeder` (local / testing only, idempotent) fills every screen: sync history with a failed run, heartbeat, AI calls over a week (three models, errors, refusals, an account at its daily quota, one blocked), sign-ins with a password-guessing address and a blocked one, 24 accounts over 30 days and a week of activity, a pinned and a hidden article, a prepared notice (OFF), and one of each data-quality problem. It also creates `admin@sunstock.test` (shared demo password). `DemoAdminFeaturesCleanupSeeder` removes exactly what it made (the fake DEMO… stocks are also walked by the daily `sync:stock-prices`, so clean up when done).

### Tests
Groups `adminHealth`, `adminSecurity`, `adminUserTools`, `adminContent`, `adminDataQuality`, `demoAdminSeeder` (in `tests/Feature/Backend/Controllers/` and `tests/Feature/Seeders/`). Gotchas: a Blade view cannot `use Throwable;` (the compiled file warns "no effect") — keep `\Throwable` in the admin layout; a test helper must not be called `fail()` (final in PHPUnit); mass-assignment-protected flags (`pinned_at`, `is_hidden`, `ai_blocked_at`) need `forceFill` in test fixtures too; `db:seed` in a test that sets the environment to production asks for `--force`, so call the seeder's `run()` directly.

---
## ADMIN_SYNC_AI_SITE_DASHBOARD - October 9, 2026

### Summary
The owner had no ideas for the day and asked for admin features ("quản lý này kia"). Looking at the admin first showed a real gap: the two jobs added the day before (world markets, signals) were missing from Sync Status. Four things were built, all behind the existing `manage-features` permission (no new permission, no seeder change):

1. **Sync Status** — every run of a `sync:*` / `signals:*` command (scheduler, the "Sync ngay" button or a terminal) is recorded in `sync_runs` by `App\Support\SyncRunRecorder` (console events `CommandStarting` / `CommandFinished`, registered in `AppServiceProvider::boot`). The page shows per source the last run (result + duration), a schedule-aware **"Trễ"** state (each source has its own `max_age_hours`; the old rule was a flat 24 h), a banner counting late sources and a table of the latest runs. New sources: **Chỉ số thế giới** and **Tín hiệu hôm nay**, read straight from their cache keys (the services' readers queue a refresh job, a monitoring page must not); both commands joined the manual-trigger whitelist. The dashboard's source table got the same two rows. First real finding: gold, funds and ETFs had not synced for 5–7 days on the dev machine (the scheduler is not running locally).
2. **Dashboard charts** (`DashboardInsightsService`, 5-minute cache, plain CSS bars, no chart library): sign-ups per day (30 days), activity per hour of the Vietnam day (7 days, needs `view-timeline`), most followed and most held symbols, accounts active this week. Aggregates only.
3. **Quản lý AI** (`/admin/ai`) — `ai_requests` logs every call of `/ai-chat` and `/ai-predict` (user, kind, status ok/error/refused, the model that answered, duration, the question cut to 300 chars). `AiService::lastModel()` says which model of the fallback chain answered. `AiUsageService::refusal()` runs before the provider: a **kill switch**, a per-account **block** (`users.ai_blocked_at`, set only by `forceFill`, audited) and an optional **daily quota** per account (`site_settings` key `ai`; refused calls do not use it up; they are logged as `refused`). The page: today / 7-day numbers, success rate, average time, the model chain with per-model use, heaviest users with Khóa / Mở khóa AI, the latest calls. Names and e-mails show only to holders of `manage-users` (others see "Tài khoản #id").
4. **Giao diện & Cache** (`/admin/site`) — `site_settings` (key => JSON) read through `App\Support\SiteSettings` (1-minute cache, safe defaults): **home blocks** an admin can hide (world strip, heat map, brief, foreign, sentiment, gold/USD/funds, signals, events, news — a hidden block is not even computed, so it queues no refresh job; hiding both signals and events removes the section and its jump link), a **site-wide notice** bar (`partials/site-notice.blade.php`: three levels, text escaped, link only `https://…` or `/path`, closable, comes back when the text changes) and three **cache buttons** (home data, the weekly AI prediction, the stock screener) that clear named keys only.

### Tests
Groups `adminSyncRuns`, `adminDashboardCharts`, `adminAi`, `siteControl` (46 tests). Notes: Laravel does not dispatch the console events for `Artisan::call` inside a test run, so the recorder is tested with synthetic events plus a wiring check; a second `Http::fake` does not replace the first (the first matching stub wins) — use `Http::swap(new Factory)`; do not run Pint over whole directories (it reformatted 56 unrelated files once and had to be reverted): run it on the files you wrote.

---
## HOME_SCROLL_SECTIONS_AND_FX - October 8, 2026

### Summary
After layout pass 2 and the six extra features the owner said the home page looked **more** cluttered than the old one and asked to research the 3 most-starred GitHub repositories on UI/UX and structure. Read for real (raw files): **Maybe** (54k★ — a few full-width sections per page, one H1, one primary action), **Ghostfolio** (9.4k★ — a thin tab bar, each tab its own view; the fear/greed gauge lives in its Markets tab), **Wealthfolio** (9.1k★ — few modules per dashboard). OpenBB could not be read.

### Round 1 — four tabs (tried, then replaced)
The page was split into Tổng quan | Thị trường | Tín hiệu & sự kiện | Khám phá tabs (with a row of three "teaser" cards on the overview, because the first cut looked like "nothing to see"). The owner then said it was tidy but "lost its character": having to click a tab to see anything, the AI prediction hidden in a tab, and the page looked flat with no animation or 3D. **Lesson: a market page should reward scrolling; do not hide content behind tabs — organise it with sections and navigation instead.** The tab bar, the `hometab` event and the teasers were removed.

### What the page is now
- **One continuous scroll in four numbered sections** (`#hpOverview`, `#hpMarkets`, `#hpSignals`, `#hpExplore`) plus the news / guest signup banner (`#homeNews`). `index.blade.php` defines the shared variables once and `@include`s `partials/home/{overview,markets,signals,explore,news}.blade.php`; sections 02–04 have a numbered header (big gradient number, title, one-line description).
- **Sticky jump bar** (`#homeJump`, a frosted pill under the hero): links to the sections, the one being read is highlighted (`initJumpBar`, `activeIndex()`), smooth scrolling comes from the page's existing anchor handler; it scrolls sideways on a phone.
- **AI prediction is a banner** (`.ai-hero`): animated indigo→violet gradient, two drifting blurred orbs, equaliser-like bars, a button with a moving shine. Same element ids as before (`aiPredictBtn`, `aiPredictResult`, `aiPredictLoading`, `aiPredictContent`), so the click handler in `index.js` is unchanged.
- **Visual effects** (`resources/frontend/js/shared/fx.js`, decoration only, off for `prefers-reduced-motion`): index cards, pulse cards, featured-stock cards and the AI banner **tilt in 3D** towards a real mouse with a soft light following it (`initTilt`, never on touch); cards, headers and indices **rise into view** as they are scrolled to, staggered (`initReveal` — the `.fx-reveal` class is added by the script, so without JS everything simply shows); the sentiment needle sweeps in when its card is first seen (`IntersectionObserver` in `home.js`).
- "Độ rộng" is a card of its own next to the movers list (`#mkBreadthCard`); signals and events are two stand-alone cards. The "Thế giới" label was recoloured (it now sits on a light background).
- A page of the hot-industries list (`?page=N`) still opens the hot list inside the Khám phá card and scrolls to it.

### Tests
`homeLayoutV2` now covers the jump bar and its targets, the section order and which block lives in which section, the AI banner ids, `?page=2`, the guest banner at the end and the script hooks; `tests/js/fx.test.mjs` covers the tilt maths and the active-section rule. `homeLayout`, `homePulse`, `marketOverview` were adapted. Nothing committed yet — the owner wanted to check the page first.

---
## HOME_PULSE_SIX_FEATURES - October 8, 2026

### Summary
After the layout pass the owner found the page "a bit empty" and asked for more content, possibly from vnstock or elsewhere, then said "do 1 to 6". Before proposing, the data was checked for real (see "Found"). **Still not committed**, as the owner asked for the layout pass.

### Found
- The KBS price board that `get_market_overview.py` already requests carries `foreign_buy_volume`, `foreign_sell_volume` and `foreign_room` for every stock; they were thrown away. Real values checked (e.g. FPT bought 821 k / sold 1,81 m shares).
- vnstock's **MSN** source answers daily bars for world indices without a key (S&P 500, Nasdaq, Dow, FTSE, DAX, Nikkei, Hang Seng, Shanghai; BTC came back empty and oil is not in its map, so neither is used).
- `stock_prices` holds 3,4 m daily rows, but only ~870 stocks had a bar for the latest day, so signals compare TODAY'S snapshot quote (all ~1.550 stocks) with the stored history instead.
- Only 61 company profiles are cached, and none had an upcoming event today: the events calendar needed more coverage, not just a widget.

### Done
1. **Khối ngoại** (`foreign_flow()` in the script → `snapshot.data.foreign`, no schema change): totals, net per exchange and the five biggest net buyers/sellers. Every value is an **estimate** (net volume × last price; the board has no foreign matched value) and the card says so. Drawn by `js/market/pulse.js` (pure, tested) on load and on every poll.
2. **Vàng · Tỷ giá · Quỹ** (`MarketPulseService`, DB-only): SJC sell price with its change since the previous Vietnam day and the world price, Vietcombank USD with its change (new `ExchangeRateRepository::getRateBefore`), the three best equity funds over 12 months (funds without a 12-month figure never rank). Each part fails independently.
3. **Tâm lý thị trường** (`App\Support\MarketSentiment`, pure): a 0–100 reading from breadth 35 %, VN-Index move 30 %, ceilings vs floors 15 % (needs ≥ 5 limit stocks), net foreign flow 20 %; missing components are left out and the weights re-scaled; < 2 components = no reading. SVG gauge with a needle that sweeps in, four bars showing each part. Labelled as our own indicator, not an official index and not advice.
4. **Tín hiệu** (`StockSignals` pure + `StockSignalService`): 52-week breakouts/breakdowns, volume ≥ 2× the 20-session average with ≥ 5 bn traded, RSI(14) ≥ 70 / ≤ 30, for stocks with ≥ 3 bn traded today and ≥ 100 stored sessions. The build reads ~300 k bars (≈ 1,3 s), so **a page view only reads the cache**; a missing or old result queues one deduplicated `BuildStockSignalsJob` (also `signals:build` on the scheduler every 30 min in the session + 18:15). Description of what happened, never a recommendation.
5. **Thế giới** (`py/get_world_markets.py` + `WorldMarketService`): thin strip under the Vietnamese indices; cache-only reads, refreshed by `sync:world-markets` every 30 min or by a deduplicated job when missing/old (> 90 min).
6. **Sự kiện** (`HomeEventsService`): upcoming dividends / bonus shares / meetings (next 60 days) from the cached profiles plus the visitor's own watchlist and portfolio symbols (flagged "của bạn"; a symbol without a profile queues one, at most 15 per visit). Coverage grows: `sync:company-profiles --seed` now seeds the **most traded stocks first** and runs **daily** (40 a night; 60 were queued by hand today). The tab states how many companies it covers.

Layout: a new row **"Nhịp thị trường"** (foreign flow | sentiment | gold·USD·funds) between the workspace and the AI button, a world strip under the index cards, and two more tabs (Tín hiệu, Sự kiện) in "Khám phá thêm". Desktop height ≈ 4.700 px (was 3.450 before the additions, 8.257 originally); phone 8.100 px stacked, no horizontal overflow.

### Safety of the test suite
`Tests\TestCase` now fakes `SyncWorldMarketsJob` and `BuildStockSignalsJob` (the test queue is `sync`, so rendering the home page would otherwise have run a real Python call and the heavy build). Writing the page tests also showed that a test which deletes all exchange rates makes `home()` fall back to a live Python fetch: such a test was removed, keep a rate row in home tests.

### Tests
Groups `foreignFlow`, `marketSentiment`, `stockSignals`, `stockSignalsService`, `worldMarkets`, `marketPulse`, `homeEvents`, `homePulse` (≈ 130 PHP tests incl. the real `foreign_flow()` and the world script with a stand-in `Quote`) + Node `pulse.test.mjs` (13). 478 PHP tests across the groups touching the home page / layout and 112 Node tests pass; Pint clean.

---

## HOME_LAYOUT_PASS_2 (A + B) - October 8, 2026

### Summary
Owner: the home page felt cluttered and not optimised; asked for researched layout options, then chose **A + B** and asked not to commit until checked. Measured first (Chrome): the page was **8.257 px (about 9 screens)**; the same stock list appeared in four places; two long tables (exchange rates 1.344 px, hot industries 1.104 px) sat in the tail.

### Sources used for the options
NN/g, [Scrolling and attention](https://www.nngroup.com/articles/scrolling-and-attention/) (2018 eyetracking: ~57 % of viewing time above the fold, ~74 % in the first two screens, ~81 % in the first three; put priority content first, keep a clear visual hierarchy) · NN/g, [Progressive disclosure](https://www.nngroup.com/articles/progressive-disclosure/) (show few important things first, defer the rest, two levels at most) · NN/g, [F-shaped reading pattern](https://www.nngroup.com/articles/f-shaped-pattern-reading-web-content-discovered/) · W3C, [Manageable quantity of content](https://www.w3.org/WAI/WCAG2/supplemental/patterns/o5p03-manageable-quantity) (about five main choices per screen, extras behind a clear "more"). Vendor blogs on dashboard hierarchy were only used as colour. Not verified: the actual layouts of TradingView / Yahoo Finance / SSI iBoard.

### A — "one screen, two levels"
- Index cards became a **compact strip** (166 → ~90 px). The **brief** shows only its headline; the five cards unfold on "Chi tiết" (remembered).
- **Workspace**: heat map and VN-Index chart are two views (tabs) of one card on the left; the right card has five tabs (Tăng, Giảm, GTGD, Theo dõi, Độ rộng) replacing the movers table + watchlist card + breadth card. The movers table dropped its volume column to fit 350 px. The heat map card grows to the height of the list beside it; folded, it shrinks to its header.
- **"Khám phá thêm"**: featured stocks, hot industries (still paginated) and exchange rates in one tabbed card; rates show the six main currencies with a link to all of them. News shows three cards. On phones the featured cards swipe sideways and the brief fits one row.
- Result: **~3.450 px** on desktop (about 3.9 screens), no horizontal overflow at 390 px.

### B — "mine first" (signed-in visitors)
- `PortfolioService::homeSummary()` adds the user's active portfolios from the **stored** prices (no refresh, queue job or Python; holdings without a price are counted apart, never a −100 % loss) and the home page shows value, profit/loss and today's move above the market, with the watchlist as chips. It can never take the page down (try/catch → card absent). Verified with a demo account (141 triệu ₫, −6,33 %).

### Tests
Group `homeLayoutV2` (19): tab ARIA wiring for all three tablists, five side tabs and a five-column movers table, heat map + chart in one card, chart-only fallback, folded brief, explore card and `?page=` tab, main currencies in fixed order (and the first six as fallback), three news cards, personal card for guest / user without portfolio / user with portfolio / failing service / other users' data, and four `homeSummary` cases. Node: `tabs.test.mjs` (4). The old `homeLayout` and `marketOverview` tests were adapted to the new markup. **Not committed yet, on the owner's request.**

---

## MARKET_BRIEF - October 8, 2026

### Summary
Owner was out of ideas and asked me to pick something good and build it. Chosen: a short written summary of the session, "Bản tin phiên", right under the index cards.

### Done
- **`App\Support\MarketBrief`** (pure, deterministic, no AI call, no extra query): from `MarketOverviewService::overview()` and the heat-map tiles it writes a one-sentence headline (`VN-Index giảm 11,59 điểm (0,66%) về 1.737,71. 266 mã tăng, 423 mã giảm. Thanh khoản 15,5 nghìn tỷ ₫ (+25,8% so với phiên trước).`; the verb is graded: gần như đi ngang / nhích / tăng-giảm / tăng-giảm mạnh) plus up to five cards: **Độ rộng** (who is in control, ceilings and floors), **Ngành** (strongest/weakest industry by value-weighted % among industries with ≥ 3 tiles and ≥ 2 % of the shown value, "Khác" excluded), **Các chỉ số** (VN30, HNX-Index, UPCoM-Index), **Nổi bật** (top gainer/loser of the all-exchanges board) and **Dòng tiền** (share of the market's value held by the five busiest stocks; left out rather than wrong when the tiles and the market total disagree). Every figure comes from the input; nothing is estimated.
- **Home page**: a glass card between the index cards and the heat map with a slowly moving gradient border, cards rising in one after another, and a **"Hỏi AI vì sao"** button that opens the AI chat and sends a question built from the numbers (the chat's per-account limits and the guest login hint apply as if typed). The 60 s poll (`brief` key of `GET /market/data`) rewrites the sentences in place (text nodes only) and blinks a card whose text changed.
- **Bug found and fixed on the way**: the AI chat closes itself on any click outside its bubble, so opening it from a mouse click on another widget was undone by the same click. `shared/ai-chat.js` now exports `askAi(text, {send})`, which opens the chat after the click has finished; the command palette's "Hỏi AI" uses it too (its own copy is gone).

### Tests
Group `marketBrief`: 20 pure tests (wording and number formats, verb thresholds, flat sessions, breadth tones, sector selection/weighting/thresholds/edge wording, indices, movers, concentration incl. inconsistent inputs, no invented figures) and 6 page tests (placement, ask question, poll wording, no data, escaping of hostile industry names, the deferred chat opening). Related groups (`marketHeatmap`, `commandPalette`, `homeLayout`, `marketOverview`, `aiFeatures`, `aiLimits`, `codeStyle`) green; Pint clean. Verified in Chrome with the real 8 Oct snapshot and with `fetch` stubbed (no real AI call).

---

## MARKET_HEATMAP_AND_COMMAND_PALETTE - October 3, 2026

### Summary
Owner asked for something "wow". Two pieces: a market heat map on the home page and a global Ctrl+K command palette.

### Heat map ("Bản đồ nhiệt thị trường")
- **What it shows**: the 150 busiest stocks of the newest snapshot as a squarified treemap grouped by ICB industry; tile size = traded value (the site has no market-cap data and the page says so), colour = % change (green/red, deeper towards the ±7 % daily limit, grey when flat). Hover (or keyboard focus) gives a card with name, industry, price, value, volume; clicking a tile opens the stock; clicking an industry name zooms into it (back button / Esc); exchange chips filter HOSE / HNX / UPCoM. The layout and colour maths are pure (`js/market/treemap.js`), `js/market/heatmap.js` moves the elements (CSS transitions) instead of rebuilding them, so filtering, zooming and the 60 s poll glide; tiles pop in on first paint and blink when their % changed.
- **Data**: `App\Support\MarketHeatmap` (pure shaping) + `MarketHeatmapService` (newest snapshot + `StockRepository::symbolInfo()` for names/industries, cached per snapshot) → `window.__MARKET__.heatmap` on the home page and the `heatmap` key of `GET /market/data`. A failure there never takes the page or the poll down (card simply absent / `null`). No Python is started.
- **Found on the way**: `stock_symbols.exchange` says `HSX` for every row, so the exchange filter could not use it. `py/get_market_overview.py` now writes each quote's exchange (HOSE/HNX/UPCOM/null) as the **11th element** of the quote array; older snapshots simply have none (the filter shows a friendly "no data yet" message until the next sync, `php artisan sync:market-overview`).

- **Fold / unfold** (owner: it takes a lot of room on the home page): a chevron button before the title (the title is clickable too) folds the card to one line with a summary (`150 mã · 37 tăng · 106 giảm · …`); the body folds by animating its grid row to `0fr`, so nothing is re-laid-out and it is out of the tab order once folded. The choice is kept in `localStorage` (`sunstock-heatmap`, wrapped in try/catch) and applied by a tiny script right after the card, before the first paint (no jump); with no stored choice it is folded on phones (< 768 px) and open on larger screens. `aria-expanded` / `aria-controls` kept in step.

### Command palette (Ctrl+K)
- Ctrl/⌘+K (or `/` outside a field, or the search button in the navbar / next to the phone menu button) opens a glass dialog: stock results from the existing `/stocks-list` endpoint (debounced, abortable), matching pages (accent-insensitive, with keywords), recent stocks (localStorage), and "Hỏi AI: …" which opens the AI chat with the question filled in (the AI endpoints keep their per-account limits). Arrow keys wrap, Enter opens, Esc closes, the highlight glides to the selected row, `aria-activedescendant` / combobox / listbox roles.
- The page list is server-side (`partials/command-palette.blade.php`): guests see Đăng nhập/Đăng ký, signed-in users their account; links are site-relative, the admin is never advertised. Results are built with text nodes (never HTML strings).
- The two old per-page Ctrl+K handlers (home, stock page) were removed so they cannot fight the palette.

### Tests
Groups `marketHeatmap` (PHP: pure shaping, service + cache, symbol lookup incl. hostile input, poll, home markup/order, failure tolerance, script-tag escaping, the real `analyse()` writing the exchange) and `commandPalette` (PHP: per-visitor pages, relative links, dialog accessibility, triggers, legacy handlers gone, no innerHTML from visitor data, reduced motion); Node: `treemap.test.mjs` (areas proportional and exact, no overlap, inside the box, aspect ratios, degenerate input, grouping/filters, layout containment, colour scale, label levels) and `palette.test.mjs` (highlighting incl. decomposed accents and markup, recent list, wrap-around selection, sections for each state). 489 PHP tests across the groups touching the layout/home page and 95 Node tests pass; Pint clean.

---

## MOBILE_BOTTOM_NAV - October 3, 2026

### Summary
Item 6 of the UX proposal: on a phone the whole site was reachable only through the hamburger menu. Now a bottom bar offers the five places people open most.

### Done
- **`partials/mobile-nav.blade.php`** (included by `layouts/app.blade.php`, phones only via `d-md-none` + a `max-width: 767.98px` stylesheet): Trang chủ, Cổ phiếu, Theo dõi, Danh mục and, for a signed-in user, Tài khoản (`profile.show`) or, for a guest, Đăng nhập. The current page is marked (`is-active`, `aria-current="page"`, filled icon, a small indicator on the top edge); everything else (funds, gold, rates, news) stays in the top menu.
- **`css/shared/mobile-nav.css`**: frosted bar with rounded top, safe-area padding for notched phones, tap feedback, the active icon pops once. The footer, the AI chat bubble, the back-to-top button, the exchange-rate FAB and the funds compare bar are all lifted clear of it.
- **`js/shared/mobile-nav.js`**: the bar slides away while scrolling down and returns on scroll up, near the top, at the end of the page and when a form field gets focus; the decision is the pure `nextVisible()`.

### Tests
Group `mobileNav` (8 PHP: items for guest/user, one active item per page, filled icon, phone-only markup and `aria-hidden` icons, the stylesheet's phone breakpoint, safe area, reduced motion and floating-button offsets, layout wiring) + 6 Node tests (`tests/js/mobilenav.test.mjs`). Verified at 390 px with Playwright: bar visible at the top, hidden while scrolling down, back on scroll up and at the page end, floating buttons stacked above it.

---

## HOME_PAGE_DATA_FIRST - October 2, 2026

### Summary
Owner asked for changes users see at once (item 2 of the UX proposal). The home page opened with a ~700 px blue hero (title, badge, two buttons, four marketing numbers) and the search box under it; the market overview started below the first screen. Now data comes first. A dark mode was tried first and reverted on the owner's request (it clashed with the blue brand look); nothing of it remains in the code.

### Done
- **Compact hero**: title, one-line subtitle and the search box share one ~280 px band; popular tickers, a "So sánh" chip and the Ctrl+K hint sit under the input. The "Dữ liệu cập nhật…" pill, the 700+/20+/AI/Free numbers and the hero's register button are gone (the guest banner at the bottom still offers sign-up).
- **Order of the page**: search → **market overview** (indices, VN-Index chart, breadth, liquidity, movers, watchlist) → AI prediction button → featured stocks → exchange rates → hot industries → news → (guests only) "Tại sao chọn Sun Stock AI?" → (guests only) sign-up banner. A signed-in user no longer gets the product pitch.
- Phone: input and a text-less search button on one row, smaller title, label hidden.
- No controller, service, route or JS change: `index.js` binds to the same ids/classes (`#symbol`, `.search-form-wrapper`, `.search-btn`, `.btn-text`, `#notFoundMsg`), `market/home.js` is untouched.

### Background and motion (second pass, same day)
Owner: the middle of the page looked empty and cut off from the blue hero. Now: the hero is one even blue and the market section opens with the same blue fading out behind the title and the index cards (`.mk::before`), first a split (plain top, tinted bottom) which read as separate pieces. Final: **one continuous background** (`.home-body`) chosen on the colour wheel, analogous blues (217° brand blue → 236° indigo → 252° violet → 268° orchid, same soft saturation) with one complement (amber 43°, the hero's accent) as a small warm glow; its first stop is the hero's bottom blue, so hero, market and the rest flow into each other. Cards are frosted glass (`rgba(255,255,255,.8)` + blur) so the tint shows through everywhere. More motion: a market line that draws itself across the hero, five glows drifting on different paths, a light sheen sweeping over an index card on hover. Motion: hero orbs and candle bars drift, index cards rise in one after another, sparklines and the VN-Index chart are revealed left to right, the breadth bar sweeps and the exchange bars grow, cards below the first screen use AOS, index levels and breadth counts count up, a card lifts on hover, and numbers flash green/red when the 60 s poll changes them (`home.js` + `shared/pricefx.js`). Every `animation` lives inside `@media (prefers-reduced-motion: no-preference)` (tested); decoration is `aria-hidden`.

### Tests
Group `homeLayout` (10; the first 7 cover the layout, the last 3 the shared background wrapper, scroll animation and the reduced-motion guard): search before market, nothing but the hero between them, script hooks kept, old marketing gone, order of the working blocks, guest pitch + banner last, signed-in user sees neither, page still renders when the market service has no data. `marketOverview`, `codeStyle` and `ExampleTest` still green; Pint clean.

### Not done
Phone bottom navigation (item 6) and the rest of the proposal.

---
## STOCK_PAGE_UX_REFRESH - October 2, 2026

### Summary
Owner: the recent work was invisible to users; propose and build changes people see at once. Package 1 + 4 + 5 of the proposal: new stock page header, modern price table, skeleton loading and number cues.

### Found while looking at the real page (Chrome)
The header printed **"62 VNĐ"** for a 62,100 ₫ share, the chart axis ran 10–140 and the table printed `63.7` with a `VND` badge on every row: the feed is quoted in thousands of VND and the page never converted it. Also: a ~380 px banner that was half empty with five buttons stacked at different widths, and a table of bare numbers.

### Done
- **`StockQuoteSummary`** (pure) converts once: stocks/ETFs in whole VND, indices in points; the page hands `{scale, decimals, unit}` to the script so header, chart axis, legend and table agree (62.100 everywhere; VN-Index stays 1.737,71).
- **New header**: symbol + exchange (HOSE/UPCoM) + company + industry, one row of actions, big coloured price, change chip (`-600 (-0,96%)`), day and 52-week range bars with the close marked, tiles for volume (× the 20-session average), open, and 1/3/6-month and 1-year returns. Phone layout checked.
- **New history table**: close, change in dong and %, open/high/low, volume with a thin bar (amber when ≥ 1.5× average), weekday under the date, no currency column, sticky header, paging window `1 … 4 5 6 … 102`; open/high/low drop out on a phone. Chart legend now spells Mở/Cao/Thấp/Đóng (it showed `C` for the high next to `Đ`).
- **Skeletons**: shimmering placeholders for the chart, the table and the finance block (which dims the old table when you switch tab instead of a spinner); honours reduced motion.
- **Number cues** (`shared/pricefx.js`): the price counts up from the previous close and flashes once red/green.
- Removed the old banner CSS. `VnFormat` gained `signed`, `volume`, `exchange`.

### Tests
Groups `stockPageUx` (PHP 34, Node 17 new). Full suite and Node suite green; Pint clean on touched PHP. Verified in the owner's Chrome (FPT and VNINDEX) and a 390 px phone emulation without horizontal overflow.

### Not done
Dark mode, home-page redesign and the phone bottom navigation (items 3, 2, 6 of the proposal) are still open.

---

## PUBLIC_ENDPOINT_ABUSE_PROTECTION - October 2, 2026

### Summary
Last open item of the security audit (A1): anonymous visitors could make the server start Python processes of up to a minute, limited only by a 30-requests-a-minute per-IP throttle, and so keep dozens running and burn the shared vnstock quota.

### Found (by reading every web-reachable `PythonRunner` call)
- `POST /company/{symbol}/load`: any 2–10 character ticker spawned a run (a made-up symbol took ~60 s to come back "not found"). A loop over random symbols = ~30 concurrent subprocesses.
- `GET /exchange-rate/search?date=`: only checked inside the Python wrapper, so **the raw text was used as a cache key** (unbounded keys, and an array parameter crashed it), every valid date missing from the database spawned a run, and **a failed or refused fetch cached `[]` for 30 minutes**.
- `?force=1` and `/gold/refresh`: manual refresh buttons open to everyone (the gold one shares a global cooldown, so a guest could also burn it).
- Already fine: stock pages (`StockPriceFreshness::isKnown`, failure memory), fund detail (stored codes only, cached for hours), market overview / ETF / fund lists (single flight).

### Fixed
- **`PythonWebGuard`** (new, used by `PythonRunner::run()` for web requests only): global ceiling of 3 concurrent processes and a per-visitor budget (guest IP 4/min and 20/h, signed-in user 10/min and 60/h), counted only for processes that really start; a refused call returns a `refused` result that callers already treat as "source unreachable". All numbers in `.env` (`PYTHON_WEB_*`, `config/python_limits.php`, floored at 1). Queue workers, scheduler and artisan are not limited.
- Company profile: only listed symbols (or ones with a stored profile) reach Python — a made-up ticker is now a 404 in ~0.3 s instead of ~60 s; forced refresh needs a login (401 JSON with `login_url`).
- Exchange rates: a searched date must be a real `Y-m-d`, not in the future, at most 3 years back, otherwise the page shows "Ngày không hợp lệ" before any cache or Python; empty or refused answers for the latest rates are no longer cached (a refused date is not cached either; a genuine "no rates that day" still is).
- `/gold/refresh`: login required, and a guest no longer touches the shared cooldown.

### Verified
Live: 5 made-up symbols → 404 in 0.3–0.7 s each, guest `force=1` and gold refresh → 401, bad dates → warning. Tests (group `webPythonGuard`, 40) count real process starts with a stand-in executable. Full suite 871 passed.

### Not done
Fund detail and the stock first-load still use their own protections plus the new guard; the paid AI endpoints were handled earlier (login + per-account limits). No CAPTCHA.

---

## IMPORTS_CLEANUP - October 1, 2026

### Summary
Owner: classes were written out in full inside the code (for example `\App\Models\User::STATUS_ACTIVE` in a Blade view) — a very serious cleanliness defect that must be forbidden for every AI agent.

### Done
- Scanned the whole project with the PHP tokenizer: **165 inline fully-qualified names in 57 PHP files** (45 `\Mockery`, 30 `\RuntimeException`, 21 `\Throwable`, 13 `\Exception`, models, jobs, commands, interfaces…) and **50 in 21 Blade views**, plus two doc comments. All replaced by imports (`use` in PHP, `@use` in Blade); no name clash existed, so no alias was needed. PHP was rewritten with Pint restricted to the import rules only (the rest of those files was left as it was).
- Rule written into `AGENTS.md` (new "Imports" section and a row in the forbidden-patterns table), `docs/GUIDELINES.md` and the assistant's memory.
- Enforcement: `pint.json` (Laravel preset + `fully_qualified_strict_types` with `import_symbols`, global classes imported) and `tests/Unit/CodeStyle/NoInlineFullyQualifiedNamesTest.php` (group `codeStyle`, 4 tests) which fails on any inline name in PHP, doc comments and Blade.
- Verified: full suite green, `view:cache` compiles every view, public pages return 200.

### Found while checking the views, then fixed
`/profile/edit` returned a 500 for an account with no `user_profiles` row: the view read `$profile->username` and `$profile->mobile` on null. The existing tests only asserted which view the controller returned, so nothing rendered it. The form now falls back to the account name (and an empty mobile), opening the page does not create a row, and saving creates it. New `ProfilePagesRenderTest` (group `profile`, 5 tests) renders both pages for real: no profile, stored profile, old input, a hostile name, and the create-on-save path.

---

## AUTHORIZATION_HARDENING - October 1, 2026

### Summary
Follow-up of the login/register audit: scan of authorization (frontend ownership, admin RBAC, privilege escalation) and the agreed fixes. Decisions by the owner: (1) only `admin` may change users/roles/permissions; (2) account status 0 inactive / 1 active / 2 pending / 4 blocked; (3) AI chat and prediction only for signed-in users with per-account limits read from `.env`.

### Scan (attacks run as tests: user B vs user A, every admin route × role, delegation abuse)
Safe already: ownership of portfolios, items, transactions and watchlist (reads 404, writes change nothing); route × role gates (56 admin routes); CSRF and method checks on admin writes; sensitive files not served.
Found and fixed:
1. **Privilege escalation through delegated permissions** — a `manage-users` holder could make themselves or anyone admin; a `manage-roles` holder could grant their own role any permission. Now every write in users/roles/permissions needs the `admin` role (`AdminOnlyForChanges`), reads stay permission-based.
2. **Admin lock-out** — an admin could remove their own admin role, rename the `admin` role (everyone loses `/admin`, because access is checked by role name) or rename/strip the core `manage-roles`/`manage-permissions`. `AdminGuard` forbids it; the last effective admin is protected.
3. **"Suspend" did nothing** — an un-verified administrator kept access, and open sessions of blocked people kept working. Replaced by real statuses (below) and `EnsureUserIsActive`, which ends such sessions on the next request.
4. **No audit trail** for user/role/permission/portfolio/queue changes — now logged with actor, IP, target and before/after (never secrets).
5. **Dashboard leaked** user e-mails, portfolio owners and the activity feed to roles without `manage-users` / `manage-features` / `view-timeline`.
6. **Admin forms bypassed the policies** (weak passwords, `<script>` names, free-form stock symbols) — now `AuthRules` everywhere; symbols `A-Z0-9{2,10}`.
7. `/portfolio/99999999999999999999` and similar oversized ids gave a 500 — route patterns make them a 404; `roles`/`permissions` `show` routes (no method behind them) removed; manual sync no longer returns the exception text.
8. **Anyone could call the paid AI endpoints** and spawn work — see below.

### Account status
Migration `2026_10_01_000001_add_status_to_users_table` (default 2, back-filled: confirmed e-mail → 1, else 2). Only active + confirmed e-mail can sign in; reasons are shown after the right password only; admin login always answers like a wrong password; a verification link / password reset turns pending → active and never unblocks. Admin user form has the four statuses; `User::applyStatus()` keeps the e-mail confirmation consistent. Because pending accounts cannot sign in, the "resend verification" endpoint is now public (per-IP and per-address limits, same answer for any address, mails only pending accounts) with a form on the login page.

### AI access and limits
`/ai-chat` and `/ai-predict` require login (401 JSON with a readable message and `login_url` for AJAX). Limits are per account, from `.env` (floored at 1): `AI_PREDICT_INTERVAL_MINUTES=15` (one prediction per interval), `AI_CHAT_WINDOW_MINUTES=5` and `AI_CHAT_MAX_QUESTIONS=5`. Over the limit: 429 JSON with a Vietnamese message and `retry_after`; nothing is sent to Groq. Documented in `.env.example` and `config/ai_limits.php`.

### Seeder for manual testing
`DemoStaffSeeder` (local only): `webadmin@`, `support@` (can open /admin, not admins), `pending@`, `inactive@`, `blocked@sunstock.test`, shared demo password.

### Tests
New groups `authzSecurity` (67 + AdminGuard rules), `accountStatus` (46 incl. guard tests), `aiLimits` (18); `demoUsers` +6. Legacy tests adapted (role/permission writes as admin, AI endpoints signed in, watchlist/verification flows). Full suite 805 passed (was 665). Pint run on every touched PHP file (the repo had never been formatted, so some old files changed style).

### Status propagation re-check (same day)
Owner asked to verify the new status is applied everywhere from the frontend to the admin. Walked every place that authenticates, e-mails, schedules or lists users and fixed the gaps: one `User::scopeMayEnter()` now defines "may enter" for both logins and `AdminGuard`; blocked/inactive accounts no longer get reset mails and an old reset token is refused; price-alert e-mails and the scheduled price refresh skip owners who may not sign in (the alert flag is left for later); the dashboard counts by status; the admin user list can be filtered by status. Tests found two real bugs in the new filter ("abc" silently meant status 0, and `when(0, …)` dropped the inactive filter) — both fixed. Remember-me, public pages, AJAX and registration-after-block are covered by tests. +17 tests (822 in total).

### Not done / for the owner
`.env` still has `APP_DEBUG=true`/`APP_ENV=local` (stack traces are returned to the browser): set `false`/`production` when deploying. Other public endpoints that call Python (`/company/{symbol}/load`, `/gold/refresh`, `/stock/*`) are still anonymous, per-IP limited only. No CAPTCHA/2FA.

---
## AUTH_SECURITY_AUDIT - October 1, 2026

### Summary
Owner asked for a full security review of login/register (XSS, CSRF, SQL injection, brute force…), a check of existing tests, a double-check in the browser, simulated attacks, and fixes. The comment feature mentioned afterwards does not exist in the codebase (no model, route or view), so there is nothing to audit there yet.

### Method
Read every auth controller/route/middleware/config, then attacked the running stack with `curl` (isolated cases, limiter reset between them), then re-ran the same attacks after the fixes, then confirmed in the browser pane (reflected payload shown as text, no script executed). Throw-away accounts used for the attacks were deleted afterwards.

### Already safe (verified live and now covered by tests)
CSRF is enforced on every credential POST (419); SQL-injection payloads in e-mail/password/username/token never authenticate and never break the query (Eloquent bindings, array/JSON inputs rejected); mass assignment on register ignored (`role`, `email_verified_at`, `id`…); output is HTML-escaped everywhere user text is shown; session cookie is `Secure`, `HttpOnly`, `SameSite=Lax`; session id regenerated on login; open-redirect parameters ignored; spoofed `X-Forwarded-For` does not reset limits; 1 MB passwords cost 0.3 s (no hashing DoS).

### Vulnerabilities found and fixed
1. **Password-reset poisoning (critical).** URLs in e-mails were built from the request `Host` header: `POST /forgot-password` with `Host: evil.test` made the victim receive a real reset token in a link to evil.test (same for the verification link). Now every URL is pinned to `APP_URL` (`pinRootUrl`), plus `trustHosts` for non-local environments.
2. **Account enumeration on `/reset-password`**: an unknown address answered "Không tìm thấy tài khoản", a known one "Link không hợp lệ". Same message now. The admin login also told a non-admin with a correct password that the account "has no admin rights"; now identical to a wrong password.
3. **Brute force protection was weak**: a single `throttle:5,1` shared by GET pages and POSTs per IP (two page views locked a person out) and nothing per account (a botnet could try unlimited passwords). Replaced by named limiters (per IP, per account+IP, per account per hour) on the POSTs only; forgot-password is also capped per recipient (inbox flooding).
4. **Stored XSS payloads were accepted** as username/mobile (`<script>` was saved). Output escaping stopped it from firing, but the data reached logs/e-mails/exports. Username and mobile are now whitelisted (`AuthRules`), in register and profile; toast helper uses `textContent`.
5. **Weak passwords / unbounded length**: min 8 only. Now 8–128 with a letter and a digit (register, reset, profile, admin account).
6. **Sessions survived a password change.** `authenticateSessions()` added: changing or resetting the password signs every other device out; the profile/admin password forms re-stamp the current session.
7. **No security headers.** Added `SecurityHeaders` middleware (frame-ancestors/clickjacking, nosniff, Referrer-Policy — `no-referrer` on reset URLs, Permissions-Policy, HSTS, form-action/base-uri/object-src CSP, `no-store` on auth pages); nginx `server_tokens off`; `expose_php = Off`. A full script-src CSP needs a nonce rollout (the site uses CDN and inline page-data scripts) and is not done.
8. **Registration was not atomic** (user could exist without profile/role) and failures were swallowed silently: now one transaction and `report($e)`.
9. **Verification dead end (functional)**: login refuses unverified accounts but the verify link required being logged in, so a new user could never verify. The link now works from a signed URL (+ hash of the current e-mail) without a session, and completing a password reset also verifies the mailbox. Login no longer creates a session (or remember cookie) for an unverified account.
10. Failed logins are now logged with IP.

### Not changed / for the owner
- Registration still says "Email đã được sử dụng" (needed for usability; mitigated by the register limiter).
- No CAPTCHA, no 2FA, no compromised-password check (needs an outbound call).
- `APP_DEBUG=true` / `APP_ENV=local` in `.env`: must be `false` / `production` when deployed (stack traces leak otherwise). `.env` was not touched.
- Guests who lost the verification e-mail cannot request another one (resend needs a session); an admin can still verify them.

### Tests
Group `authSecurity`: 120 tests (CSRF, SQLi, brute force variants, enumeration, mass assignment, XSS, Host poisoning, token/verification abuse, admin probing, headers, session invalidation, rule boundaries). Two fixtures in `AccountControllerTest` used a password without a digit and were updated; the old test that asserted the leaking message was replaced. Full suite: 665 passed.

---
## COMPANY_PROFILE_QUEUE_FAILURES - October 1, 2026

### Summary
Admin > Giám sát Queue showed ~44 failed `SyncCompanyProfileJob` after a sync, all with "Không lấy được dữ liệu công ty (nguồn dữ liệu chậm hoặc lỗi kết nối)".

### Root cause
Not the queue and not load: even a single `sync:company-profiles --symbol=FPT` failed after 61s. Timing each vnstock call separately: KBS answered in 0.2–1.4s, but **VCI (`iq.vietcap.com.vn`) timed out** (30s read timeout, retried 3 times by vnstock = ~95s). `get_company_profile.py` already isolated failing sections, but it waited for every thread, so it outlived `PythonRunner`'s 60s ceiling, was killed, printed nothing, and the job threw — discarding the good KBS data.

### Fix
- `py/get_company_profile.py`: `DEADLINE_SECONDS = 40`; sections that have not answered are reported in `errors` and everything else is returned; `os._exit(0)` so a stuck retry thread cannot hold the process. With VCI still down FPT now returns in 40s with overview, ownership, officers, subsidiaries from KBS.
- `CompanyProfileService::sync()`: a partial result (`errors.vci_events`) no longer overwrites the stored corporate events.
- Restarted the queue workers (they keep old PHP in memory) and retried the failed jobs.

### Tests
Group `companyProfile`: +3 (events survive a partial refresh, a clean refresh replaces them without reading the old row, the script's deadline is below the PHP timeout and it exits hard). 44 pass. Also fixed a flaky `goldPrice` test that failed when run within 30 minutes after Vietnamese midnight (it used the real clock; now frozen at midday). Full suite: 545 passed.

### Not fixed (outside our control)
VCI being slow/down: valuation snapshot, events and the long shareholder list are missing from profiles fetched while it is down, and they fill in on a later refresh.

---

## Earlier work — one-line digest

Full text: [2026-09](history/2026-09.md) · [2026-06](history/2026-06.md) · [2026-05 and earlier](history/2026-05-and-earlier.md).

### September 2026 — [archive](history/2026-09.md)

**Sep 30**
- **PORTFOLIO_AUDIT_AND_INSIGHTS** — Owner: users lose interest in the portfolio feature. Asked to (1) audit its UI/UX and current features, (2) seed 4–5 more accounts, (3) build wave 1 of the suggested improvements (benchmark comparison, risk, sector split).
- **ETF_PAGES** — Owner asked for an ETF page after the vnstock capability survey (idea #8). Explored what the feed really has for ETFs before designing anything.
- **CLAUDE_CONFIG_AND_DOCS_REREAD** — Owner asked to re-read AGENTS.md and every file in `docs/`, and to create a `.claude` folder if useful.

**Sep 20**
- **HISTORY_AND_CHANGELOG_CONDENSED** — User: one day had too many README changelog rows and `docs/HISTORY.md` had grown to 1,429 lines / 160 KB.
- **README_SCREENSHOTS_AND_WEBSITE_TOUR** — User: the README Screenshots section was outdated; re-shoot the site's highlights, add a detailed page-by-page `.md`, and make sure nothing sensitive (usernames, passwords, security-related or attackable surfaces) is pictured.
- **VNAI_AGENTS_MD_INJECTION** — User asked why `AGENTS.md` kept showing as modified and was never pushed. Cause: the `vnai` package (dependency of vnstock) appends an 86-line `vnai-bootstrap` "skill router" prompt to `AGENTS.md` every time a Python script run…
- **AI_PREDICT_AND_CHAT_FIX** — User reported two problems: (1) the home page "AI Dự đoán thị trường tuần này" answered "Dịch vụ AI tạm thời không khả dụng…"; (2) the AI chat popup opened but no button worked, typing failed and any message ended in the same s…
- **WEEKLY_DB_BACKUP** — After Docker Desktop lost all containers/images (recovered, volumes intact) the user asked for an automatic weekly database backup stored OUTSIDE Docker: a `database_backup` folder beside the source tree (path derived from wher…
- **ADMIN_REDESIGN_2026** — Task 3 of three. User: the admin backend looks like 2010s UI/UX; find a free, current template and redesign the whole backend to keep users engaged.
- **STOCK_PAGE_FIRST_LOAD_SPEED** — Task 2 of three. User: the first click on any symbol (search or link) takes ~10 s, later ones are fast because of the cache — bring it to ~3–4 s.
- **MARKET_OVERVIEW_WATCHLIST_LEDGER** — Task 1 of three: make users come back every day (market overview + watchlist) and make the portfolio worth using (a buy/sell ledger with realised P&L).
- **GOLD_PRICE_PAGE** — User asked for a gold-price feature (vnstock has it) and to put it in one menu with the exchange rate, as two submenus.
- **PORTFOLIO_REWORK** — User reported the portfolio (frontend + admin) had ugly buttons, buttons that "sometimes work", and little value to users (low usage in production). Audit found real bugs, not just looks.

**Sep 19**
- **ADMIN_SIDEBAR_AND_ACCOUNT_UI_FIXES** — 1. **Sidebar sub-menus** (Hệ thống: Quản lý Users / Vai trò / Quyền hạn / Giám sát Queue; also Tính năng) rendered side by side and wrapped: the sub-menu container was `<div class="nav collapse">` and Tabler's `.nav` is a horiz…
- **SEARCH_AUTOCOMPLETE_REDESIGN** — User found the symbol-search dropdown ugly: the highlighted row was a heavy colour and the hovered row looked identical to the keyboard-selected one, so two rows appeared "selected" at once.
- **COMPANY_PROFILE_FUND_CATALOG_LIGHTWEIGHT_CHARTS** — User asked for two features suggested from free vnstock APIs — a company profile page (#1) and an open-ended fund catalog (#3) — then asked to replace the "ugly" ApexCharts with TradingView Lightweight Charts, downloaded locall…
- **QUEUE_DELETE_ALL_FAILED_JOBS** — Admin > Giám sát Queue's "Job thất bại" card only had "Retry tất cả"; user asked for a matching "Xoá tất cả" button.

**Sep 17**
- **ADMIN_ACCOUNT_AND_NEWS_CATEGORIES** — survey of admin gaps; self-service password page `/admin/account` (no permission gate) and news-category CRUD that refuses to delete a category still used by RSS sources.

**Sep 16**
- **QUEUE_MONITOR_REALTIME_ACTIVITY** — `queue_job_logs`: which job runs now, elapsed time, recent jobs, processed-today counter.
- **PYTHON_EXEC_TIMEOUT_FIX** — every Python call goes through `App\Support\PythonRunner` (Unix `timeout`), because Laravel's pcntl-based job timeout cannot interrupt a blocked `exec()` (a "300 s" job had run 9+ minutes).
- **QUEUE_MONITOR_CUSTOM_PAGE** — Horizon removed as too hard to read for this app; a small in-app monitor (Controller/Service/Repository) replaces it; Redis queue kept.
- **SYNC_SPEED_OPTIMIZATION** — queue workers 3 → 6 for the I/O-bound sync jobs.

**Sep 15**
- **QUEUE_MONITORING_HORIZON** — Horizon added to watch a growing Redis backlog; fixed `retry_after` < `timeout` misconfiguration (later replaced, see above).
- **DOCS_CONSISTENCY_AUDIT** — every `.md` checked against the code; wrong command names, missing setup steps and stale RBAC text fixed.
- **SCALABLE_PERMISSIONS_SYSTEM** — DB-driven `permissions`/`permission_role` replace hard-coded Gates; Admin UI for roles and permissions.
- **FIX_TEST_SUITE_LAST_FAILURE** — the long-standing `ExampleTest` failure fixed (partition migration made MySQL-only); suite green.
- **PROFILE_EXTENDED_FIELDS** — birthday, gender, address, bio and avatar upload on the profile.
- **PROFILE_AND_PORTFOLIO_AUDIT** — crash for admin-created users without a profile row fixed; portfolio totals hardened against drift.
- **ADD_FORGOT_PASSWORD_FEATURE** — reset by e-mail on Laravel's `Password` broker with a generic response (no user enumeration).

**Sep 14**
- **AUTH_AUTHZ_AUDIT_AND_TESTS** — guests hitting `/admin/*` now go to the admin login; RBAC/Gate tests.
- **FIX_SCREENER_INLINE_CSS_AND_DATE_ICON_ROUND2** and **FIX_SCREENER_UIUX_AND_EXCHANGE_RATE_DATEPICKER** — screener redesign with its own CSS and proper Vietnamese; exchange-rate date picker.
- **FIX_EXCHANGE_RATE_PAGE_BROKEN** — Python output was concatenated before JSON decoding; now the last JSON line is used (the pattern every caller follows).
- **MANDATORY_TESTING_RULE_AND_FIRST_TEST_SUITE** — `#[Group('feature')]` rule, `docs/TESTING.md`, in-memory sqlite tests.
- **PORTFOLIO_REAL_PRICES_ALERTS_AND_SCREENER** — real prices instead of `rand()`, target/stop-loss e-mail alerts, stock screener.

### June 2026 — [archive](history/2026-06.md)

**Jun 27**
- **BACKEND_ADMIN_UPGRADE** — admin panel on real data, activity log, sync management.
- **SYNC_NEWS_SCHEDULER_MISSING** — `/news` showed only 4-week-old articles because `sync:news` was never run by the scheduler.
- **COMPANY_FINANCIALS_AND_BACKEND_USER_LAYER** — `company_financials` table/model and the backend user service layer.

### May 2026 and earlier — [archive](history/2026-05-and-earlier.md)

**May 30**
- **NEWS_RSS_SYSTEM** and **NEWS_CATEGORIES_FRONTEND** — RSS → `news` table (dedup by URL hash), categories FK, `/news` page.
- **STOCK_ADMIN_SERVICE_LAYER** — admin StockController moved onto the Controller→Service→Repository pattern.
- **AI_GROQ_MIGRATION_AND_SECURITY_HARDENING** — OpenRouter (404/429) replaced by Groq; timeout, XSS escaping, prompt-injection hardening.
- **DOCKER_MIGRATION** — Nginx, PHP-FPM, MySQL, Redis, queue and scheduler containers with HTTPS.
- **IDEMPOTENCY_SYNC_SKIP_EXISTING_DATA** — sync/backfill commands skip data already present (`--force` overrides).

**May 29**
- **COMPANY_FINANCIALS_DB_CACHE_AND_ARCHITECTURE** — financials cached in the DB instead of a live vnstock call per request.
- **TECHNICAL_INDICATORS_AND_COMPANY_FINANCIALS** — MA/Bollinger/RSI/MACD on the stock chart; financial statements.
- **HISTORICAL_BACKFILL_PARALLEL_QUEUE** — history back to 2018 with 6 parallel workers.
- **DATABASE_SCALABILITY_PARTITIONING** — `stock_prices` partitioned by year.
- **FIX_VNSTOCK_DEPRECATED_API_AND_SCHEDULER** — vnstock 4.x API change and Laravel 12 scheduler fixed after data froze at 2026-05-15.

**May 28**
- **AUTO_SYNC_BACKGROUND_SCHEDULER** — slow pages (hot industries, exchange rates) now filled by the scheduler instead of on request.
- **DOCUMENTATION_AUDIT_AND_EXPANSION** — STRUCTURE/GUIDELINES brought in line with the code.

**May 16** — **SEARCH_AND_DATA_INTEGRITY** — search auto-select bug, missing ETF data.

**May 1–2 and January 2025** — **CLEANUP_OLD_FILES**, **RBAC_SYSTEM_AND_ADMIN_DASHBOARD**, **RBAC_SYSTEM_MULTIPLE_ROLES_AND_SEPARATE_AUTH**, **SYSTEM_ERROR_FIXES_AND_EMAIL_VERIFICATION**, **AUTHENTICATION_SYSTEM**, **PORTFOLIO_MANAGEMENT_FEATURE** — legacy cleanup, roles and separate admin auth, e-mail verification, profile, first portfolio feature.

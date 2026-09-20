# Feature Update History

The **latest two days** are kept here in full, newest first. Everything older lives verbatim in [docs/history/](history/) and is summarised in one line per entry under [Earlier work](#earlier-work-one-line-digest). The README changelog has one row per day.

**Maintaining this file:** add new entries at the top. When this file passes ~400 lines, move the entries that are more than two days old to `docs/history/<YYYY-MM>.md` (verbatim, keep the `## NAME - Month D, YYYY` title) and add a one-line digest for each below.

---

## HISTORY_AND_CHANGELOG_CONDENSED - September 20, 2026

User: one day had too many README changelog rows and `docs/HISTORY.md` had grown to 1,429 lines / 160 KB.

- **README**: both changelogs (EN + VI) now have **one row per day** (16 rows for 2026-09-20 became 2; the three 2026-09-15 / 2026-05-29 / 2026-05-30 groups merged the same way).
- **docs/HISTORY.md**: 1,429 → ~360 lines. Only the latest two days stay in full; every older entry moved **verbatim** to `docs/history/2026-09.md`, `2026-06.md` and `2026-05-and-earlier.md` (41 entries) and is summarised in one line under "Earlier work". Verified lossless: all 1,028 non-empty lines of the old file exist in the new files.
- References updated (AGENTS.md, GUIDELINES.md with the maintenance rule, PYTHON_INTEGRATION.md, two test comments).

---

## README_SCREENSHOTS_AND_WEBSITE_TOUR - September 20, 2026

User: the README Screenshots section was outdated; re-shoot the site's highlights, add a detailed page-by-page `.md`, and make sure nothing sensitive (usernames, passwords, security-related or attackable surfaces) is pictured.

**Done:** 18 JPEGs in `docs/screenshots/` (home + market overview, stock chart with MA/Bollinger/RSI/MACD, company profile, compare, screener with real filters, AI chat + prediction, funds list + detail, gold, FX, portfolio list/detail/holdings, watchlist, two mobile shots), `docs/WEBSITE_TOUR.md` (16 sections: what each page shows, data source, refresh), README gallery rewritten; all 8 old images removed from `public/images/` (they were outdated; `ss_login`, `ss_register` and `ss_portfolio` were also the kind of screen we no longer publish - the portfolio one showed a test username; they remain in git history).

**How:** headless Chrome via playwright-core driven from the scratchpad (nothing added to the project), host mapped to the local stack. Signed-in pages used a throwaway demo user + sample portfolio created for the shoot and **deleted afterwards** (verified 0 rows left).

**Sensitive-content review:** login/register/forgot-password, profile/account and the whole admin panel are not pictured; no URL bar in captures; every image viewed at full size; JPEGs contain no EXIF/XMP/comments; text scan of the images' bytes and of the new docs for e-mails, tokens and keys found nothing; the news page was left out (third-party photos and named people in headlines).

**Bugs found and fixed while shooting:** (1) gold page: the SJC sell price rendered as `147.600.0` (KPI grid too narrow) - `auto-fit` grid + `nowrap`; (2) compare page had ASCII-folded Vietnamese (`So Sanh Co Phieu`, `Bieu do tang truong`) - diacritics restored; (3) fund detail showed 'Chưa lấy được dữ liệu chi tiết' for web requests - `PythonRunner` fell back to a shared `/tmp/python-home` created 0700 by root (artisan/queue), so `www-data` got `[Errno 13]` on `~/.vnstock/api_key.json`; now `python-home-<uid>` (+1 assertion). Also: recreating the `php` container leaves nginx pointing at the old IP (502) - `docker compose restart nginx`.

**Not done / notes:** screenshots are static (re-shoot after big UI changes); the stock/compare pages still show prices in thousands of VND by convention.

---

## VNAI_AGENTS_MD_INJECTION - September 20, 2026

User asked why `AGENTS.md` kept showing as modified and was never pushed. Cause: the `vnai` package (dependency of vnstock) appends an 86-line `vnai-bootstrap` "skill router" prompt to `AGENTS.md` every time a Python script runs in the repo. No secrets in it, but it is untrusted third-party instructions in a file AI assistants obey (load skills, hit an external license URL with an API key, "never write files to disk"). Fixed at the source: `PythonRunner::noAgentSetupEnv()` + compose env `VNSTOCK_DISABLE_AGENT_SETUP=1`; `AGENTS.md` restored to the committed version; +1 test (`pythonRunner`). Verified: Python ran again after the change and `git diff AGENTS.md` stayed empty.

---

## AI_PREDICT_AND_CHAT_FIX - September 20, 2026

### Summary:
User reported two problems: (1) the home page "AI Dự đoán thị trường tuần này" answered "Dịch vụ AI tạm thời không khả dụng…"; (2) the AI chat popup opened but no button worked, typing failed and any message ended in the same service error.

### Root causes:
1. **Every model was retired.** `AiService` hard-coded `llama-3.3-70b-versatile` (404 for this key), `llama3-70b-8192` and `gemma2-9b-it` (both 400 `model_decommissioned`) — visible in `laravel.log`. On top of that `predictMarket()` cached the fallback *text* for 2 h, so even a recovered provider would have kept showing the error.
2. **Dead popup.** `layouts/app.js` is loaded by Vite as an ES module, so `closeAiChat`, `sendAiChat`, `setAiQuestion`, `clearAiChat` are module-scoped, not global; the Blade view still used inline `onclick="sendAiChat()"` etc. → `ReferenceError` on every click. (Enter used `keypress`, which also fires on the Vietnamese IME's confirm key.)

### Changes:
- `AiService`: models from `GROQ_MODELS` (comma list) or `DEFAULT_MODELS` (`openai/gpt-oss-120b`, `openai/gpt-oss-20b`, `groq/compound-mini`, `qwen/qwen3.8-27b` — verified against `GET /openai/v1/models` for this key), `tryAsk()` returns `null` on failure, `ask()` keeps the old message API, `max_tokens` 2048 (reasoning models), `<think>` stripped, cache key `ai_market_predict_v2_…` (drops the cached error) and only real answers are cached. Controllers return **503 + `message`** instead of an error string in a 200.
- **Grounding:** the raw answers invented numbers (VN-Index "≈1 200" while it was 1 815,66; made-up CPI and revenues). `marketContext()` now appends the real snapshot from `MarketOverviewService` (indices, breadth, liquidity, top 5 movers) to the prediction prompt, and both system prompts forbid inventing figures. Re-run against the live API: the answer quotes the real 1 815,66 / 426 mã tăng / 22 934 tỷ.
- Frontend: `shared/ai-chat.js` (addEventListener only, `keydown` + `isComposing` guard, busy state, typing indicator, Esc/outside-click close that ignores nodes removed by "clear", 503 message shown, user text via `textContent`), `shared/ai-text.js` (Markdown-lite incl. tables, escape-first), home prediction rewritten to use it and stay retryable after a failure.

### Tests:
PHP 435 → 454 (`aiFeatures`, 19), Node 27 → 36. Full suite green.

### Verified / not verified:
Live Groq calls through `AiService` (prediction 3–6 s, chat ~3 s). The UI logic was exercised in the browser pane on the real page markup with the bundled module injected and `fetch` stubbed — the pane blocks the app's own assets and same-origin requests, and Chrome could not resolve `sunstock-local.dev`. Checked there: open/close/clear/chips/language flag, send button, Enter, IME-Enter ignored, busy state, 503 error bubble, table/list rendering, a `<script>` in the answer stays text. Not done: a click-through on the real page in a normal browser (please hard-refresh once so the new build loads).

---

## WEEKLY_DB_BACKUP - September 20, 2026

### Summary:
After Docker Desktop lost all containers/images (recovered, volumes intact) the user asked for an automatic weekly database backup stored OUTSIDE Docker: a `database_backup` folder beside the source tree (path derived from where the project lives), laid out `year/month/day/<db>_db.zip` holding `<db>_db.sql`, run when Docker starts, skipped when a valid zip from the last week already exists (weekly, not daily, to save disk).

### Implementation:
- **`App\Support\DatabaseBackup`** — `mysqldump --single-transaction` into a temp dir, verifies the trailing `-- Dump completed` (truncation guard), zips, re-opens the zip to validate, then renames into `<root>/Y/n/j/<db>_db.zip`. Failure leaves nothing that could pass for a backup and never touches older ones. `latest()`/`isFresh($days)` only count readable zips that contain a non-empty `.sql`. Root = `config('backup.path')` or `dirname(base_path())/database_backup`.
- **`db:backup {--force} {--days=7} {--list}`** — skip message when fresh, exit 1 on failure.
- **Wiring** — `docker/php/Dockerfile` installs `default-mysql-client`; compose bind-mounts `../database_backup:/backups` into `php` + `scheduler` (`DB_BACKUP_PATH=/backups`); `scheduler-entrypoint.sh` runs `db:backup` first at every start; `bootstrap/app.php` also schedules it hourly (no-op unless the weekly window has passed — the stack may be off at any fixed hour).

### Verified:
Real backup taken by the scheduler's start-up run: `C:\xampp\htdocs\database_backup\2026\9\20\stock_app_db.zip` (39.2 MB from a 197.8 MB dump, 22 s); a second `db:backup` skips, `--list` shows it. Tests: 17 (`dbBackup`); PHP 418 → 435.

### Not done / caveats:
No automatic retention (each week adds ≈40 MB), restore is manual (steps in `docs/DOCKER.md` §6b, the restore itself was not rehearsed), only MySQL contents are covered, and the root cause of Docker Desktop wiping itself is unknown.

---

## ADMIN_REDESIGN_2026 - September 20, 2026

### Summary:
Task 3 of three. User: the admin backend looks like 2010s UI/UX; find a free, current template and redesign the whole backend to keep users engaged.

### Decision:
The admin already ran Tabler, but **1.0.0 from a CDN** (2022 look: dark sidebar, plain top bar, inline SVG icons, `window.confirm()`). Switching template family would have meant rewriting all 27 views for no gain, so the app moved to **Tabler 1.5.1** (2026, MIT, Bootstrap 5.3, theme system with light/dark/auto) and the shell was rebuilt around it: same markup family, new design layer. Bundled locally through Vite with Inter (variable, Vietnamese) and Tabler Icons — no CDN at runtime, cacheable, works offline in the intranet.

### Changed:
- **Shell** (`layouts/admin.blade.php`): light sidebar with section labels, active pill, failed-jobs badge and collapse-to-icons; sticky blurred top bar with **Ctrl/⌘+K command palette** (jump to any page you may open, accent-insensitive), theme switch (light / dark / auto, no flash on load), account menu; one navigation array feeds sidebar and palette, each entry behind its Gate.
- **Feedback**: server flash messages and script results are toasts; every destructive action uses a real confirm dialog (`data-confirm`, `window.adConfirm`) instead of the browser's `confirm()`.
- **Pages**: new split-layout login; new dashboard (KPI tiles, traffic-light health of the five data sources, system health: failed jobs / unverified emails / ★ / ledger usage, recent users/portfolios/activity, quick actions); Users, Stocks, Portfolios, News, Sync Status, Queue monitor and Timeline rewritten (toolbars in the card header, avatar cells, status dots, row actions, empty states); Timeline is grouped by day with per-type 7-day counts (and knows the new `watchlist_added` / `portfolio_trade` events); the remaining pages (roles, permissions, categories, forms, detail pages) got the new design layer, Tabler icons instead of inline SVG, and `data-confirm`.
- Vietnamese relative dates everywhere (`Carbon::setLocale('vi')`: "2 giờ trước" instead of "2 hours ago").
- Dashboard: the `COUNT(*)` over the 5M-row price table is cached 10 min (it was the slowest query on the page).
- Removed the unused `css/layouts/admin.css` and `js/layouts/admin.js`.

### Bugs found on the way:
`backend/timeline/index.blade.php` contained two complete, contradictory `@section('content')` blocks (the second silently won and used array access on model rows); replaced by one clean view. Inline `<script>` blocks pushed by pages run before the ES module, so shared helpers are only used from event handlers (documented).

### Tests:
PHP 401 → 418 (`adminShell`), Node 20 → 27. `php artisan test` 418/418, `npm test` 27/27.

### Verified / not verified:
Every admin page rendered without server errors and inspected in the browser pane via inline static copies (light and dark; dashboard, sync status, queue, timeline, user form, login). Not exercised in a real click-through: the palette and confirm dialog run only in the browser (their pure logic is unit-tested), and dark mode was checked on the dashboard only. The pane blocks the app's own assets, so previews embed the built CSS/fonts.

---

## STOCK_PAGE_FIRST_LOAD_SPEED - September 20, 2026

### Summary:
Task 2 of three. User: the first click on any symbol (search or link) takes ~10 s, later ones are fast because of the cache — bring it to ~3–4 s.

### Root causes (three, not one):
1. **The web path fetched data and threw it away.** `StockRepository::updateStockPriceFromPython()` read `$item['date']`, but `get_stock.py` only produced the epoch-ms `time`; the queue job converted it, the web path did not. So the ~10 s fetch stored **nothing**, and the only reason later clicks were faster was the 1-hour cache *flag*, not data.
2. **It ran for every symbol, every hour**: `latest bar < today` is true for any symbol until the evening sync (and all weekend), so the whole-year fetch ran inside the request again after each expiry.
3. **The source was slow/unreliable**: VCI only. From the container it failed with `ConnectionError` after ~99 s of retries; when merely slow ~10 s. On top of that, `import vnstock` costs a fixed ~1.7 s at every script start.

### Changed:
- `py/get_stock.py`: **KBS-direct** first (stdlib HTTPS GET of KBS's public history endpoint; no vnstock import), then vnstock KBS, then VCI; explicit `date` field; optional start date for incremental runs; no per-symbol sleep for a single-symbol call; an empty KBS-direct answer is authoritative (no fallback). Measured in the container: **VIC one year 0.94 s** (vs 4.2 s via vnstock-KBS, vs 99 s failing VCI); five symbols in one run 1.8 s.
- `StockPriceFreshness` + `TradingCalendar` + `RefreshStockPricesJob` (details in docs/PYTHON_INTEGRATION.md): never-synced → one short fetch (25 s ceiling, failure remembered 5 min); stale → served at once, ONE deduplicated incremental job; unknown/malformed codes → no Python and no junk `stocks` rows (they used to be created by any URL); newest session painted from the market snapshot (the script now also stores open/high/low per symbol) with a small badge.
- One routine stores bars (`StockService::storePriceData`), shared by the job path, the web path and the refresh job; the compare endpoint follows the same policy and fetches history-less symbols in one run.
- The bulk sync and the backfill use the same script, so they also get the faster source (dev data had gone stale because VCI kept failing).

### Measured (dev, Sunday, after the change):
| Case | Before | After |
|---|---|---|
| first-ever view of a symbol, via nginx + FPM | ≥ 10 s (~99 s + no data while VCI fails) | **1.15 s** (Python + KBS + page) |
| same page again | ~0.5 s once the 1 h flag was set, otherwise the full fetch again | 0.47 s |
| stale symbol (E1VFVN30, newest bar 6 sessions old) | full fetch, stored nothing | **70 ms** in-process; the queue worker filled it up to 2026-09-18 within seconds |
| first-ever view of an index (VNINDEX) | VCI | 0.43 s in-process |

### Also fixed on the way:
`getStockPrice()` no longer creates a `stocks` row for whatever code it is asked about; `validateSymbol()` used `$` (accepts a trailing newline) — now `\z`.

### Tests:
PHP 367 → 401 (`stockFreshness`), including the real `get_stock.py` against a fake KBS server. `php artisan test` 401/401, `npm test` 20/20.

### Not done / caveats:
The nightly per-symbol sync could be replaced by storing each session's bars from the market board (one request for ~1,500 symbols) — not done here, mentioned as a follow-up. Public holidays are unknown to `TradingCalendar` (one extra deduplicated background refresh on such a day). KBS is a single provider now in front of the slow ones; if it changes its endpoint the script falls back to vnstock KBS/VCI automatically (slower, still correct).

---

## MARKET_OVERVIEW_WATCHLIST_LEDGER - September 20, 2026

### Summary:
Task 1 of three: make users come back every day (market overview + watchlist) and make the portfolio worth using (a buy/sell ledger with realised P&L).

### Data source finding (drives the whole design):
`Trading.price_board()` on **KBS** answers all ~1,500 stocks in one request (~1 s) with price, reference, % change, volume and traded value, and KBS index history takes ~0.3–2 s — while the VCI source that the app used for everything else timed out at 30 s from the container. So one script (`py/get_market_overview.py`, ~4 s) feeds the home page, the ticker tape and the watchlist. Two traps found on the way: bonds/covered warrants make the board endpoint reject the request (filtered by listing `type`), and importing vnstock lazily from several threads deadlocks (imports moved before the pool).

### Added:
- **Market overview** on the home page: index cards with sparklines (VN-Index, VN30, HNX, UPCoM), VN-Index area+volume chart, breadth (advancers/decliners/unchanged/ceiling/floor — idle stocks are not "unchanged"), liquidity per exchange (compared with the previous session only once the session is complete), top gainers / losers / most traded with exchange filters, live status pill, 60 s poll while the market is open (verified end to end with a simulated session).
- **Real ticker tape** in the layout (the old one was hard-coded: fixed arrows and a static text).
- `market_snapshots` (one row per session; quote map on the newest row only), `MarketOverviewService` (stale-while-revalidate: 5 min in session / 6 h outside, first visit loads live once), `sync:market-overview` (every 5 min Mon–Fri 09:00–15:10 VN + 18:00), `SyncMarketOverviewJob`, admin Sync Status entry.
- **Watchlist**: `watchlist_items`, ★ buttons (home movers, stock page, company page), `/watchlist` page (live prices from the snapshot, last-close fallback, sparkline, sort, add with autocomplete, ceiling/floor flags, auto refresh), max 50, auth-only (no verified-email wall, unlike the portfolio).
- **Portfolio trade ledger**: `portfolio_transactions` (migration backfills an opening buy per existing holding), `PortfolioLedgerService` — buy = weighted-average cost including the fee, sell = frozen cost basis + realised P&L (fee includes the 0.1 % sell tax), over-sell rejected, undo of the newest transaction per symbol, everything in one DB transaction; UI: trade modal with live preview and auto fee estimate, buy/sell buttons per holding, realised/total P&L, win rate, fees, best/worst trade, per-symbol chips, ledger table, ledger CSV; the add-stock form now writes the ledger too.
- `PythonRunner` passes `VNSTOCK_API_KEY` explicitly (see docs/PYTHON_INTEGRATION.md — the Guest tier is 20 requests/min).

### Bugs found and fixed:
1. **Portfolio upcoming events were always empty in production** (since the portfolio rework): Laravel does not resolve `?CompanyProfileService $x = null` constructor parameters, so the service was `null`. `PortfolioService` is now bound with an explicit closure (regression test added).
2. `index.blade.php` declared `function parseRate()` inside the view → "Cannot redeclare" on a second render in one process; guarded with `function_exists`.
3. `ExampleTest` (GET `/`) would have run the real Python script on an empty snapshot table — tests must never reach the network; added the `BuildsMarketPayload` fixture trait and seeded it there.
4. `MarketSnapshot.trade_date` needed `date:Y-m-d` so the same-session upsert matches on SQLite as well as MySQL.
5. My first CSV export wrote `ï»¿` instead of the UTF-8 BOM (escape sequence mangled while generating the file) — caught by its test.

### Tests:
PHP 298 → 367 (`marketOverview`, `watchlist`, `portfolioLedger`, +2 `pythonRunner`), Node 10 → 20. `php artisan test` 367/367, `npm test` 20/20.

### Verified / not verified:
Live script against KBS (1,547 quotes, HOSE 22 nghìn tỷ, 4 indices, ~4 s), first `sync:market-overview` through PythonRunner, home/watchlist/portfolio pages rendered in the container and in the browser pane via inline static copies (dev data temporarily added and removed again), live poll with a stubbed endpoint, trade-modal maths against the service maths. Not verified: the real bootstrap modal (the pane blocks the CDN jQuery/Bootstrap), and a genuine in-session poll (the market was closed while building this — Sunday).

---

## GOLD_PRICE_PAGE - September 20, 2026

### Summary:
User asked for a gold-price feature (vnstock has it) and to put it in one menu with the exchange rate, as two submenus.

### Added:
- **Menu**: the single "Tỷ giá" nav item became a **Thị trường** dropdown → *Tỷ giá ngoại tệ* (`/exchange-rate`) and *Giá vàng* (`/gold`); active for both route families; footer link added.
- **Data** (`py/get_gold_price.py`, vnstock 4.0.4): SJC bars + Bảo Tín Minh Châu gold/silver + world gold (USD/oz), fetched in parallel (~4s). vnstock has **no free history API**, so `sync:gold-prices` (every 15 min, 07:00–19:00 VN, `withoutOverlapping`) records quotes into `gold_prices` and the chart fills in over time (the page says so honestly).
- **Data-quality findings**: SJC's by-date endpoint returned different prices for the same call (47.9M / 72M / 144.6M) → only the current quote is used and it is cross-checked against BTMC's own SJC line (>4% apart = dropped). BTMC is quoted per chỉ (×10 to lượng), in Vietnam time (→ UTC).
- **Page** (`/gold`): SJC + BTMC ring-gold + world (USD/oz and VND/lượng at the VCB USD sell rate) + domestic premium cards, day change vs the previous day's last quote, step-line chart (buy/sell, 24h/7d/30d/all, any product), lượng/chỉ toggle for every price, value / P&L calculator, silver table, refresh button (120s cooldown), stale-while-revalidate (one deduplicated `SyncGoldPricesJob` for a page older than 20 min), first-ever visit loads live once via `SingleFlight`. Error state when the source is down.
- Admin > Sync Status lists the source (`sync:gold-prices`, manual trigger whitelisted); `ExchangeRateRepository::getLatestRate()` added for the USD rate.

### Bug caught by the new tests:
`GoldPriceRepository::saveQuotes()` looked quotes up with the script's ISO string (`2026-09-20T01:30:00Z`), which never equals the stored DATETIME text, so a **second** sync tried to insert the same row and hit the unique index. Timestamps are now normalised before the lookup. Confirmed on MySQL: run 1 stored 9 new quotes, run 2 stored 0.

### Tests:
+27 (`goldPrice`, incl. one sync-status test). `php artisan test` 298/298, `npm test` 10/10.

### Verified / not verified:
Live script (12 SJC + 98 BTMC rows, world 4378 USD/oz), real MySQL sync twice, page rendered in the container (200) and in the browser pane via an inline static copy (KPI cards, chart, tables, unit toggle, calculator all working; chart data in that copy was synthetic). The browser sandbox blocks the app's own assets, so the real page was not clicked through end to end. The chart history is thin until the scheduler has run for a while.

---

## PORTFOLIO_REWORK - September 20, 2026

### Summary:
User reported the portfolio (frontend + admin) had ugly buttons, buttons that "sometimes work", and little value to users (low usage in production). Audit found real bugs, not just looks.

### Root causes found:
1. **Wrong money unit (my own earlier bug).** `sync:portfolio-prices` copied the feed's price (thousands of VND, ACB = `22.05`) into VND columns, so an 85,000 ₫ buy showed as `22 ₫` / `-99.97%`, and target/stop-loss alerts (compared against that number) fired falsely. Fixed at the source with `App\Support\PriceUnit`; the price refresh now goes through it.
2. **Dead buttons.** `show.js` is an ES module but the view used inline `onclick="updatePrices()"` / `editItem()` → never ran. "Edit" also used `prompt()` and posted to a URL that does not exist (`/item/{id}/update`; the route is `PUT /item/{id}`). "Refresh" silently did nothing when a symbol had no price rows and still said "success".
3. **Delete redirect bug**: `removeStock()` looked the item up with `getPortfolioById($itemId)` (an item id used as a portfolio id).
4. **Cannot deactivate a portfolio**: unchecked checkbox is not submitted, so `is_active` never became false.
5. Portfolio total recomputed per item from a stale cached `items` collection during a price update.
6. Add-stock "auto name" was a hard-coded 8-company array; name and symbol were free text (typos → holdings that can never be priced).
7. **Admin**: the list/detail read attributes that do not exist (`total_value`, `profit_loss`, `items_count`…) so everything showed 0; header cards were hard-coded ("75%", "+12%", "+8.5%"); search `OR` swallowed the status filter; the detail page has always 500'd (nested quotes in `@section('page_title', '… {{ … 'N/A' }}')`) and the stats page had no view.

### Changed / added:
- **Prices**: `StockRepository::getLatestQuotes()` (latest + previous close), `getCloseHistory()`, `ensureTracked()`; `PortfolioService::refreshPrices()` returns a report; the show/index pages refresh from synced quotes on every visit (no button needed); symbols with no data get a deduplicated background sync; new `portfolio_items.previous_price` / `price_date` → today's move + "as of" date per holding; adding a holding values it at the market price immediately; a repeat buy keeps the market price and averages the cost.
- **UI**: new design system `css/portfolio/portfolio.css` (one primary action per page, soft/ghost/danger variants, loading state, modals instead of `confirm()`/`prompt()`), rewritten index/show/create/edit/add-stock pages + chooser.
- **Features to make it worth using**: performance chart (value vs. capital, rebuilt from buy dates + daily closes — available on day one), allocation donut, today's P&L + best/worst, upcoming dividends / shareholder meetings for held symbols (from cached company profiles), target/stop-loss levels with distance in %, inline concentration advice, CSV export, add-stock with autocomplete + auto-filled name/price + live totals + one-click target/stop presets, "Thêm vào danh mục" on the stock and company pages (`/portfolio/add?symbol=`), portfolio link visible to guests, real onboarding state.
- **Validation**: buy/target/stop prices are whole VND with min 100 (a "85" typed for 85,000 is rejected with an explanation); symbol must exist in `stock_symbols`.
- **Admin**: real header numbers, real columns, server-side search/status filter with pagination, working detail page, new stats page (top held symbols).

### Tests:
+34 (`portfolioPage` 17, `portfolioAdmin` 4, `portfolioPrices` +8). `php artisan test` 271/271, `npm test` 10/10.

### Verified:
Against the dev DB: the real portfolio now reads ACB 22,050 ₫ / VCB 58,200 ₫ (was 22 ₫ / 500 ₫), today's move and a 74-point performance series computed. Real browser (inline static copies of the rendered pages): KPI cards, performance chart, donut, table without horizontal scroll, overflow menu, edit/delete modals prefilled with the right action URL, add-stock live totals + presets. Not verified in the browser: the quote fetch on the add form and the refresh button's round trip (the browser sandbox blocks fetch) — both endpoints are covered by feature tests.

---

## ADMIN_SIDEBAR_AND_ACCOUNT_UI_FIXES - September 19, 2026

### Fixed (two UI bugs the user found in the admin layout, `layouts/admin.blade.php`):
1. **Sidebar sub-menus** (Hệ thống: Quản lý Users / Vai trò / Quyền hạn / Giám sát Queue; also Tính năng) rendered side by side and wrapped: the sub-menu container was `<div class="nav collapse">` and Tabler's `.nav` is a horizontal flex row. Now `.nav-sub` (vertical, indented, dot bullets, single active style). Also: the *Hệ thống* group only auto-expanded on `admin.users*` pages (now on roles / permissions / queue too) and was hidden unless the user had `manage-users` (now `@canany` of its four permissions; each link keeps its own `@can`) — a role holding only `manage-queue` previously had no visible way to the queue page.
2. **"Quản trị viên" badge on top of the avatar**: Tabler styles `.navbar-nav .nav-link .badge` as `position:absolute` (a notification dot for nav icons), so the role badge left the flow and landed over the avatar/name. The account block is now `.account-toggle` (flex, gap) with name over role badges and the badge forced back to `position:static`.
Also `mb_substr` for the avatar initial (was `substr`, wrong for a name starting with a multi-byte letter).

### Verified:
Layout geometry measured in a real browser at 1500px (static render of the layout as the admin user): sub-menu links stacked vertically at the same left edge; avatar right edge 1384 < badge left 1393, badge top 28 > name bottom 26, all inside the 56px header. `queueMonitor` + `permissions` groups green.

---

## SEARCH_AUTOCOMPLETE_REDESIGN - September 19, 2026

### Summary:
User found the symbol-search dropdown ugly: the highlighted row was a heavy colour and the hovered row looked identical to the keyboard-selected one, so two rows appeared "selected" at once.

### Root causes:
- The CDN `awesomplete.css` (neon `mark`, dark selected row) was loaded together with **three separate copies** of override CSS (`index.css`, `stock.css`, `layouts/app.css` with `!important`) that gave `:hover` and `[aria-selected]` the *same* background — and the mouse hover never moved the keyboard selection, so both could be lit at once.
- Awesomplete's default row renderer wraps matches in `<mark>` on the raw label HTML; labels were HTML strings (`<b>SYM</b><span>name</span>`), so typing `b`/`span` could corrupt the markup and company names were injected unescaped.
- Results were unranked (`LIKE %q%`, limit 20, no ORDER BY) — typing `FPT` did not guarantee FPT was first.

### Changed:
- New `resources/frontend/js/shared/autocomplete.js` (`stockAutocomplete`) used by home, stock and compare pages: DOM-built rows (ticker chip + company name + exchange pill), soft-blue match tint, **single highlight** (mouse hover moves the selection, without Awesomplete's scroll-jumping `goto()`), debounced + `AbortController`-cancelled fetch (a slow old response can't overwrite a newer one), clear (×) button, loading spinner, pick-to-search on the stock page (as on home).
- New `css/shared/autocomplete.css` — the only stylesheet for the dropdown and the search inputs (brand palette, focus ring, icon turns blue on focus, reduced-motion, mobile). The three duplicate blocks and the CDN CSS/JS were removed; `awesomplete` is now an npm dependency bundled by Vite.
- `StockRepository::searchSymbols()`: ranked exact → prefix → contains → name-only, returns only `symbol,name,exchange`, LIKE wildcards escaped.
- Data fix: 182 of 1777 `stock_symbols.name` values were stored as CP437 mojibake (`C├┤ng ty Cß╗ò phß║ºn`) by an old Windows-console sync and were visible as gibberish rows; these are mostly delisted symbols so re-syncing could not repair them. New `App\Support\Mojibake` + idempotent migration `2026_09_19_000003` repaired all 182 (verified 182 → 0).

### Tests:
Group `stockSearch` (+6). `php artisan test` all green; `npm test` unchanged.

### Verified:
Real browser (static copy of the built home/stock pages with a stubbed `/stocks-list`): dropdown renders, exactly one `aria-selected` row at any time while moving the mouse across rows, no scroll jump, clear button + focus ring. Keyboard navigation relies on Awesomplete's own handling and was not exercised in the browser tool.

---

## COMPANY_PROFILE_FUND_CATALOG_LIGHTWEIGHT_CHARTS - September 19, 2026

### Summary:
User asked for two features suggested from free vnstock APIs — a company profile page (#1) and an open-ended fund catalog (#3) — then asked to replace the "ugly" ApexCharts with TradingView Lightweight Charts, downloaded locally for speed, using whatever of its features made sense.

### Added — Company profile (`/company/{symbol}`, group `companyProfile`):
- `py/get_company_profile.py`: 9 vnstock `Company` calls (KBS + VCI) concurrently (~4s); every section isolated (`errors`), KBS primary, VCI adds valuation/events/long shareholder list, officers merged by normalised name to attach holdings; unknown ticker detection (vnstock returns an all-zero row, not an error) and a `transient` flag so a data-source outage is never cached as "no such company".
- `company_profiles` table/model (`STALE_DAYS=3`), `CompanyProfileRepository`/`Interface`, `CompanyProfileService` (stale-while-revalidate with one deduplicated `SyncCompanyProfileJob`, `load()` first-visit/forced refresh with 5-min cooldown, 15-min negative cache, `present()`), `CompanyProfileController` (`show`, POST `load`), `sync:company-profiles` (`--symbol/--seed/--limit/--dispatch`, weekly), view + JS (loader shell, tabs, donuts), link from the stock page.
- `App\Support\SingleFlight`: N simultaneous cache misses run one Python process.

### Added — Fund catalog (`/funds`, `/funds/{code}`, `/funds/compare`, group `fundCatalog`):
- `py/get_fund_list.py` (one Fmarket call = all 68 funds), `py/get_fund_detail.py` (NAV history thinned to daily-3y + weekly, allocation, industries, top holdings; 4 concurrent calls).
- `funds` table/model (`type_code` derived from the Vietnamese label), `FundRepository` (NULL returns always last, whitelisted sort, escaped LIKE), `FundService` (catalog, `syncAll()`, `detail()` cached 6h behind `SingleFlight`, compare helpers), `FundController`, `sync:funds` (daily 18:30), navbar "Quỹ mở".
- `App\Support\FundMetrics`: window return, max drawdown, annualised volatility (null for ALL — history is weekly before the daily window).
- Both sync commands added to Admin > Sync Status (whitelist + rows + 2 icons).

### Changed — charts (all pages) ApexCharts → Lightweight Charts 5 (npm, bundled by Vite):
- `shared/charts.js` (house theme, legend, helpers), `shared/indicators.js` (pure maths), `shared/svgcharts.js` (donut + diverging bars — Lightweight Charts is time-series only), `css/shared/charts.css`.
- Stock page: candles/area toggle in one chart, volume histogram, MA20/50/200 + Bollinger overlays, RSI and MACD as **panes of the same chart** (shared time axis/crosshair, overbought/oversold price lines), OHLC+change legend, period buttons zoom the time axis (all history stays scrollable), PNG screenshot button. Stock compare: percent lines re-based to a **common start date** (raw `percent` is per-stock-first-day, so stocks listed on different dates were not comparable). Fund NAV: baseline series (green above / red below the start-of-period NAV). Exchange-rate bars: SVG.
- Fixed while touching them: compare page inline `onclick` handlers were not on `window` (ES module) → exposed; garbled UTF-8 (double-encoded) in stock.js strings/comments.

### Fixed — real bug found while testing web-triggered Python:
PHP-FPM runs as `www-data` (home `/var/www`, root-owned) so vnstock's `~/.vnstock` write failed: **every Python call made from a web request returned an error/empty result** (e.g. `get_exchange_rate.py` → `[]`), silently masked by DB fallbacks; only root queue workers/scheduler worked. `PythonRunner` now sets `HOME` to a temp dir when the effective user's home is not writable.
Also fixed `VnFormat::date()` returning *today's date* for unparseable input (Carbon fallback) — caught by its own test.

### Tests:
+85 PHPUnit tests (`companyProfile`, `fundCatalog`, `pythonRunner` HOME override) and `npm test` (10 Node tests: indicators + chart helpers). `php artisan test`: 236/236. Lessons recorded in docs/TESTING.md (router caches the controller per Route; `@json([..])` comma bug).

### Verified:
Live against real vnstock/Fmarket in Docker: company profile FPT/VCB/HPG (unknown ticker + ETF → not-found, negative cache hit in 0.1s, forced-refresh 429), 68 funds synced, fund detail via web request (3s cold, 0.1s cached). Charts rendered in a real browser from inline static copies of the built pages (the in-app browser sandbox blocks asset requests): stock page with all indicators + panes, fund NAV baseline + donut + bars, fund compare, stock compare, company donuts. Not covered by automation: hover/click interaction of the charts.

---

## QUEUE_DELETE_ALL_FAILED_JOBS - September 19, 2026

### Summary:
Admin > Giám sát Queue's "Job thất bại" card only had "Retry tất cả"; user asked for a matching "Xoá tất cả" button.

### Added:
- `QueueMonitorRepository::deleteAllFailedJobs()` (counts `failed_jobs`, then `Artisan::call('queue:flush')` — same "reuse Laravel's own command" approach as retry-all/forget), passed through `QueueMonitorService` and both interfaces.
- `QueueMonitorController::destroyAll()` → `DELETE /admin/queue/failed` (`admin.queue.destroy-all`, gate `manage-queue`, same group as the other queue routes). Logs an `admin_action` entry via `ActivityLogger` ("Xoá toàn bộ job thất bại: N job") since it's irreversible.
- View: red "Xoá tất cả" button next to "Retry tất cả", guarded by a `confirm()` that says the deletion is permanent, then reloads the page on success.
- Tests (group `queueMonitor`, +2): deletes every row and reports the exact count; forbidden without `manage-queue` (rows untouched).

### Verified:
`php artisan test`: 151/151 passing. Route registered (`route:list`). Deliberately did **not** click the button against the live dev DB — it holds ~588 real historical failed-job rows and this action is irreversible; covered by the automated test against an isolated DB instead.

---

## Earlier work — one-line digest

Full text: [2026-09](history/2026-09.md) · [2026-06](history/2026-06.md) · [2026-05 and earlier](history/2026-05-and-earlier.md).

### September 2026 — [archive](history/2026-09.md)

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

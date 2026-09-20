# Python Integration Guide

## Overview

Python scripts in `py/` are called from Laravel Service classes via **`App\Support\PythonRunner`** — never call `exec()` directly, see "Calling Pattern" below for why. All scripts output **pure JSON** to stdout.

## Python Config

The Python executable path is configurable via `config/services.php`:

```php
// config/services.php
'python' => [
    'path' => env('PYTHON_PATH', 'python'),
],
```

Set in `.env` if your Python binary has a different name or path:
```env
PYTHON_PATH=python3
# or on Windows:
PYTHON_PATH=C:/Python311/python.exe
```

## Calling Pattern — `App\Support\PythonRunner` (MANDATORY, never call `exec()` directly)

```php
use App\Support\PythonRunner;

// Manual JSON-scan (when you need $exit_code/$timed_out, or custom parsing —
// e.g. ExchangeRateService::parsePythonOutput() does its own multi-line parsing):
$result = PythonRunner::run(base_path('py/get_stock.py'), [$argument], 280);
// $result = ['output' => string[], 'exit_code' => int, 'timed_out' => bool]

// Or, if you just want the decoded JSON (most callers):
$decoded = PythonRunner::runAndDecodeJson(base_path('py/get_stock.py'), [$argument], 280);
// null if no JSON-looking line was found in the output
```

Every argument in the array is shell-escaped individually — never build the command string yourself or interpolate arguments into a raw string.

### Why `PythonRunner` exists instead of plain `exec()`

**A real bug, found and fixed 2026-09-16 (see `docs/HISTORY.md`, `PYTHON_EXEC_TIMEOUT_FIX`):** a `ProcessStockPriceSync` job — which declares `public $timeout = 300` — was still shown as "processing" **9+ minutes** in, because the external VCI API (`trading.vietcap.com.vn`) was hanging on every request. Laravel's job/worker timeout relies on `pcntl_alarm()` delivering a `SIGALRM` that the PHP process must be free to handle — but while PHP is blocked inside `exec()`'s own blocking read, waiting on the Python subprocess, it cannot service that signal. The configured timeout was never actually enforced; the worker stayed stuck until the Python subprocess itself eventually returned (which, against a hung API, could be never).

`PythonRunner::run()` wraps every command with the Unix `timeout` utility (GNU coreutils — present in the Docker image, `php:8.2-fpm` is Debian-based; **not** available on Windows, so this class silently falls back to a plain unwrapped `exec()` on Windows — same as before this fix, not worse, for XAMPP/manual dev setups). The OS itself kills the Python child if it runs past `$timeoutSeconds`, which makes `exec()`'s pipe hit EOF and return immediately — no dependency on PHP signal handling at all. Confirmed live: the same stuck jobs, after this fix, correctly terminated at ~286s (their 280s ceiling + a few seconds of `timeout`/PHP overhead) instead of hanging indefinitely.

**Picking `$timeoutSeconds` for a new call site:** it must stay comfortably below whatever timeout governs the *calling* context (a job's own `$timeout`, the worker's `--timeout`, or — for a web request — reasonable UX), so that when it fires, there's still time to log/return cleanly. If one PHP method can invoke Python multiple times in a loop (e.g. `CompanyFinancialService::fetchFromPython()`, called up to 8× per `SyncCompanyFinancialJob` run), size each individual call well below `(job timeout) / (max calls)` — see that method's own comment for the reasoning. Bounding each individual call also has a secondary benefit: PHP only gets a chance to act on a pending job-timeout signal once `exec()` returns control to it, so keeping calls short is what lets Laravel's own job `$timeout` have a chance to fire at all between calls, on top of PythonRunner's own hard ceiling.

### JSON scanning

```php
// PythonRunner::runAndDecodeJson() does this for you; shown here for when you
// need the raw $result['output'] instead (see manual example above).
$jsonStr = '';
for ($i = count($result['output']) - 1; $i >= 0; $i--) {
    if (str_starts_with(trim($result['output'][$i]), '{') || str_starts_with(trim($result['output'][$i]), '[')) {
        $jsonStr = trim($result['output'][$i]);
        break;
    }
}
```

> **Why scan from the end?** Python's `vnstock` library may print warnings to stdout. The actual JSON is always the last non-empty JSON-like line.

## Python Scripts Reference

### `py/get_stock.py`
- **Purpose**: Daily OHLCV history for one or more symbols — the stock page's first load, its background refresh, the nightly bulk sync and the backfill all use it
- **Args**: `SYM[,SYM...] [start YYYY-MM-DD] [end YYYY-MM-DD]` (start defaults to one year ago, end to today)
- **Output**: `{"data": {"FPT": [{"time": <epoch ms>, "date": "2026-09-18", "open", "high", "low", "close", "volume"}, ...]}, "errors": {...}, "sources": {"FPT": "KBS-direct"}}`, oldest bar first, **prices in thousands of VND** (the feed unit the database stores; indices in points)
- **Sources, tried per symbol until one answers**: (1) **KBS-direct** — a plain HTTPS GET of KBS's public history endpoint with the standard library, ~0.25 s, and it does **not import vnstock** (that import alone is a fixed ~1.7 s on every script start); (2) vnstock `KBS`; (3) vnstock `VCI`. (2) and (3) are imported lazily, under a lock (importing vnstock from several threads deadlocks), and only when (1) *failed*: an empty answer from KBS-direct is authoritative, otherwise a quiet incremental window would pay for the slow sources. `STOCK_SOURCES` (comma list) changes the order; `STOCK_DIRECT_TIMEOUT` (default 12 s) bounds one direct request; `KBS_BASE_URL` points the script at a fake in tests.
- **Why**: VCI — the only source before — failed from the container (`ConnectionError` after ~99 s of retries; ~10 s when merely slow). KBS-direct answers a whole year for one symbol in ~1 s including Python start-up (measured 0.94 s in the container vs 4.2 s through vnstock's KBS source vs 99 s failing VCI). KBS and VCI prices are identical on the same day; only very old bars differ by a dividend-adjustment factor (VNM 2025-09-22: 56.79 vs 56.88).
- **Incremental**: pass `start` to fetch only the missing sessions (`StockPriceFreshness` asks for `latest stored − 5 days`).
- **Concurrency**: up to `MAX_CONCURRENT=4` symbols in parallel (`concurrent.futures.ThreadPoolExecutor` — blocking HTTP calls, so threads are the right tool).
- **Rate limit**: 0.3 s pause per symbol in a *bulk* run; a single-symbol call (a web request) does not sleep.

### `py/get_exchange_rate.py`
- **Purpose**: Fetch VCB exchange rates by date or last N days
- **Args**:
  - Date string: `2026-05-28` → returns rates for that day as JSON array
  - Number string: `7` → returns rates for last 7 days as array of `{date, rates}` objects
- **Concurrency**: the "last N days" path fetches days in parallel the same way (`MAX_CONCURRENT=4`)
- **Output**: JSON array

### `py/get_hot_industries.py`
- **Purpose**: Return stocks from hot industries (Ngân hàng, Bất động sản, CNTT)
- **Args**: Optional integer `limit` (default: 30)
- **Output**: JSON array of stock records

### `py/get_stock_list.py`
- **Purpose**: Fetch full list of all stock symbols from vnstock
- **Args**: None
- **Output**: JSON array of `{symbol, organ_name, ...}` objects
- **Note**: Includes ETFs (e.g., FUEVFVND)

### `py/get_company_finance.py`
- **Purpose**: Fetch company financial statements (income statement, balance sheet, cash flow, ratios) for one symbol
- **Args**: Symbol, statement type, period (quarter/year) — called from `CompanyFinancialService::fetchFromPython()`
- **Output**: JSON object of financial statement rows
- **Data source**: KBS via vnstock; cached in the `company_financials` table (`STALE_DAYS = 30`), refreshed by `sync:company-financials`

### `py/get_company_profile.py`
- **Purpose**: Full company profile for one symbol — overview, ownership structure, major shareholders, officers, subsidiaries/affiliates, corporate events
- **Args**: Symbol — called from `CompanyProfileService::runScript()` (page loader, `SyncCompanyProfileJob`, `sync:company-profiles`)
- **How**: nine vnstock `Company` calls (KBS: overview/ownership/shareholders/officers/subsidiaries; VCI: overview/shareholders/officers/events) run concurrently in a `ThreadPoolExecutor` (~4s total instead of ~10s sequential). Every section is isolated — one failing source/section becomes an entry in `errors`, never a failed page. KBS is primary (stable); VCI adds the valuation snapshot, events and the long shareholder list; officers are merged by normalised name (honorifics stripped) so KBS positions get VCI ownership %.
- **Output**: one JSON object (see the script's docstring). Unknown ticker → `{"error", "transient": false}`; every section failing with no "invalid symbol" message → `"transient": true` (data source down — callers must **not** cache that as "no such company").
- **Gotchas**: vnstock returns an all-empty row (not an error) for unknown tickers — detected and turned into `error`; KBS `charter_capital` arrives in billions in some payloads and VND in others (normalised); VCI percentages are fractions (×100 applied), KBS ones already percent.
- **Cache**: `company_profiles` table (`STALE_DAYS = 3`, stale-while-revalidate).

### `py/get_fund_list.py`
- **Purpose**: Every open-ended fund on Fmarket in one call (~0.5s) — NAV, fee, returns over 1m…inception
- **Args**: None — `FundService::runListScript()` (`sync:funds`, first catalog visit)
- **Output**: `{"funds": [...]}` or `{"error"}`

### `py/get_fund_detail.py`
- **Purpose**: One fund's NAV history + asset/industry allocation + top holdings (4 concurrent Fmarket calls)
- **Args**: Fund short name (regex-validated) — `FundService::runDetailScript()` via `GET /funds/{code}/detail`
- **Output**: NAV thinned to daily for the last 3 years and weekly before that (~1.7k points instead of ~4k); cached 6h in `Cache` behind `SingleFlight`

### `py/get_market_overview.py`
- **Purpose**: The whole market in one go for the home page / ticker / watchlist — four indices with a 30-session series, breadth and liquidity per exchange, top gainers / losers / most-traded (overall and per exchange) and a quote for every symbol
- **Args**: None — `MarketOverviewService::runScript()` (page first-visit loader, `SyncMarketOverviewJob`, `sync:market-overview`)
- **Source**: **KBS** through vnstock. `Listing.symbols_by_exchange()` (~1 s, gives exchange + type) → `Trading.price_board()` for all ~1,500 stocks/ETFs in ONE request (~1 s: price, reference, % change, volume, traded value) → `Quote(<index>).history()` for VNINDEX / VN30 / HNXINDEX / UPCOMINDEX. Run concurrently, ~4 s in total. The VCI source, in contrast, timed out at 30 s from the container and answered its listing in 30 s.
- **Output**: `{fetched_at, trade_date, indices[], exchanges{HOSE,HNX,UPCOM}, movers{ALL,HOSE,HNX,UPCOM}{gainers,losers,value}, quotes{SYMBOL:[price,ref,%,vol,value,ceiling,floor]}, errors, warnings}`; whole VND. `{"error"}` only when nothing answered; a failed board with working indices is reported in `errors.board` and the service refuses to store the half-empty payload.
- **Gotchas**: bonds and covered warrants (`type` bond/cw) make the board endpoint reject the whole request (`Invalid derivative or bond symbol`) and are filtered out; vnstock modules must be imported **before** the thread pool (lazy imports from several threads deadlock on the module lock); stocks that did not trade are not counted as "unchanged" (UPCoM's ~900 idle tickers would swamp the breadth bar); gainers/losers need ≥ 5 billion VND traded (one-lot trades show +38%); `trade_date` is the latest index bar, so a weekend run stores Friday's session under Friday's date.
- **Cache**: `market_snapshots` (one row per session, overwritten as the day goes on; `quotes` kept on the newest row only). Stale after 5 min during the session (Mon–Fri 09:00–15:00 VN), 6 h outside it.

### `py/get_gold_price.py`
- **Purpose**: Current gold/silver prices — SJC bars, Bảo Tín Minh Châu (BTMC) products and the world gold price (USD/oz) — for the `/gold` page
- **Args**: None — `GoldPriceService::runScript()` (page first-visit loader, manual refresh, `SyncGoldPricesJob`, `sync:gold-prices`)
- **How**: `vnstock.explorer.misc.gold_price.sjc_gold_price()` and `btmc_goldprice()` run concurrently (~4s). BTMC prices are per chỉ in the source and multiplied ×10 to VND/lượng; its timestamps are Vietnam time and converted to UTC; "ĐỒNG XU" (coins) are skipped; silver keeps the pack unit written in the product name.
- **Output**: `{fetched_at, sjc:[{product,branch,buy,sell}], btmc:[{metal,product,purity,buy,sell,unit,quoted_at}], world_usd_oz, errors, warnings}`. `{"error"}` only when no source answered at all; one source failing degrades to a `warnings` entry.
- **Gotchas**: the SJC by-date endpoint is unreliable (the same call returned 47.9M / 72M / 144.6M), so only the *current* SJC quote is used and it is dropped when its sell price is more than 4% away from BTMC's own "SJC" line (`SJC_CROSSCHECK_TOLERANCE`). There is no free history API (only the paid `vnstock_data`), so history is accumulated in `gold_prices` by the 15-minute scheduler.
- **Cache**: `gold_prices` table (one row per source/product/branch/quote time); stale after 20 min → one deduplicated `SyncGoldPricesJob`.

### `py/register_api_key.py`
- **Purpose**: Register or configure vnstock API key for sponsored tier access
- **Args**: None (interactive or reads from env)
- **Output**: Status message or error JSON

## Every `PythonRunner` call site and its `$timeoutSeconds`

| Caller | Script | Context | Ceiling |
|---|---|---|---|
| `StockService::fetchStockDataFromPython()` | `get_stock.py` | `ProcessStockPriceSync` job (`$timeout=300`) | 280s |
| `StockService::refreshPrices()` → `fetchStockDataFromPython($symbols, $timeout, $from)` | `get_stock.py` | **Web request**: stock page first-ever view of a symbol / compare page (`StockPriceFreshness`, ceiling `StockPriceFreshness::SYNC_TIMEOUT` = 25 s, normally ~1 s) + `RefreshStockPricesJob` (`$timeout=90`, incremental, 60 s) | 25s / 60s |
| `StockService::fetchStockListFromPython()` | `get_stock_list.py` | `sync:stock-data` command | 120s |
| `StockService::fetchHotIndustriesFromPython()` | `get_hot_industries.py` | `sync:hot-industries` command | 60s |
| `CompanyFinancialService::fetchFromPython()` | `get_company_finance.py` | `SyncCompanyFinancialJob` (`$timeout=180`), up to 8 calls/run | 35s |
| `ExchangeRateService::fetchRatesFromPython()` | `get_exchange_rate.py` | Web request (`ExchangeRateController`) + `sync:exchange-rates` | 30s |
| `BackfillStockPriceChunk::handle()` | `get_stock.py` | Queued job (`$timeout=600`) | 550s |
| `BackfillStockPrices` (inline mode) | `get_stock.py` | Manual terminal run (`--dispatch` not passed) | 550s |
| `CompanyProfileService::runScript()` | `get_company_profile.py` | Web request (first-visit loader / forced refresh) + `SyncCompanyProfileJob` (`$timeout=90`) + `sync:company-profiles` | 60s |
| `FundService::runListScript()` | `get_fund_list.py` | `sync:funds` + first catalog visit | 60s |
| `FundService::runDetailScript()` | `get_fund_detail.py` | Web request (`GET /funds/{code}/detail`) | 60s |
| `GoldPriceService::runScript()` | `get_gold_price.py` | Web request (first visit / "Làm mới") + `SyncGoldPricesJob` (`$timeout=90`) + `sync:gold-prices` (every 15 min, 07:00–19:00 VN) | 60s |
| `MarketOverviewService::runScript()` | `get_market_overview.py` | Web request (first visit only) + `SyncMarketOverviewJob` (`$timeout=90`) + `sync:market-overview` (every 5 min in session, 18:00) | 60s |

Changing a job's own `$timeout`/a command's expected runtime? Update the matching row here and the matching `PythonRunner::run()` call together — they're meant to move as a pair.

## Web-request Python calls & the `HOME` override

Python launched from a **PHP-FPM request** runs as `www-data`, whose home (`/var/www`) is root-owned in the image. vnstock writes `~/.vnstock` at import time, so every web-triggered call died with `[Errno 13] Permission denied: '/var/www/.vnstock'` — scripts returned an error/empty result and callers silently fell back to whatever was cached (e.g. `get_exchange_rate.py` returned `[]` from a web request, while the same script worked from the queue worker/scheduler, which run as root). `PythonRunner::run()` now points `HOME` at `sys_get_temp_dir()/python-home` **only when the effective user's home is not writable**; root workers and Windows dev setups are unaffected. Covered by `PythonRunnerTest` (group `pythonRunner`).

Caching pattern for the new pages: DB-first (`company_profiles`, `funds`) or `Cache` (fund detail), with `App\Support\SingleFlight` so N simultaneous misses run **one** Python process, and a negative cache only for a definitive "does not exist" answer.

## vnai must not edit AGENTS.md

The `vnai` package (a vnstock dependency) rewrites `AGENTS.md` in the working directory — and `~/.claude/CLAUDE.md`, `~/.codex/AGENTS.md`, `~/.gemini/GEMINI.md` — with a "Dynamic Skill Router" prompt for AI assistants whenever Python runs. It contains no secrets, but it is third-party instructions injected into a file AI tools trust, including a step that sends an API key to an external URL. `PythonRunner` therefore exports `VNSTOCK_DISABLE_AGENT_SETUP=1 VNSTOCK_AGENT_TARGETS=none` for every script, and `docker-compose.yml` sets `VNSTOCK_DISABLE_AGENT_SETUP=1` on php/queue/scheduler. If `git diff AGENTS.md` ever shows a `vnai-bootstrap` block again, something is running vnstock outside the runner.

## Sync speed — two levers, tuned together

User feedback (2026-09-16): syncing felt slow, whether triggered manually or via the scheduler. Two changes, meant to be tuned as a pair:

1. **`docker/php/supervisord.conf`**: `numprocs` for `queue-worker-redis`, 3 → 6. These jobs are I/O-bound (waiting on VCI/KBS APIs, not CPU), so more workers process more chunks concurrently for close to free.
2. **`py/get_stock.py` / `py/get_exchange_rate.py`**: internal `MAX_CONCURRENT` (currently 4) — was a plain sequential `for` loop over symbols/days, now `concurrent.futures.ThreadPoolExecutor`. A 20-symbol chunk that used to run 1 request at a time now runs 4 at a time.

**Combined effect**: worst-case concurrent requests to VCI went from 3 (1 worker × 1 sequential request) to 24 (6 workers × 4 threads) — an 8x ceiling, not "as many as possible". Going higher risks VCI rate-limiting/blocking harder, which would make syncing *slower*, not faster — there's no way to know the actual safe ceiling without VCI publishing one, so this was a deliberate, conservative first step rather than a guess at the true max. If it's still not fast enough, raise both numbers together and watch **Admin > Giám sát Queue** while doing it — pending-count trend and the failed-jobs list will show whether you're still winning or starting to get rate-limited.

**Next lever if 1+2 aren't enough** (not implemented, real infra work): every `exec()` call spawns a *fresh* Python process, paying `vnstock`/`pandas` import cost every single time (visible in worker logs as the "Vnstock X.X is available" banner on every invocation). A long-lived Python microservice (FastAPI/Flask) that PHP calls over HTTP instead of `exec()` would eliminate that repeated startup cost entirely and let Python-side concurrency be async-native instead of thread-based — the highest-ceiling option, but a genuinely new piece of infrastructure (new container, health checks, a redesigned PHP↔Python contract), not a config tweak.

## Rules for Python Scripts

1. **Only JSON to stdout** — no `print("debug message")`, no logging to stdout
2. Use `sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')` on Windows to fix encoding
3. Suppress vnstock logs: `logging.getLogger().setLevel(logging.WARNING)`
4. Scripts must handle missing/bad arguments gracefully and output `{"error": "..."}` on failure
5. Exit with code `0` on success

## Adding a New Python Script

1. Create `py/my_script.py` following the template above
2. Add a method to the relevant Service class (e.g., `StockService`) that calls `App\Support\PythonRunner::run()` or `runAndDecodeJson()` — **never** call `exec()` directly, see "Calling Pattern" above
3. Pick a `$timeoutSeconds` below whatever timeout governs the caller (job `$timeout`, worker `--timeout`, or reasonable UX for a web request) — see the sizing guidance above
4. Arguments go in the `$args` array, one per element — `PythonRunner` shell-escapes each individually; never build the command string yourself
5. Document the new script in this file

## Exchange Rate Service Pattern

`ExchangeRateService::fetchRatesFromPython()` calls `py/get_exchange_rate.py` via `PythonRunner::run()` and stores results in the `exchange_rates` table via `ExchangeRateRepository`.

## vnstock API key and rate limits

vnstock's anonymous **Guest** tier allows only **20 requests per minute** (the library prints a banner and waits when it is hit); a registered key raises that. `vnai` reads the key from the **environment variable** `VNSTOCK_API_KEY`, not from `.env`. Artisan, queue workers and the scheduler already inherit it (Laravel exports `.env` into the process environment), and `PythonRunner` now also passes `config('services.vnstock.api_key')` explicitly (validated `[A-Za-z0-9_.-]{8,200}`, shell-escaped) so PHP-FPM pools with `clear_env` behave the same. When you run a script by hand inside the container, pass it yourself: `docker compose exec -e VNSTOCK_API_KEY=... php /opt/venv/bin/python3 py/<script>.py`. Budget requests accordingly: the market script costs 6 per run (listing + 4 indices + 1 board), which is why the board is fetched in a single request.

## The stock page never waits for a provider (`StockPriceFreshness`)

`GET /stock?symbol=X` used to run a one-year `get_stock.py` (VCI) inside the request whenever the newest stored bar was older than *today* — every symbol, every hour, ~10 s (~100 s while VCI failed) — and then **stored nothing**: `StockRepository::updateStockPriceFromPython()` read `$item['date']`, a field the script never produced (the queue job path converted `time` → `date`, the web path did not). Now:

| Situation | What the request does |
|---|---|
| never synced, known symbol (or index) | ONE synchronous fetch (KBS-direct, ~1 s, 25 s hard ceiling); a failure is remembered for 5 min so the next visitors are not made to wait for it again |
| history behind the last completed session (`TradingCalendar`) | renders immediately from the DB, queues ONE deduplicated `RefreshStockPricesJob` (10 min lock per symbol) that fetches only `latest − 5 days …` |
| up to date | nothing |
| unknown / malformed code | no Python, no `stocks` row (the page says "Không tìm thấy mã"; a malformed code is a 404) |

The newest session — possibly still running — never needs Python: `get_market_overview.py` puts each symbol's open/high/low/close/volume in the market snapshot, and `StockPriceFreshness::withLiveBar()` appends that candle when the stored history stops before the snapshot's session (a small badge on the page says "đang giao dịch" / "từ bảng giá"). The compare endpoint follows the same policy, fetching all history-less symbols in ONE run. Holidays are not known to `TradingCalendar`: on one the answer is a session too late, which costs one harmless deduplicated refresh.

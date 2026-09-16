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
- **Purpose**: Fetch historical daily price data for one or more stock symbols
- **Args**: Comma-separated symbol string (e.g., `VCB` or `VCB,ACB,FPT`)
- **Output**: `{ "data": { "VCB": [...], "ACB": [...] }, "errors": { ... } }`
- **Data source**: vnstock VCI, last 365 days
- **Concurrency**: fetches up to `MAX_CONCURRENT=4` symbols in parallel (`concurrent.futures.ThreadPoolExecutor` — these are blocking `requests` calls, not async-native, so threads are the right tool). Was a plain sequential for-loop until 2026-09-16 — see "Sync speed" below.
- **Rate limit**: 0.3s delay per symbol (each thread paces its own requests; sleeping in one thread doesn't block the others)

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

### `py/register_api_key.py`
- **Purpose**: Register or configure vnstock API key for sponsored tier access
- **Args**: None (interactive or reads from env)
- **Output**: Status message or error JSON

## Every `PythonRunner` call site and its `$timeoutSeconds`

| Caller | Script | Context | Ceiling |
|---|---|---|---|
| `StockService::fetchStockDataFromPython()` | `get_stock.py` | `ProcessStockPriceSync` job (`$timeout=300`) | 280s |
| `StockService::fetchStockListFromPython()` | `get_stock_list.py` | `sync:stock-data` command | 120s |
| `StockService::fetchHotIndustriesFromPython()` | `get_hot_industries.py` | `sync:hot-industries` command | 60s |
| `CompanyFinancialService::fetchFromPython()` | `get_company_finance.py` | `SyncCompanyFinancialJob` (`$timeout=180`), up to 8 calls/run | 35s |
| `ExchangeRateService::fetchRatesFromPython()` | `get_exchange_rate.py` | Web request (`ExchangeRateController`) + `sync:exchange-rates` | 30s |
| `BackfillStockPriceChunk::handle()` | `get_stock.py` | Queued job (`$timeout=600`) | 550s |
| `BackfillStockPrices` (inline mode) | `get_stock.py` | Manual terminal run (`--dispatch` not passed) | 550s |

Changing a job's own `$timeout`/a command's expected runtime? Update the matching row here and the matching `PythonRunner::run()` call together — they're meant to move as a pair.

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

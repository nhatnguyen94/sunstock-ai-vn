# Python Integration Guide

## Overview

Python scripts in `py/` are called from Laravel Service classes using PHP's `exec()` function. All scripts output **pure JSON** to stdout.

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

## Calling Pattern (Used in `StockService`)

```php
$pythonPath = config('services.python.path', 'python');
$scriptPath = base_path('py/get_stock.py');
$command = escapeshellarg($pythonPath) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($argument);
exec($command, $output, $returnVar);

// JSON is always the LAST line starting with '{' or '['
$jsonStr = '';
for ($i = count($output) - 1; $i >= 0; $i--) {
    if (str_starts_with(trim($output[$i]), '{') || str_starts_with(trim($output[$i]), '[')) {
        $jsonStr = trim($output[$i]);
        break;
    }
}

$result = json_decode($jsonStr, true) ?? ['error' => 'Parse error'];
```

> **Why scan from the end?** Python's `vnstock` library may print warnings to stdout. The actual JSON is always the last non-empty JSON-like line.

## Python Scripts Reference

### `py/get_stock.py`
- **Purpose**: Fetch historical daily price data for one or more stock symbols
- **Args**: Comma-separated symbol string (e.g., `VCB` or `VCB,ACB,FPT`)
- **Output**: `{ "data": { "VCB": [...], "ACB": [...] }, "errors": { ... } }`
- **Data source**: vnstock VCI, last 365 days
- **Rate limit**: 1 second delay between symbols (to avoid VCI throttle)

### `py/get_exchange_rate.py`
- **Purpose**: Fetch VCB exchange rates by date or last N days
- **Args**:
  - Date string: `2026-05-28` → returns rates for that day as JSON array
  - Number string: `7` → returns rates for last 7 days as array of `{date, rates}` objects
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

### `py/register_api_key.py`
- **Purpose**: Register or configure vnstock API key for sponsored tier access
- **Args**: None (interactive or reads from env)
- **Output**: Status message or error JSON

## Rules for Python Scripts

1. **Only JSON to stdout** — no `print("debug message")`, no logging to stdout
2. Use `sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')` on Windows to fix encoding
3. Suppress vnstock logs: `logging.getLogger().setLevel(logging.WARNING)`
4. Scripts must handle missing/bad arguments gracefully and output `{"error": "..."}` on failure
5. Exit with code `0` on success

## Adding a New Python Script

1. Create `py/my_script.py` following the template above
2. Add a method to the relevant Service class (e.g., `StockService`)
3. Use the standard calling pattern (see above)
4. Validate inputs with `escapeshellarg()` **always** — never interpolate user data into shell commands
5. Document the new script in this file

## Exchange Rate Service Pattern

`ExchangeRateService` calls `py/get_exchange_rate.py` and stores results in the `exchange_rates` table via `ExchangeRateRepository`.

```php
// Typical flow in ExchangeRateService
$output = $this->callPython('get_exchange_rate.py', $date);
$rates = json_decode($output, true);
$this->exchangeRateRepository->upsertRates($date, $rates);
```

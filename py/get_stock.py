import sys
import io
import json
import logging
import time
import concurrent.futures
from datetime import datetime, timedelta

# Fix encoding issue on Windows
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

# Suppress all logs and banners below WARNING level
logging.getLogger().setLevel(logging.WARNING)

from vnstock.api.quote import Quote

# Check for symbol argument
if len(sys.argv) < 2:
    print(json.dumps({"error": "Stock symbol is required"}))
    sys.exit(1)

symbols_arg = sys.argv[1]
symbols = [s.strip().upper() for s in symbols_arg.split(',') if s.strip()]

# Define date range
# argv[2] = optional start date (YYYY-MM-DD). If omitted, default to last 1 year.
# argv[3] = optional end date (YYYY-MM-DD). If omitted, default to today.
end_date = datetime.today()
if len(sys.argv) >= 4:
    end = sys.argv[3]
else:
    end = end_date.strftime('%Y-%m-%d')

if len(sys.argv) >= 3:
    start = sys.argv[2]
else:
    start = (end_date - timedelta(days=365)).strftime('%Y-%m-%d')

# Concurrency within THIS process — was a plain sequential for-loop (1 symbol
# at a time), which is why a 20-symbol chunk took ~20x a single request's
# latency end to end. MAX_CONCURRENT=4 threads move this to genuinely
# parallel I/O-bound requests (Quote.history() is a blocking `requests` call
# under the hood, so threads — not asyncio — is the right tool here).
#
# Keep this modest: this app also runs several queue workers concurrently
# (docker/php/supervisord.conf), each spawning its own instance of this
# script — total concurrent requests to VCI = workers x MAX_CONCURRENT, not
# just this number. Pushing this too high risks harder rate-limiting/blocks
# from VCI, which would make things slower, not faster. See docs/PYTHON_INTEGRATION.md.
MAX_CONCURRENT = 4

results = {}
errors = {}


def fetch_one(symbol):
    try:
        q = Quote(symbol=symbol, source='VCI')
        df = q.history(start=start, end=end)

        data = [] if df is None or df.empty else json.loads(df.to_json(orient='records', force_ascii=False))

        # Per-request pacing — sleeping in one thread doesn't block the others,
        # so this still throttles this process's own request rate without
        # giving up the concurrency gained from running symbols in parallel.
        time.sleep(0.3)

        return symbol, data, None
    except Exception as e:
        time.sleep(0.3)
        return symbol, None, str(e)


with concurrent.futures.ThreadPoolExecutor(max_workers=MAX_CONCURRENT) as executor:
    for symbol, data, error in executor.map(fetch_one, symbols):
        if error is not None:
            errors[symbol] = error
        else:
            results[symbol] = data

output = {"data": results, "errors": errors}

# Output JSON data only (clean, single line)
print(json.dumps(output, ensure_ascii=False))

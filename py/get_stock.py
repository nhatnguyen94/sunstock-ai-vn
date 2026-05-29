import sys
import io
import json
import logging
import time
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

results = {}
errors = {}

for symbol in symbols:
    try:
        q = Quote(symbol=symbol, source='VCI')
        df = q.history(start=start, end=end)

        if df is None or df.empty:
            results[symbol] = []
        else:
            results[symbol] = json.loads(df.to_json(orient='records', force_ascii=False))

        # Rate limit: 0.3s delay per symbol (safe with multiple workers)
        time.sleep(0.3)

    except Exception as e:
        errors[symbol] = str(e)
        time.sleep(0.3)

output = {"data": results, "errors": errors}

# Output JSON data only (clean, single line)
print(json.dumps(output, ensure_ascii=False))

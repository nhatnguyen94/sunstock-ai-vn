import sys
import json
import logging
from datetime import datetime, timedelta
from vnstock import Vnstock

# Suppress all logs below WARNING level
logging.getLogger().setLevel(logging.WARNING)

# Check for symbol argument
if len(sys.argv) < 2:
    print(json.dumps({"error": "Stock symbol is required"}))
    sys.exit(1)

symbol = sys.argv[1].upper()

# Define date range (last 30 days)
end_date = datetime.today()
start_date = end_date - timedelta(days=30)
start = start_date.strftime('%Y-%m-%d')
end = end_date.strftime('%Y-%m-%d')

try:
    stock = Vnstock().stock(symbol=symbol, source='VCI')
    df = stock.quote.history(start=start, end=end)

    if df.empty:
        print(json.dumps({"error": "No data available"}))
        sys.exit(0)

    # Output JSON data only (clean, single line)
    print(df.to_json(orient='records', force_ascii=False))

except Exception as e:
    print(json.dumps({"error": str(e)}))
    sys.exit(1)

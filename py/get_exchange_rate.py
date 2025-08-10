import sys
import json
from datetime import datetime, timedelta

try:
    from vnstock.explorer.misc.exchange_rate import vcb_exchange_rate
except ImportError:
    print(json.dumps({"error": "vnstock not installed"}))
    sys.exit(1)

def get_dates(n):
    today = datetime.today()
    return [(today - timedelta(days=i)).strftime('%Y-%m-%d') for i in range(n)]

dates = get_dates(3)
result = []
for date in dates:
    try:
        df = vcb_exchange_rate(date=date)
        result.append(df.to_dict(orient='records'))
    except Exception as e:
        result.append({"error": str(e), "date": date})

print(json.dumps(result, ensure_ascii=False))
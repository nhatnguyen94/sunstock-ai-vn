import sys
import json
import concurrent.futures
from datetime import datetime, timedelta
from vnstock.explorer.misc.exchange_rate import vcb_exchange_rate

# Same reasoning as py/get_stock.py: was a sequential for-loop over `days`,
# now fetched concurrently. Kept modest for the same rate-limit reasons —
# see docs/PYTHON_INTEGRATION.md.
MAX_CONCURRENT = 4


def fetch_one_day(date):
    df = vcb_exchange_rate(date=date)
    if df.empty:
        return None
    return {"date": date, "rates": df.to_dict(orient='records')}


def get_by_days(days):
    today = datetime.today()
    dates = [(today - timedelta(days=i)).strftime('%Y-%m-%d') for i in range(days)]

    with concurrent.futures.ThreadPoolExecutor(max_workers=MAX_CONCURRENT) as executor:
        return [r for r in executor.map(fetch_one_day, dates) if r is not None]

if len(sys.argv) > 1:
    arg = sys.argv[1]
    if arg.isdigit():
        days = int(arg)
        data = get_by_days(days)
        print(json.dumps(data, ensure_ascii=False))
    else:
        date = arg
        df = vcb_exchange_rate(date=date)
        print(json.dumps(df.to_dict(orient='records'), ensure_ascii=False))
else:
    print(json.dumps([]))

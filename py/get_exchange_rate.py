import sys
import json
from datetime import datetime, timedelta
from vnstock.explorer.misc.exchange_rate import vcb_exchange_rate

def get_by_days(days):
    results = []
    today = datetime.today()
    for i in range(days):
        date = (today - timedelta(days=i)).strftime('%Y-%m-%d')
        df = vcb_exchange_rate(date=date)
        if not df.empty:
            results.append({
                "date": date,
                "rates": df.to_dict(orient='records')
            })
    return results

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
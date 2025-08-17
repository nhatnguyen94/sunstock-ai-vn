import sys
import json
from vnstock.explorer.misc.exchange_rate import vcb_exchange_rate

if len(sys.argv) > 1:
    date = sys.argv[1]
    df = vcb_exchange_rate(date=date)
    print(json.dumps(df.to_dict(orient='records'), ensure_ascii=False))
else:
    print(json.dumps([]))
import sys
import io
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

import json
from vnstock import Listing

listing = Listing()
try:
    df = listing.all_symbols()
    stocks = df.to_dict(orient='records')
    print(json.dumps(stocks, ensure_ascii=False))
except Exception as e:
    print(json.dumps({"error": str(e)}))
import sys
import io
import json
import logging
import pandas as pd

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
logging.getLogger().setLevel(logging.WARNING)

from vnstock import Listing

try:
    listing = Listing()

    df_stocks = listing.all_symbols(show=False)
    df_etfs = listing.all_etf(show=False).to_frame(name='symbol')

    df = pd.concat([df_stocks, df_etfs], ignore_index=True)
    # Output proper JSON, replacing NaN with null
    print(df.to_json(orient='records', force_ascii=False))
except Exception as e:
    print(json.dumps({"error": str(e)}))
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

    # Base: all symbols with organ_name (symbol, organ_name)
    df_stocks = listing.all_symbols(show=False)

    # Exchange info: fetch each exchange and tag (HOSE→HSX to match local convention)
    exchange_frames = []
    for exch in ['HOSE', 'HNX', 'UPCOM']:
        try:
            df_exch = listing.symbols_by_exchange(exchange=exch, to_df=True)
            df_exch = df_exch[['symbol']].copy()
            df_exch['exchange'] = 'HSX' if exch == 'HOSE' else exch
            exchange_frames.append(df_exch)
        except Exception:
            pass
    if exchange_frames:
        df_exchange = pd.concat(exchange_frames, ignore_index=True).drop_duplicates('symbol')
        df_stocks = df_stocks.merge(df_exchange, on='symbol', how='left')
    else:
        df_stocks['exchange'] = None

    # Industry info: symbol → industry_name mapping
    try:
        df_industries = listing.symbols_by_industries()
        df_industry_map = df_industries[['symbol', 'industry_name']].drop_duplicates('symbol')
        df_stocks = df_stocks.merge(df_industry_map, on='symbol', how='left')
    except Exception:
        df_stocks['industry_name'] = None

    # ETFs (no exchange/industry)
    try:
        df_etfs = listing.all_etf(show=False).to_frame(name='symbol')
        df_etfs['organ_name'] = None
        df_etfs['exchange'] = None
        df_etfs['industry_name'] = None
        df = pd.concat([df_stocks, df_etfs], ignore_index=True)
    except Exception:
        df = df_stocks

    print(df.to_json(orient='records', force_ascii=False))
except Exception as e:
    print(json.dumps({"error": str(e)}))
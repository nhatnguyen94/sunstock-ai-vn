#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Market overview for the home page: indices, breadth, liquidity, top movers and a quote for every symbol.

Usage:
    python get_market_overview.py

Everything comes from KBS through vnstock, because it answers the WHOLE market in ~1 second (the VCI source
was timing out at 30 s in production-like conditions):
  * Listing.symbols_by_exchange()          -> symbol, exchange (HOSE / HNX / UPCOM), type      (~1 s)
  * Trading.price_board(all symbols)       -> price, reference, % change, volume, traded value  (~1 s)
  * Quote(<index>).history(last ~60 days)  -> VNINDEX, VN30, HNXINDEX, UPCOMINDEX             (~0.3-2 s each)
They run concurrently, so the whole script takes ~3 s including Python start-up.

Output: one JSON line
{
  "fetched_at": "2026-09-20T03:15:00Z",
  "trade_date": "2026-09-18",                 # date of the latest index session (the board shows that session)
  "indices":   [{"code","name","close","change","percent","volume","series":[[date, close, volume], ...]}],
  "exchanges": {"HOSE": {"count","advancers","decliners","unchanged","ceiling","floor","value","volume"}, ...},
  "movers":    {"ALL": {"gainers":[row], "losers":[row], "value":[row]}, "HOSE": {...}, ...},
  "quotes":    {"FPT": [price, reference, percent, volume, value, ceiling, floor], ...},   # whole VND
  "errors": {...}, "warnings": [...]
}
row = {"symbol","exchange","price","change","percent","volume","value"}.

Only 3-character tickers (real stocks — not ETFs / funds) with at least MIN_VALUE (5 billion VND) traded are
ranked, otherwise UPCOM one-lot trades (+38% on 100 shares) would top every "gainers" list.
"""

import io
import json
import math
import re
import sys
import warnings
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, timedelta, timezone

warnings.filterwarnings('ignore')

if hasattr(sys.stdout, 'buffer'):
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
if hasattr(sys.stderr, 'buffer'):
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8', errors='replace')

# Imported once, up front: importing vnstock modules lazily from several threads at the same time deadlocks
# on Python's per-module import lock ("deadlock detected by _ModuleLock").
import pandas as pd
from vnstock.api.listing import Listing
from vnstock.api.quote import Quote
from vnstock.api.trading import Trading

INDICES = [('VNINDEX', 'VN-Index'), ('VN30', 'VN30'), ('HNXINDEX', 'HNX-Index'), ('UPCOMINDEX', 'UPCoM-Index')]
EXCHANGES = ['HOSE', 'HNX', 'UPCOM']
MIN_VALUE = 5_000_000_000        # 5 billion VND traded before a stock can be ranked as a gainer/loser
TOP_N = 10
SERIES_DAYS = 45                 # calendar days of index history (~30 sessions)
BOARD_CHUNK = 2500        # one request for the whole market (guest tier allows only 20 requests/min)
STOCK_RE = re.compile(r'^[A-Z][A-Z0-9]{2}$')


def num(v, default=None):
    try:
        f = float(v)
    except (TypeError, ValueError):
        return default
    return default if math.isnan(f) else f


def fetch_listing():
    df = Listing(source='KBS').symbols_by_exchange()
    if df is None or df.empty:
        raise RuntimeError('Listing returned no symbols')
    # bonds and covered warrants are rejected by the board endpoint ("Invalid derivative or bond symbol") and are
    # not wanted anyway; ETFs ('fund') stay so a watchlist can hold them
    df = df[df['exchange'].isin(EXCHANGES) & df['type'].isin(['stock', 'fund'])]
    return dict(zip(df['symbol'].astype(str).str.upper(), df['exchange']))


def fetch_board(symbols):
    trading = Trading(source='KBS')
    frames = []
    for i in range(0, len(symbols), BOARD_CHUNK):
        frames.append(trading.price_board(symbols[i:i + BOARD_CHUNK]))
    df = pd.concat([f for f in frames if f is not None and len(f)], ignore_index=True)
    if df.empty:
        raise RuntimeError('Price board returned no rows')
    return df


def fetch_index(code, name):
    start = (datetime.now() - timedelta(days=SERIES_DAYS)).strftime('%Y-%m-%d')
    end = datetime.now().strftime('%Y-%m-%d')
    df = Quote(symbol=code, source='KBS').history(start=start, end=end)
    if df is None or len(df) < 1:
        raise RuntimeError('%s returned no history' % code)
    series = [[str(r['time'])[:10], round(float(r['close']), 2), int(r['volume'])] for _, r in df.iterrows()]
    last = series[-1]
    prev = series[-2][1] if len(series) > 1 else last[1]
    return {
        'code': code, 'name': name, 'close': last[1], 'change': round(last[1] - prev, 2),
        'percent': round((last[1] / prev - 1) * 100, 2) if prev else 0.0,
        'volume': last[2], 'date': last[0], 'series': series,
    }


def analyse(df, exch):
    """Breadth, liquidity, movers and the per-symbol quote map from the price board (whole VND)."""
    quotes = {}
    rows = []
    stats = {e: {'count': 0, 'advancers': 0, 'decliners': 0, 'unchanged': 0, 'ceiling': 0, 'floor': 0, 'value': 0.0, 'volume': 0} for e in EXCHANGES}

    for r in df.to_dict('records'):
        sym = str(r.get('symbol', '')).upper()
        ex = exch.get(sym) or r.get('exchange')
        price, ref = num(r.get('close_price')), num(r.get('reference_price'))
        if not ref or ref <= 0:
            continue
        traded = bool(price and price > 0 and (num(r.get('volume_accumulated'), 0) or 0) > 0)
        price = price if traded else ref                      # untraded today: show the reference price, 0% move
        pct = num(r.get('percent_change'), 0.0) if traded else 0.0
        vol = int(num(r.get('volume_accumulated'), 0) or 0)
        value = float(num(r.get('total_value'), 0.0) or 0.0)
        ceil, floor = num(r.get('ceiling_price')), num(r.get('floor_price'))
        quotes[sym] = [int(round(price)), int(round(ref)), round(pct, 2), vol, int(value),
                       int(ceil) if ceil else None, int(floor) if floor else None]

        if ex in stats and STOCK_RE.match(sym):
            s = stats[ex]
            s['count'] += 1
            s['value'] += value
            s['volume'] += vol
            if traded:
                if price > ref:
                    s['advancers'] += 1
                elif price < ref:
                    s['decliners'] += 1
                else:
                    s['unchanged'] += 1
                if ceil and price >= ceil:
                    s['ceiling'] += 1
                if floor and price <= floor:
                    s['floor'] += 1
            # stocks that did not trade at all are in `count` only — counting them as "unchanged" made UPCoM's
            # ~900 idle tickers dwarf the real picture
            if traded and value >= MIN_VALUE:
                rows.append({'symbol': sym, 'exchange': ex, 'price': int(round(price)), 'change': int(round(price - ref)),
                             'percent': round(pct, 2), 'volume': vol, 'value': int(value)})

    for s in stats.values():
        s['value'] = int(s['value'])

    def board(pool):
        return {
            'gainers': sorted([r for r in pool if r['percent'] > 0], key=lambda r: -r['percent'])[:TOP_N],
            'losers': sorted([r for r in pool if r['percent'] < 0], key=lambda r: r['percent'])[:TOP_N],
            'value': sorted(pool, key=lambda r: -r['value'])[:TOP_N],
        }

    movers = {'ALL': board(rows)}
    for e in EXCHANGES:
        movers[e] = board([r for r in rows if r['exchange'] == e])
    return quotes, stats, movers


def main():
    result = {
        'fetched_at': datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ'),
        'trade_date': None, 'indices': [], 'exchanges': {}, 'movers': {}, 'quotes': {},
        'errors': {}, 'warnings': [],
    }

    with ThreadPoolExecutor(max_workers=6) as pool:
        f_list = pool.submit(fetch_listing)
        f_idx = [(code, pool.submit(fetch_index, code, name)) for code, name in INDICES]

        for code, fut in f_idx:
            try:
                result['indices'].append(fut.result())
            except Exception as exc:  # noqa: BLE001 - one index failing must not lose the rest
                result['errors'][code] = str(exc)[:160]

        try:
            exch = f_list.result()
            df = fetch_board(sorted(exch.keys()))
            result['quotes'], result['exchanges'], result['movers'] = analyse(df, exch)
        except Exception as exc:  # noqa: BLE001
            result['errors']['board'] = str(exc)[:160]

    if not result['indices'] and not result['quotes']:
        print(json.dumps({'error': 'No market data source answered', 'errors': result['errors']}, ensure_ascii=False))
        return

    if result['indices']:
        result['trade_date'] = max(i['date'] for i in result['indices'])
    else:
        result['warnings'].append('No index data — trade date unknown')
        result['trade_date'] = datetime.now().strftime('%Y-%m-%d')
    if not result['quotes']:
        result['warnings'].append('Price board unavailable: %s' % result['errors'].get('board', 'unknown'))

    print(json.dumps(result, ensure_ascii=False, separators=(',', ':')))


if __name__ == '__main__':
    main()

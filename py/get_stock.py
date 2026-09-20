#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Daily OHLCV history for one or more symbols.

Usage:
    python get_stock.py SYM[,SYM...] [start YYYY-MM-DD] [end YYYY-MM-DD]

    start defaults to one year ago, end to today.

Output: ONE JSON line
    {"data": {"FPT": [{"time": 1789000000000, "date": "2026-09-18", "open", "high", "low", "close", "volume"}, ...]},
     "errors": {"XYZ": "message"}, "sources": {"FPT": "KBS-direct"}}
Prices are in thousands of VND (the feed unit the database stores).

Sources, tried per symbol in this order until one answers with rows:
  1. KBS-direct  plain HTTPS GET of KBS's public history endpoint with the standard library — ~0.25 s. It does not import
                 vnstock at all, and importing vnstock alone costs ~1.7 s of every script start.
  2. KBS         the same data through vnstock (imported lazily, only when 1. failed)
  3. VCI         vnstock's VCI source — the slow/unreliable one this script used exclusively before (connection errors,
                 30 s timeouts; a stock page waited ~10 s, sometimes ~100 s with retries)
Set STOCK_SOURCES (comma separated, e.g. "KBS-direct,VCI") to change the order. KBS and VCI prices agree to the cent on
the same day; only very old bars can differ by a dividend-adjustment factor.
"""

import calendar
import concurrent.futures
import io
import json
import logging
import os
import sys
import threading
import time
import urllib.error
import urllib.parse
import urllib.request
from datetime import datetime, timedelta

# Fix encoding issue on Windows
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

# Suppress all logs and banners below WARNING level
logging.getLogger().setLevel(logging.WARNING)

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

SOURCES = [s.strip() for s in os.environ.get('STOCK_SOURCES', 'KBS-direct,KBS,VCI').split(',') if s.strip()]
DIRECT_TIMEOUT = float(os.environ.get('STOCK_DIRECT_TIMEOUT', '12'))

# (KBS_BASE_URL exists so tests/Feature/Support/GetStockScriptTest can point the script at a local fake)
KBS_BASE = os.environ.get('KBS_BASE_URL', 'https://kbbuddywts.kbsec.com.vn/iis-server/investment')
# Indices live under a different path and are quoted in points, not VND
KBS_INDICES = {'VNINDEX', 'HNXINDEX', 'UPCOMINDEX', 'VN30', 'HNX30', 'VN100'}
KBS_HEADERS = {
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
    'Referer': 'https://kbbuddywts.kbsec.com.vn/',
    'Accept': 'application/json',
}

# Concurrency within THIS process — was a plain sequential for-loop (1 symbol
# at a time), which is why a 20-symbol chunk took ~20x a single request's
# latency end to end. MAX_CONCURRENT=4 threads move this to genuinely
# parallel I/O-bound requests (the fetch is a blocking HTTP call, so threads — not asyncio — is the right tool here).
#
# Keep this modest: this app also runs several queue workers concurrently
# (docker/php/supervisord.conf), each spawning its own instance of this
# script — total concurrent requests to the data source = workers x MAX_CONCURRENT, not
# just this number. Pushing this too high risks harder rate-limiting/blocks
# from the provider, which would make things slower, not faster. See docs/PYTHON_INTEGRATION.md.
MAX_CONCURRENT = 4

# Pacing between requests of a BULK run (sleeping in one thread doesn't block the others). A single-symbol call —
# what a web request makes — has nothing to pace against, so it does not sleep.
PACE_SECONDS = 0.3 if len(symbols) > 1 else 0

results = {}
errors = {}
used = {}

_vnstock_lock = threading.Lock()
_quote_cls = None


def quote_class():
    """vnstock's Quote, imported on first use only. Importing vnstock modules from several threads at once
    deadlocks on Python's per-module import lock, hence the lock."""
    global _quote_cls
    with _vnstock_lock:
        if _quote_cls is None:
            from vnstock.api.quote import Quote
            _quote_cls = Quote
    return _quote_cls


def utc_ms(day_time):
    """'2026-09-18 07:00' -> epoch ms of that wall-clock time read as UTC (what the DataFrame -> JSON path produced)."""
    return calendar.timegm(datetime.strptime(day_time[:16], '%Y-%m-%d %H:%M').timetuple()) * 1000


def kbs_direct(symbol):
    """One symbol's daily bars straight from KBS. Raises on any problem so the next source is tried."""
    sd = datetime.strptime(start, '%Y-%m-%d').strftime('%d-%m-%Y')
    ed = datetime.strptime(end, '%Y-%m-%d').strftime('%d-%m-%Y')
    is_index = symbol in KBS_INDICES
    url = '%s/%s/%s/data_day?%s' % (KBS_BASE, 'index' if is_index else 'stocks', urllib.parse.quote(symbol),
                                     urllib.parse.urlencode({'sdate': sd, 'edate': ed}))
    req = urllib.request.Request(url, headers=KBS_HEADERS)
    with urllib.request.urlopen(req, timeout=DIRECT_TIMEOUT) as resp:
        payload = json.loads(resp.read().decode('utf-8'))

    rows = payload.get('data_day') or []
    unit = 1 if is_index else 1000
    out = []
    for r in rows:
        day = str(r['t'])[:10]
        if day < start:
            continue
        out.append({
            'time': utc_ms(str(r['t'])),
            # KBS quotes stocks in VND (the database keeps thousands of VND); index levels are already in points
            'open': round(float(r['o']) / unit, 3), 'high': round(float(r['h']) / unit, 3),
            'low': round(float(r['l']) / unit, 3), 'close': round(float(r['c']) / unit, 3),
            'volume': int(float(r['v'])), 'date': day,
        })
    out.sort(key=lambda x: x['time'])
    return out


def vnstock_history(symbol, source):
    df = quote_class()(symbol=symbol, source=source).history(start=start, end=end)
    if df is None or df.empty:
        return []
    records = json.loads(df.to_json(orient='records', force_ascii=False))
    for rec, t in zip(records, df['time']):
        rec['date'] = str(t)[:10]
    return records


def fetch_one(symbol):
    last_error = None
    answered_empty = False
    for source in SOURCES:
        try:
            rows = kbs_direct(symbol) if source == 'KBS-direct' else vnstock_history(symbol, source)
            # A direct KBS answer is authoritative even when it holds no rows (an incremental window with no new
            # session, a suspended symbol): falling back to the slow sources for that would cost seconds for nothing.
            # They are only tried when KBS-direct FAILED (exception).
            if rows or source == 'KBS-direct':
                if PACE_SECONDS:
                    time.sleep(PACE_SECONDS)
                return symbol, rows, None, source if rows else None
            answered_empty = True
        except Exception as e:  # noqa: BLE001 - try the next source
            last_error = '%s: %s' % (source, e)

    if PACE_SECONDS:
        time.sleep(PACE_SECONDS)

    # a source answered "no rows" and none failed with an error -> the symbol simply has no bars in the range
    if answered_empty and last_error is None:
        return symbol, [], None, None
    return symbol, None, last_error or 'no source returned data', None


with concurrent.futures.ThreadPoolExecutor(max_workers=MAX_CONCURRENT) as executor:
    for symbol, data, error, source in executor.map(fetch_one, symbols):
        if error is not None:
            errors[symbol] = error
        else:
            results[symbol] = data
            if source:
                used[symbol] = source

output = {"data": results, "errors": errors, "sources": used}

# Output JSON data only (clean, single line)
print(json.dumps(output, ensure_ascii=False))

#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Fetch domestic gold/silver prices (SJC + Bao Tin Minh Chau) and the world gold price via vnstock.

Usage:
    python get_gold_price.py

Output: one JSON line
{
  "fetched_at": "2026-09-20T03:15:00Z",
  "sjc":  [{"product","branch","buy","sell"}],                       # VND per luong (37.5 g)
  "btmc": [{"metal","product","purity","buy","sell","unit","quoted_at"}],
  "world_usd_oz": 4378.0 | null,                                     # USD per troy ounce
  "errors": {"sjc": "...", ...}, "warnings": ["..."]
}
BTMC quotes gold per "chi" (1/10 luong) — converted here so every gold price is VND/luong.
Silver keeps the pack size that is in its name (1 luong / 5 luong / 1 kg) and unit == "pack".
quoted_at is UTC ISO (BTMC publishes Vietnam local time, UTC+7).

Why SJC is cross-checked: SJC's by-date endpoint (what vnstock calls) has been observed returning stale /
mixed-up results (the same call answered 47.9M, 72M and 144.6M VND). A SJC quote is only kept when it agrees
with BTMC's own "VANG MIENG SJC" line within 4%; otherwise it is dropped with a warning.
"""

import sys
import io
import json
import math
import re
import warnings
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, timedelta, timezone

warnings.filterwarnings('ignore')

if hasattr(sys.stdout, 'buffer'):
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
if hasattr(sys.stderr, 'buffer'):
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8', errors='replace')

SJC_CROSSCHECK_TOLERANCE = 0.04
VN_OFFSET = timedelta(hours=7)


def money(v):
    """'14,360,000' / 14360000.0 / '0' -> int VND, None when empty or zero."""
    try:
        f = float(str(v).replace(',', '').strip())
    except (TypeError, ValueError):
        return None
    if math.isnan(f) or f <= 0:
        return None
    return int(round(f))


def fetch_sjc():
    from vnstock.explorer.misc.gold_price import sjc_gold_price
    df = sjc_gold_price()
    if df is None or df.empty:
        raise RuntimeError('SJC returned no data')
    out = []
    for r in df.to_dict('records'):
        buy, sell = money(r.get('buy_price')), money(r.get('sell_price'))
        if buy and sell:
            out.append({'product': str(r['name']).strip(), 'branch': str(r['branch']).strip(), 'buy': buy, 'sell': sell})
    return out


def fetch_btmc():
    from vnstock.explorer.misc.gold_price import btmc_goldprice
    df = btmc_goldprice()
    if df is None or df.empty:
        raise RuntimeError('BTMC returned no data')
    rows, world = [], []
    for r in df.to_dict('records'):
        name = str(r.get('name', '')).strip()
        upper = name.upper()
        try:
            local = datetime.strptime(str(r.get('time')), '%d/%m/%Y %H:%M')
        except ValueError:
            continue
        quoted = (local - VN_OFFSET).replace(tzinfo=timezone.utc)

        w = money(r.get('world_price'))
        if w:
            world.append((quoted, float(w)))

        buy, sell = money(r.get('buy_price')), money(r.get('sell_price'))
        if not buy:
            continue

        if upper.startswith('BẠC'):
            rows.append({'metal': 'silver', 'product': name, 'purity': None, 'buy': buy, 'sell': sell,
                         'unit': 'pack', 'quoted_at': quoted.strftime('%Y-%m-%dT%H:%M:%SZ')})
        elif str(r.get('karat', '')).strip():
            if 'ĐỒNG XU' in upper:
                continue  # priced per coin (0.1 chi), not per chi — would corrupt the per-luong comparison
            rows.append({'metal': 'gold', 'product': name, 'purity': str(r.get('gold_content', '')).strip() or None,
                         # per chi -> per luong
                         'buy': buy * 10, 'sell': sell * 10 if sell else None,
                         'unit': 'luong', 'quoted_at': quoted.strftime('%Y-%m-%dT%H:%M:%SZ')})
    latest_world = max(world)[1] if world else None
    return rows, latest_world


def main():
    result = {'fetched_at': datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ'),
              'sjc': [], 'btmc': [], 'world_usd_oz': None, 'errors': {}, 'warnings': []}

    with ThreadPoolExecutor(max_workers=2) as pool:
        f_sjc, f_btmc = pool.submit(fetch_sjc), pool.submit(fetch_btmc)
        try:
            result['sjc'] = f_sjc.result()
        except Exception as exc:  # noqa: BLE001 - one source failing must not lose the other
            result['errors']['sjc'] = str(exc)[:160]
        try:
            result['btmc'], result['world_usd_oz'] = f_btmc.result()
        except Exception as exc:  # noqa: BLE001
            result['errors']['btmc'] = str(exc)[:160]

    # Cross-check SJC against BTMC's own SJC line (latest quote), both per luong
    if result['sjc'] and result['btmc']:
        ref = [r for r in result['btmc'] if r['metal'] == 'gold' and 'SJC' in r['product'].upper() and r['sell']]
        if ref:
            ref_sell = max(ref, key=lambda r: r['quoted_at'])['sell']
            sjc_sell = result['sjc'][0]['sell']
            if abs(sjc_sell - ref_sell) / ref_sell > SJC_CROSSCHECK_TOLERANCE:
                result['warnings'].append('SJC quote %d disagrees with BTMC SJC line %d — dropped' % (sjc_sell, ref_sell))
                result['sjc'] = []

    if not result['sjc'] and not result['btmc']:
        print(json.dumps({'error': 'No gold price source answered', 'errors': result['errors']}, ensure_ascii=False))
        return

    print(json.dumps(result, ensure_ascii=False))


if __name__ == '__main__':
    main()

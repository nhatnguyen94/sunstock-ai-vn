#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Fetch one fund's NAV history + portfolio breakdown (Fmarket via vnstock Fund API).

Usage:
    python get_fund_detail.py <short_name>

The full NAV history can be ~4000 daily points (20 years). To keep the payload
small the last 3 years are kept daily and anything older is thinned to one point
per week.

Output: JSON {
  "nav":          [["YYYY-MM-DD", nav_per_unit], ...]   # ascending
  "top_holdings": [{"code","industry","percent"}],
  "industries":   [{"name","percent"}],
  "assets":       [{"name","percent"}],
  "as_of":        "YYYY-MM-DD" | null,
  "errors":       {"section": "message"}
}  or { "error": "..." } when even the NAV history is unavailable.
"""

import sys
import io
import json
import math
import re
import warnings
from concurrent.futures import ThreadPoolExecutor
from datetime import date, timedelta

warnings.filterwarnings('ignore')

if hasattr(sys.stdout, 'buffer'):
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
if hasattr(sys.stderr, 'buffer'):
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8', errors='replace')

DAILY_YEARS = 3


def num(v, nd=2):
    try:
        f = float(v)
    except (TypeError, ValueError):
        return None
    return None if (math.isnan(f) or math.isinf(f)) else round(f, nd)


def text(v):
    if v is None or (isinstance(v, float) and math.isnan(v)):
        return None
    return str(v).strip() or None


def thin_nav(df):
    """[[date_str, nav]] ascending; one point per week before the daily window."""
    cutoff = (date.today() - timedelta(days=365 * DAILY_YEARS)).isoformat()
    points = []
    last_week = None
    for d, v in zip(df['date'].astype(str).str[:10], df['nav_per_unit']):
        nav = num(v)
        if nav is None:
            continue
        if d >= cutoff:
            points.append([d, nav])
            continue
        iso = date.fromisoformat(d).isocalendar()
        week = (iso[0], iso[1])
        if week != last_week:
            points.append([d, nav])
            last_week = week
        else:
            points[-1] = [d, nav]  # keep the last observation of the week
    return points


def main():
    code = sys.argv[1].strip() if len(sys.argv) > 1 else ''
    if not re.fullmatch(r'[A-Za-z0-9._-]{2,30}', code):
        print(json.dumps({'error': 'Invalid fund code'}))
        return

    try:
        from vnstock import Fund
        details = Fund().details
    except Exception as exc:  # noqa: BLE001
        print(json.dumps({'error': str(exc)[:300]}))
        return

    tasks = {
        'nav': lambda: details.nav_report(code),
        'top': lambda: details.top_holding(code),
        'industry': lambda: details.industry_holding(code),
        'asset': lambda: details.asset_holding(code),
    }
    res, errors = {}, {}
    with ThreadPoolExecutor(max_workers=4) as pool:
        futs = {k: pool.submit(fn) for k, fn in tasks.items()}
        for k, fut in futs.items():
            try:
                res[k] = fut.result()
            except Exception as exc:  # noqa: BLE001 - per-section isolation
                res[k] = None
                errors[k] = str(exc)[:160]

    nav_df = res.get('nav')
    if nav_df is None or getattr(nav_df, 'empty', True):
        print(json.dumps({'error': 'Không có dữ liệu NAV cho quỹ ' + code, 'errors': errors}, ensure_ascii=False))
        return

    top, industry, asset = res.get('top'), res.get('industry'), res.get('asset')

    top_holdings, as_of = [], None
    if top is not None and not top.empty:
        for r in top.to_dict('records'):
            top_holdings.append({
                'code': text(r.get('stock_code')),
                'industry': text(r.get('industry')),
                'percent': num(r.get('net_asset_percent')),
            })
        as_of = text(top.iloc[0].get('update_at'))

    print(json.dumps({
        'nav': thin_nav(nav_df.sort_values('date')),
        'top_holdings': top_holdings,
        'industries': [] if industry is None or industry.empty else [
            {'name': text(r.get('industry')), 'percent': num(r.get('net_asset_percent'))}
            for r in industry.to_dict('records')],
        'assets': [] if asset is None or asset.empty else [
            {'name': text(r.get('asset_type')), 'percent': num(r.get('asset_percent'))}
            for r in asset.to_dict('records')],
        'as_of': as_of,
        'errors': errors,
    }, ensure_ascii=False))


if __name__ == '__main__':
    main()

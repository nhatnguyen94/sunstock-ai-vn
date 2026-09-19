#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""List all open-ended funds from Fmarket via vnstock Fund API.

Usage:
    python get_fund_list.py

Output: JSON { "funds": [ {short_name, name, fund_type, fund_owner_name, management_fee,
                           inception_date, nav, nav_change_*..., nav_update_at, fund_id_fmarket} ] }
        or { "error": "..." }
"""

import sys
import io
import json
import math
import warnings

warnings.filterwarnings('ignore')

if hasattr(sys.stdout, 'buffer'):
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
if hasattr(sys.stderr, 'buffer'):
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8', errors='replace')

COLUMNS = [
    'short_name', 'name', 'fund_type', 'fund_owner_name', 'management_fee', 'inception_date',
    'nav', 'nav_change_previous', 'nav_change_last_year', 'nav_change_inception',
    'nav_change_1m', 'nav_change_3m', 'nav_change_6m', 'nav_change_12m',
    'nav_change_24m', 'nav_change_36m', 'nav_change_36m_annualized',
    'nav_update_at', 'fund_id_fmarket',
]


def clean(v):
    if v is None:
        return None
    if hasattr(v, 'item'):
        try:
            v = v.item()
        except Exception:
            pass
    if isinstance(v, float):
        return None if (math.isnan(v) or math.isinf(v)) else v
    if isinstance(v, str):
        return v.strip() or None
    if isinstance(v, (int, bool)):
        return v
    if hasattr(v, 'isoformat'):
        return v.isoformat()[:10]
    return str(v)


def main():
    try:
        from vnstock import Fund

        df = Fund().listing()
        if df is None or df.empty:
            print(json.dumps({'error': 'Fmarket returned no funds'}))
            return

        funds = []
        for rec in df.to_dict('records'):
            row = {c: clean(rec.get(c)) for c in COLUMNS}
            if row['short_name']:
                funds.append(row)

        print(json.dumps({'funds': funds}, ensure_ascii=False))
    except Exception as exc:  # noqa: BLE001
        print(json.dumps({'error': str(exc)[:300]}))


if __name__ == '__main__':
    main()

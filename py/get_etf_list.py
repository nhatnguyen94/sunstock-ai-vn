#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""List every exchange-traded fund (ETF) and listed closed-end fund on HOSE via vnstock (KBS listing).

Usage:
    python get_etf_list.py

Output: JSON { "etfs": [ {symbol, name, name_en, exchange} ] }  or  { "error": "..." }

vnstock has no NAV / iNAV, holdings or fee data for ETFs (Fmarket only lists open-end funds, and the Company API
rejects them), so this is only the roster; performance is computed from the stored daily prices.
"""

import sys
import io
import json
import warnings

warnings.filterwarnings('ignore')

if hasattr(sys.stdout, 'buffer'):
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
if hasattr(sys.stderr, 'buffer'):
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8', errors='replace')


def text(v):
    if v is None:
        return None
    s = str(v).strip()
    return None if s == '' or s.lower() in ('nan', 'none') else s


def main():
    try:
        from vnstock import Listing

        df = Listing(source='KBS').symbols_by_exchange()
        if df is None or df.empty:
            print(json.dumps({'error': 'KBS returned no listing'}))
            return

        funds = df[df['type'].astype(str).str.lower() == 'fund']
        etfs = [
            {
                'symbol': text(r.get('symbol')),
                'name': text(r.get('organ_name')),
                'name_en': text(r.get('en_organ_name')),
                'exchange': text(r.get('exchange')),
            }
            for r in funds.to_dict(orient='records')
            if text(r.get('symbol'))
        ]
        etfs.sort(key=lambda e: e['symbol'])

        if not etfs:
            print(json.dumps({'error': 'No fund symbols in the listing'}))
            return

        print(json.dumps({'etfs': etfs}, ensure_ascii=False))
    except SystemExit:
        print(json.dumps({'error': 'vnstock rate limit reached, try again in a minute'}))
    except Exception as e:
        print(json.dumps({'error': str(e)[:300]}))


if __name__ == '__main__':
    main()

#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Fetch company financial data via vnstock Finance API (KBS source).

Usage:
    python get_company_finance.py <symbol> <type> <period>
        type:   income | balance | cashflow | ratio
        period: quarter | year

Output: JSON { "data": [...], "periods": [...] }  or  { "error": "..." }
"""

import sys
import io
import json
import os
import math

# Force stdout/stderr to UTF-8 regardless of Windows console encoding
if hasattr(sys.stdout, 'buffer'):
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
if hasattr(sys.stderr, 'buffer'):
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8', errors='replace')

META_COLS = {'item', 'item_en', 'item_id', 'unit', 'levels', 'row_number'}


def safe_float(val):
    try:
        f = float(val)
        return None if math.isnan(f) or math.isinf(f) else round(f, 2)
    except (TypeError, ValueError):
        return None


def main():
    symbol      = sys.argv[1].upper() if len(sys.argv) > 1 else 'FPT'
    report_type = sys.argv[2]          if len(sys.argv) > 2 else 'income'
    period      = sys.argv[3]          if len(sys.argv) > 3 else 'quarter'

    if report_type not in {'income', 'balance', 'cashflow', 'ratio'}:
        print(json.dumps({'error': 'Invalid report type'}))
        return

    if period not in {'quarter', 'year'}:
        print(json.dumps({'error': 'Invalid period'}))
        return

    try:
        from vnstock import Finance  # noqa: PLC0415

        finance = Finance(source='kbs', symbol=symbol, show_log=False)

        if report_type == 'income':
            df = finance.income_statement(period=period)
        elif report_type == 'balance':
            df = finance.balance_sheet(period=period)
        elif report_type == 'cashflow':
            df = finance.cash_flow(period=period)
        else:
            df = finance.ratio(period=period)

        if df is None or df.empty:
            print(json.dumps({'data': [], 'periods': []}))
            return

        # Identify period columns (anything not in META_COLS)
        period_cols = [c for c in df.columns if c not in META_COLS]

        rows = []
        for _, row in df.iterrows():
            r = {
                'item':   str(row.get('item',   '')).strip(),
                'unit':   str(row.get('unit',   '')).strip(),
                'levels': int(row['levels']) if 'levels' in row and str(row.get('levels', '')).replace('.', '', 1).isdigit() else 1,
            }
            for col in period_cols:
                r[col] = safe_float(row.get(col))
            rows.append(r)

        print(json.dumps({'data': rows, 'periods': period_cols}, ensure_ascii=False))

    except Exception as exc:
        print(json.dumps({'error': str(exc)}))


if __name__ == '__main__':
    main()

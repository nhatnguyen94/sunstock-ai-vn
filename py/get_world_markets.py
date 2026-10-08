#!/usr/bin/env python3
"""
World stock indices for the home page "Thế giới" strip, from vnstock's MSN source (daily bars, no API key).

Output: ONE JSON line (vnstock prints a promo box before it: the caller scans backwards for the JSON line)
{
  "fetched_at": "2026-10-08T03:15:00Z",
  "markets": [{"code","name","region","close","previous","change","percent","date","series":[close, ...]}],
  "errors": {"CODE": "message"}
}
`date` is the date of the last bar (the most recent finished session of that market); `series` holds up to 8 closes, oldest first.
A market that does not answer is reported in "errors" and left out: one failing index must not lose the rest.
"""

import io
import json
import sys
import warnings
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, timedelta, timezone

warnings.filterwarnings('ignore')

if hasattr(sys.stdout, 'buffer'):
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
if hasattr(sys.stderr, 'buffer'):
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8', errors='replace')

from vnstock.api.quote import Quote

MARKETS = [
    ('INX', 'S&P 500', 'Mỹ'),
    ('COMP', 'Nasdaq', 'Mỹ'),
    ('DJI', 'Dow Jones', 'Mỹ'),
    ('UKX', 'FTSE 100', 'Anh'),
    ('DAX', 'DAX', 'Đức'),
    ('N225', 'Nikkei 225', 'Nhật'),
    ('HSI', 'Hang Seng', 'Hồng Kông'),
    ('000001', 'Shanghai', 'Trung Quốc'),
]
LOOKBACK_DAYS = 16
SERIES_LEN = 8


def fetch(code, name, region):
    end = datetime.now()
    df = Quote(source='MSN', symbol=code).history(
        start=(end - timedelta(days=LOOKBACK_DAYS)).strftime('%Y-%m-%d'), end=end.strftime('%Y-%m-%d'), interval='1D')
    if df is None or len(df) < 2:
        raise ValueError('fewer than two daily bars')
    rows = [(r['time'], float(r['close'])) for r in df.to_dict('records') if r.get('close') == r.get('close') and r.get('close')]
    if len(rows) < 2:
        raise ValueError('fewer than two valid closes')
    rows = rows[-SERIES_LEN:]
    (_, previous), (last_time, close) = rows[-2], rows[-1]
    change = close - previous
    return {
        'code': code, 'name': name, 'region': region,
        'close': round(close, 2), 'previous': round(previous, 2), 'change': round(change, 2),
        'percent': round(change / previous * 100, 2) if previous else 0.0,
        'date': last_time.strftime('%Y-%m-%d') if hasattr(last_time, 'strftime') else str(last_time)[:10],
        'series': [round(c, 2) for _, c in rows],
    }


def main():
    result = {'fetched_at': datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ'), 'markets': [], 'errors': {}}

    with ThreadPoolExecutor(max_workers=4) as pool:
        futures = [(code, pool.submit(fetch, code, name, region)) for code, name, region in MARKETS]
        for code, fut in futures:
            try:
                result['markets'].append(fut.result())
            except Exception as exc:  # noqa: BLE001 - keep the others
                result['errors'][code] = str(exc)[:160]

    if not result['markets']:
        print(json.dumps({'error': 'No world market answered', 'errors': result['errors']}, ensure_ascii=False))
        return

    print(json.dumps(result, ensure_ascii=False, separators=(',', ':')))


if __name__ == '__main__':
    main()

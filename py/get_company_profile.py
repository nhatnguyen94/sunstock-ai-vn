#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Fetch a full company profile via vnstock Company API (KBS + VCI merged).

Usage:
    python get_company_profile.py <symbol>

Every section is fetched concurrently and independently, so one failing
source/section never breaks the others (it just becomes null and is listed in
"errors"). KBS is the primary source (stable); VCI fills in what KBS lacks
(valuation snapshot, corporate events, long shareholder list).

Output: one JSON line
{
  "symbol": "FPT",
  "overview":     {...} | null,
  "ownership":    [{"type","percent","shares"}],      # ownership structure (donut)
  "shareholders": [{"name","percent","shares"}],      # major holders, % already x100
  "officers":     [{"name","position","group","since","independent","own_percent","own_quantity"}],
  "subsidiaries": [{"name","charter_capital","percent"}],
  "affiliates":   [{"name","charter_capital","percent"}],
  "events":       [{"category","name","title","public_date","record_date","exright_date",...}],
  "errors":       {"section": "message"}
}
or { "error": "..." } when the symbol is unknown / nothing could be fetched.
"""

import sys
import io
import json
import math
import re
import unicodedata
import warnings
from concurrent.futures import ThreadPoolExecutor

warnings.filterwarnings('ignore')

if hasattr(sys.stdout, 'buffer'):
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
if hasattr(sys.stderr, 'buffer'):
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8', errors='replace')

MAX_CONCURRENT = 6
MAX_EVENTS = 60
MAX_SHAREHOLDERS = 20
HONORIFIC = re.compile(r'^(ông|bà|mr\.?|mrs\.?|ms\.?)\s+', re.IGNORECASE)


def clean(v):
    """NaN/inf/numpy/Timestamp -> plain JSON-safe python values."""
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
        try:
            return v.isoformat()
        except Exception:
            return None
    if isinstance(v, (dict, list)):
        return None  # nested blobs (e.g. VCI prev_insight) are not needed
    return str(v)


def num(v, ndigits=None):
    v = clean(v)
    if v is None or isinstance(v, bool):
        return None
    try:
        f = float(v)
    except (TypeError, ValueError):
        return None
    return round(f, ndigits) if ndigits is not None else f


def pct_from_fraction(v):
    f = num(v)
    return None if f is None else round(f * 100, 2)


def date_only(v):
    v = clean(v)
    return v[:10] if isinstance(v, str) and len(v) >= 10 else v


def norm_name(name):
    if not name:
        return ''
    n = unicodedata.normalize('NFC', str(name)).strip()
    n = HONORIFIC.sub('', n)
    return re.sub(r'\s+', ' ', n).lower()


def rows(df):
    """DataFrame -> list of dict (tolerates duplicate column names)."""
    if df is None or getattr(df, 'empty', True):
        return []
    cols = list(df.columns)
    return [dict(zip(cols, r)) for r in df.itertuples(index=False, name=None)]


# ── Section fetchers ────────────────────────────────────────────────────────

def kbs(symbol):
    from vnstock import Company
    return Company(source='KBS', symbol=symbol)


def vci(symbol):
    from vnstock import Company
    return Company(source='VCI', symbol=symbol)


def fetch_kbs_overview(symbol):
    return rows(kbs(symbol).overview())


def fetch_kbs_ownership(symbol):
    return rows(kbs(symbol).ownership())


def fetch_kbs_shareholders(symbol):
    return rows(kbs(symbol).shareholders())


def fetch_kbs_officers(symbol):
    return rows(kbs(symbol).officers())


def fetch_kbs_subsidiaries(symbol):
    return rows(kbs(symbol).subsidiaries())


def fetch_vci_overview(symbol):
    return rows(vci(symbol).overview())


def fetch_vci_shareholders(symbol):
    return rows(vci(symbol).shareholders())


def fetch_vci_officers(symbol):
    return rows(vci(symbol).officers())


def fetch_vci_events(symbol):
    return rows(vci(symbol).events())


TASKS = {
    'kbs_overview': fetch_kbs_overview,
    'kbs_ownership': fetch_kbs_ownership,
    'kbs_shareholders': fetch_kbs_shareholders,
    'kbs_officers': fetch_kbs_officers,
    'kbs_subsidiaries': fetch_kbs_subsidiaries,
    'vci_overview': fetch_vci_overview,
    'vci_shareholders': fetch_vci_shareholders,
    'vci_officers': fetch_vci_officers,
    'vci_events': fetch_vci_events,
}


# ── Normalisers ─────────────────────────────────────────────────────────────

def build_overview(kbs_rows, vci_rows):
    k = kbs_rows[0] if kbs_rows else {}
    v = vci_rows[0] if vci_rows else {}
    if not k and not v:
        return None

    # vnstock returns an all-empty/zero row (not an error) for unknown tickers
    if not any(clean(k.get(f)) for f in ('business_model', 'exchange', 'address', 'founded_date'))             and not any(clean(v.get(f)) for f in ('organ_name', 'company_profile')):
        return None

    charter = num(k.get('charter_capital')) or None
    # KBS reports charter capital in billion VND in some payloads and VND in others
    if charter is not None and charter < 1e9:
        charter *= 1e9

    return {
        'name': clean(v.get('organ_name')),
        'short_name': clean(v.get('organ_short_name')),
        'exchange': clean(k.get('exchange')),
        'sector': clean(v.get('sector')),
        'company_type': clean(k.get('company_type')),
        'business_model': clean(k.get('business_model')),
        'history': clean(k.get('history')),
        'profile': clean(v.get('company_profile')),
        'founded_date': clean(k.get('founded_date')),
        'listing_date': date_only(k.get('listing_date') or v.get('listing_date')),
        'charter_capital': charter,
        'outstanding_shares': num(k.get('outstanding_shares')) or num(v.get('issue_share')),
        'employees': num(k.get('number_of_employees')) or None,
        'par_value': num(k.get('par_value')) or None,
        'address': clean(k.get('address')),
        'phone': clean(k.get('phone')),
        'email': clean(k.get('email')),
        'website': clean(k.get('website')),
        'tax_id': clean(k.get('tax_id')),
        'auditor': clean(k.get('auditor')),
        'ceo_name': HONORIFIC.sub('', clean(k.get('ceo_name')) or '') or None,
        'ceo_position': clean(k.get('ceo_position')),
        # valuation snapshot (as of sync time)
        'current_price': num(v.get('current_price')),
        'market_cap': num(v.get('market_cap')),
        'highest_price_1y': num(v.get('highest_price1_year')),
        'lowest_price_1y': num(v.get('lowest_price1_year')),
        'avg_volume_1m': num(v.get('average_match_volume1_month')),
        'foreign_percent': pct_from_fraction(v.get('foreigner_percentage')),
        'foreign_max_percent': pct_from_fraction(v.get('maximum_foreign_percentage')),
        'state_percent': pct_from_fraction(v.get('state_percentage')),
        'free_float_percent': pct_from_fraction(v.get('free_float_percentage')),
        'rating': clean(v.get('rating')),
        'rating_as_of': clean(v.get('rating_as_of')),
        'target_price': num(v.get('target_price')),
        'upside_percent': pct_from_fraction(v.get('upside_to_target_percent')),
        'dividend_per_share': num(v.get('dividend_per_share_tsr')),
    }


def build_ownership(kbs_rows):
    out = []
    for r in kbs_rows:
        p = num(r.get('ownership_percentage'), 2)
        if p is None or p <= 0:
            continue
        out.append({
            'type': clean(r.get('owner_type')),
            'percent': p,
            'shares': num(r.get('shares_owned')),
        })
    return out


def build_shareholders(vci_rows, kbs_rows):
    out = []
    for r in vci_rows:
        p = pct_from_fraction(r.get('share_own_percent'))
        name = clean(r.get('share_holder'))
        if not name or p is None or p <= 0:
            continue
        out.append({'name': name, 'percent': p, 'shares': num(r.get('quantity')),
                    'date': date_only(r.get('update_date'))})
    if not out:  # VCI down -> fall back to KBS (top holders only)
        for r in kbs_rows:
            name = clean(r.get('name'))
            p = num(r.get('ownership_percentage'), 2)
            if name and p:
                out.append({'name': name, 'percent': p, 'shares': num(r.get('shares_owned')),
                            'date': date_only(r.get('update_date'))})
    out.sort(key=lambda x: x['percent'], reverse=True)
    return out[:MAX_SHAREHOLDERS]


def officer_group(position):
    p = (position or '').upper()
    if 'BKS' in p or 'KIỂM SOÁT' in p:
        return 'supervisory'
    if 'HĐQT' in p or 'HỘI ĐỒNG QUẢN TRỊ' in p:
        return 'board'
    return 'executive'


def build_officers(kbs_rows, vci_rows):
    holdings = {}
    for r in vci_rows:
        key = norm_name(clean(r.get('officer_name')))
        if key:
            holdings[key] = (pct_from_fraction(r.get('officer_own_percent')),
                             num(r.get('officer_own_quantity')))
    out, seen = [], set()

    for r in kbs_rows:
        name = clean(r.get('name'))
        if not name:
            continue
        key = norm_name(name)
        seen.add(key)
        since = clean(r.get('from_date'))
        independent = isinstance(since, str) and 'độc lập' in since.lower()
        year = since if isinstance(since, int) else (int(since) if isinstance(since, str) and since.isdigit() else None)
        own = holdings.get(key, (None, None))
        position = clean(r.get('position'))
        out.append({
            'name': HONORIFIC.sub('', name),
            'position': position,
            'position_en': clean(r.get('position_en')),
            'group': officer_group(position),
            'since': year,
            'independent': independent,
            'own_percent': own[0],
            'own_quantity': own[1],
        })

    for r in vci_rows:  # officers KBS didn't list
        name = clean(r.get('officer_name'))
        if not name or norm_name(name) in seen:
            continue
        position = clean(r.get('officer_position'))
        out.append({
            'name': name, 'position': position, 'position_en': None,
            'group': officer_group(position), 'since': None, 'independent': False,
            'own_percent': pct_from_fraction(r.get('officer_own_percent')),
            'own_quantity': num(r.get('officer_own_quantity')),
        })
    return out


def build_related(kbs_rows):
    subs, affs = [], []
    for r in kbs_rows:
        name = clean(r.get('name'))
        if not name:
            continue
        item = {'name': name, 'charter_capital': num(r.get('charter_capital')),
                'percent': num(r.get('ownership_percent'), 2)}
        kind = (clean(r.get('type')) or '').lower()
        (affs if 'liên kết' in kind or 'lien ket' in kind else subs).append(item)
    return subs, affs


def build_events(vci_rows):
    out = []
    for r in vci_rows:
        title = clean(r.get('event_title_vi'))
        if not title:
            continue
        out.append({
            'category': clean(r.get('category')) or 'OTHER',
            'code': clean(r.get('event_code')),
            'name': clean(r.get('event_name_vi')),
            'title': title,
            'public_date': date_only(r.get('public_date')),
            'record_date': date_only(r.get('record_date')),
            'exright_date': date_only(r.get('exright_date')),
            'issue_date': date_only(r.get('issue_date')),
            'payout_date': date_only(r.get('payout_date')),
            'ratio': num(r.get('exercise_ratio')),
            'value_per_share': num(r.get('value_per_share')),
        })
    out.sort(key=lambda e: e['public_date'] or '', reverse=True)
    return out[:MAX_EVENTS]


def main():
    symbol = sys.argv[1].upper().strip() if len(sys.argv) > 1 else ''
    if not re.fullmatch(r'[A-Z0-9]{2,10}', symbol):
        print(json.dumps({'error': 'Invalid symbol'}))
        return

    results, errors = {}, {}
    with ThreadPoolExecutor(max_workers=MAX_CONCURRENT) as pool:
        futures = {name: pool.submit(fn, symbol) for name, fn in TASKS.items()}
        for name, fut in futures.items():
            try:
                results[name] = fut.result()
            except Exception as exc:  # noqa: BLE001 - per-section isolation is the point
                results[name] = []
                errors[name] = str(exc)[:160]

    overview = build_overview(results['kbs_overview'], results['vci_overview'])
    if overview is None:
        # Every section threw and none said "invalid ticker" => the data source is down,
        # not the symbol wrong. Callers must not cache that as "symbol does not exist".
        rejected = any(('không hợp lệ' in m.lower() or 'invalid' in m.lower()) for m in errors.values())
        transient = len(errors) == len(TASKS) and not rejected
        print(json.dumps({
            'error': 'Không tìm thấy thông tin công ty cho mã ' + symbol,
            'transient': transient,
            'errors': errors,
        }, ensure_ascii=False))
        return

    # KBS is authoritative here: VCI's subsidiary/affiliate payloads carry no charter capital
    subsidiaries, affiliates = build_related(results['kbs_subsidiaries'])

    print(json.dumps({
        'symbol': symbol,
        'overview': overview,
        'ownership': build_ownership(results['kbs_ownership']),
        'shareholders': build_shareholders(results['vci_shareholders'], results['kbs_shareholders']),
        'officers': build_officers(results['kbs_officers'], results['vci_officers']),
        'subsidiaries': subsidiaries,
        'affiliates': affiliates,
        'events': build_events(results['vci_events']),
        'errors': errors,
    }, ensure_ascii=False))


if __name__ == '__main__':
    main()

@extends('layouts.app')

@section('head')
<style>
.screener-filter-panel{background:#fff;border-radius:16px;padding:1.5rem;box-shadow:0 2px 12px rgba(0,0,0,0.06);margin-top:-2.5rem;position:relative;z-index:2;}
.screener-filter-panel label{font-size:0.78rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.03em;margin-bottom:4px;}
.screener-filter-panel .form-control{border-radius:10px;border:1px solid #e5e7eb;font-size:0.9rem;}
.screener-table{background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.06);overflow:hidden;margin-top:1.5rem;}
.screener-table table{width:100%;margin:0;}
.screener-table th{background:#f8fafc;color:#6b7280;font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.03em;padding:0.9rem 1rem;border-bottom:2px solid #e5e7eb;white-space:nowrap;}
.screener-table th a{color:inherit;text-decoration:none;}
.screener-table th a:hover{color:#2563eb;}
.screener-table td{padding:0.9rem 1rem;border-bottom:1px solid #f1f5f9;font-size:0.9rem;color:#1f2937;}
.screener-table tbody tr:hover{background:#f8fafc;}
.symbol-badge{font-weight:800;color:#2563eb;text-decoration:none;}
.symbol-badge:hover{text-decoration:underline;}
</style>
@endsection

@section('content')
<section class="compare-header">
    <div class="container">
        <div class="row align-items-center" style="position:relative;z-index:1;padding-bottom:2rem;">
            <div class="col-md-8">
                <div style="display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.25);border-radius:30px;padding:5px 16px;margin-bottom:1rem;font-size:0.8rem;font-weight:600;">
                    <i class="bi bi-funnel"></i>
                    Loc theo chi so tai chinh
                </div>
                <h1 style="font-size:2.4rem;font-weight:800;margin-bottom:0.5rem;color:white;line-height:1.2;">Stock Screener</h1>
                <p style="opacity:0.85;font-size:1rem;margin:0;">Tim co phieu theo P/E, P/B, ROE, ty suat co tuc... dua tren du lieu bao cao tai chinh da dong bo</p>
            </div>
            <div class="col-md-4 text-md-right" style="position:relative;z-index:2;">
                <a href="{{ url('/') }}" style="background:rgba(255,255,255,0.2);color:white;border:1px solid rgba(255,255,255,0.35);padding:0.75rem 1.5rem;border-radius:10px;text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:8px;">
                    <i class="bi bi-house"></i> Trang chu
                </a>
            </div>
        </div>
    </div>
</section>

<div class="container" style="padding-bottom:3rem;">
    <form method="GET" action="{{ route('stock.screener') }}" class="screener-filter-panel">
        <div class="form-row">
            <div class="col-6 col-md-2 form-group">
                <label>P/E tu</label>
                <input type="number" step="0.1" name="pe_min" value="{{ $filters['pe_min'] ?? '' }}" class="form-control" placeholder="0">
            </div>
            <div class="col-6 col-md-2 form-group">
                <label>P/E den</label>
                <input type="number" step="0.1" name="pe_max" value="{{ $filters['pe_max'] ?? '' }}" class="form-control" placeholder="20">
            </div>
            <div class="col-6 col-md-2 form-group">
                <label>P/B tu</label>
                <input type="number" step="0.1" name="pb_min" value="{{ $filters['pb_min'] ?? '' }}" class="form-control" placeholder="0">
            </div>
            <div class="col-6 col-md-2 form-group">
                <label>P/B den</label>
                <input type="number" step="0.1" name="pb_max" value="{{ $filters['pb_max'] ?? '' }}" class="form-control" placeholder="3">
            </div>
            <div class="col-6 col-md-2 form-group">
                <label>ROE tu (%)</label>
                <input type="number" step="0.1" name="roe_min" value="{{ $filters['roe_min'] ?? '' }}" class="form-control" placeholder="15">
            </div>
            <div class="col-6 col-md-2 form-group">
                <label>Co tuc tu (%)</label>
                <input type="number" step="0.1" name="dividend_yield_min" value="{{ $filters['dividend_yield_min'] ?? '' }}" class="form-control" placeholder="5">
            </div>
        </div>
        <div class="form-row align-items-end">
            <div class="col-6 col-md-3 form-group mb-0">
                <label>No/VCSH toi da</label>
                <input type="number" step="0.1" name="debt_equity_max" value="{{ $filters['debt_equity_max'] ?? '' }}" class="form-control" placeholder="1.5">
            </div>
            <div class="col-12 col-md-9 form-group mb-0 d-flex" style="gap:0.5rem;">
                <button type="submit" style="background:linear-gradient(135deg,#2563eb,#1d4ed8);color:white;border:none;border-radius:10px;padding:0.6rem 1.5rem;font-weight:700;">
                    <i class="bi bi-search"></i> Loc
                </button>
                <a href="{{ route('stock.screener') }}" style="background:#f1f5f9;color:#6b7280;border-radius:10px;padding:0.6rem 1.5rem;font-weight:700;text-decoration:none;">
                    Xoa loc
                </a>
            </div>
        </div>
    </form>

    @php
        $sortLink = function (string $key) use ($filters) {
            $dir = (($filters['sort'] ?? '') === $key && ($filters['dir'] ?? 'asc') === 'asc') ? 'desc' : 'asc';
            return route('stock.screener', array_merge($filters, ['sort' => $key, 'dir' => $dir]));
        };
    @endphp

    <div class="screener-table">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Ma CP</th>
                        <th><a href="{{ $sortLink('pe') }}">P/E</a></th>
                        <th><a href="{{ $sortLink('pb') }}">P/B</a></th>
                        <th><a href="{{ $sortLink('eps') }}">EPS</a></th>
                        <th><a href="{{ $sortLink('roe') }}">ROE (%)</a></th>
                        <th><a href="{{ $sortLink('roa') }}">ROA (%)</a></th>
                        <th><a href="{{ $sortLink('dividend_yield') }}">Co tuc (%)</a></th>
                        <th><a href="{{ $sortLink('debt_equity') }}">No/VCSH</a></th>
                        <th>Cap nhat</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($results as $row)
                    <tr>
                        <td><a class="symbol-badge" href="{{ url('/stock?symbol=' . $row['symbol']) }}">{{ $row['symbol'] }}</a></td>
                        <td>{{ isset($row['pe']) ? number_format($row['pe'], 2) : '—' }}</td>
                        <td>{{ isset($row['pb']) ? number_format($row['pb'], 2) : '—' }}</td>
                        <td>{{ isset($row['eps']) ? number_format($row['eps'], 0) : '—' }}</td>
                        <td>{{ isset($row['roe']) ? number_format($row['roe'], 2) : '—' }}</td>
                        <td>{{ isset($row['roa']) ? number_format($row['roa'], 2) : '—' }}</td>
                        <td>{{ isset($row['dividend_yield']) ? number_format($row['dividend_yield'], 2) : '—' }}</td>
                        <td>{{ isset($row['debt_equity']) ? number_format($row['debt_equity'], 2) : '—' }}</td>
                        <td style="color:#9ca3af;font-size:0.8rem;">{{ $row['synced_at'] ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-5" style="color:#9ca3af;">
                            <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:0.5rem;"></i>
                            Khong tim thay ma co phieu nao khop bo loc.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p style="color:#9ca3af;font-size:0.8rem;margin-top:0.75rem;">
        * Chi tinh tren cac ma da co du lieu bao cao tai chinh duoc dong bo (xem <a href="{{ route('admin.sync-status') }}">Sync Status</a> neu ban la admin). Danh sach se day them theo thoi gian khi <code>sync:company-financials</code> chay dinh ky.
    </p>
</div>
@endsection

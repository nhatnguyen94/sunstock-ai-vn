@extends('layouts.app')

@section('head')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/awesomplete/1.1.5/awesomplete.min.css" />
@vite('resources/frontend/css/stock/compare.css')
@endsection

@section('content')
<section class="compare-header">
    <div class="container">
        <div class="row align-items-center" style="position:relative;z-index:1;padding-bottom:2rem;">
            <div class="col-md-8">
                <div style="display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.25);border-radius:30px;padding:5px 16px;margin-bottom:1rem;font-size:0.8rem;font-weight:600;">
                    <i class="bi bi-bar-chart-steps"></i>
                    So sanh hieu suat tuong doi (%)
                </div>
                <h1 style="font-size:2.4rem;font-weight:800;margin-bottom:0.5rem;color:white;line-height:1.2;">So Sanh Co Phieu</h1>
                <p style="opacity:0.85;font-size:1rem;margin:0;">Toi da 4 ma · Bieu do tang truong % · Du lieu lich su</p>
            </div>
            <div class="col-md-4 text-md-right" style="position:relative;z-index:2;">
                <a href="{{ url('/') }}" style="background:rgba(255,255,255,0.2);color:white;border:1px solid rgba(255,255,255,0.35);padding:0.75rem 1.5rem;border-radius:10px;text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:8px;transition:all 0.3s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                    <i class="bi bi-house"></i> Trang chu
                </a>
            </div>
        </div>
    </div>
</section>

<div class="container" style="padding-top:2rem;padding-bottom:3rem;">
    <div class="input-panel" data-aos="fade-up">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3" style="gap:0.75rem;">
            <div>
                <h4 style="font-weight:700;color:#1f2937;margin:0;font-size:1.1rem;">
                    <i class="bi bi-plus-circle" style="color:#2563eb;margin-right:6px;"></i>
                    Them ma co phieu de so sanh
                </h4>
                <p style="color:#9ca3af;font-size:0.82rem;margin:4px 0 0;">Toi da 4 ma · Nhap ma hoac chon goi y</p>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                @foreach(['VCB','FPT','VNM','HPG','TCB','VIC'] as $s)
                <button onclick="addSymbolDirect('{{ $s }}')" style="background:#eff6ff;color:#2563eb;border:1px solid rgba(37,99,235,0.2);border-radius:20px;padding:5px 14px;font-size:0.8rem;font-weight:700;cursor:pointer;transition:all 0.2s;" onmouseover="this.style.background='#2563eb';this.style.color='white'" onmouseout="this.style.background='#eff6ff';this.style.color='#2563eb'">{{ $s }}</button>
                @endforeach
            </div>
        </div>
        <div class="d-flex" style="gap:0.5rem;max-width:500px;">
            <div style="flex:1;position:relative;">
                <input type="text" id="symbolInput" class="search-input-comp" placeholder="VD: VCB, FPT, VNM..." autocomplete="off">
            </div>
            <button onclick="addSymbol()" style="background:linear-gradient(135deg,#2563eb,#1d4ed8);color:white;border:none;border-radius:12px;padding:0.875rem 1.5rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;white-space:nowrap;transition:all 0.3s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
                <i class="bi bi-plus-lg"></i> Them
            </button>
        </div>
        <div id="selectedSymbols" class="mt-3"></div>
    </div>

    <div id="loadingState" class="text-center py-5" style="display:none;">
        <div class="loading-dots" style="margin-bottom:1rem;"><span></span><span></span><span></span></div>
        <p style="color:#6b7280;font-weight:500;">Dang tai du lieu so sanh...</p>
    </div>

    <div id="emptyState" class="empty-state" data-aos="fade-up">
        <i class="bi bi-bar-chart-steps" style="font-size:4rem;color:#d1d5db;display:block;margin-bottom:1rem;"></i>
        <h4 style="color:#6b7280;font-weight:700;margin-bottom:0.5rem;">Them ma co phieu de bat dau</h4>
        <p style="color:#9ca3af;font-size:0.9rem;">Nhap toi da 4 ma co phieu phia tren de so sanh hieu suat</p>
    </div>

    <div id="compareContent" style="display:none;">
        <div id="stockCards" class="row mb-4"></div>

        <div class="chart-wrap" data-aos="fade-up">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:10px;">
                <div>
                    <h4 style="font-weight:700;color:#1f2937;margin:0;font-size:1.1rem;">
                        <i class="bi bi-graph-up" style="color:#2563eb;margin-right:6px;"></i>
                        Bieu do tang truong so sanh (%)
                    </h4>
                    <p style="color:#9ca3af;font-size:0.78rem;margin:3px 0 0;">Tang truong % so voi ngay dau tien trong ky du lieu</p>
                </div>
                <div style="display:flex;gap:6px;" id="periodBtns2">
                    <button class="period-btn2 active" data-months="1">1T</button>
                    <button class="period-btn2" data-months="3">3T</button>
                    <button class="period-btn2" data-months="6">6T</button>
                    <button class="period-btn2" data-months="0">Tat ca</button>
                </div>
            </div>
            <div id="compareChart"></div>
        </div>

        <div class="stats-table" data-aos="fade-up">
            <div style="padding:1.25rem 1.5rem;background:linear-gradient(135deg,#eff6ff,#f8fafc);border-bottom:1px solid #e5e7eb;">
                <h5 style="font-weight:700;color:#1f2937;margin:0;display:flex;align-items:center;gap:8px;">
                    <i class="bi bi-table" style="color:#2563eb;"></i>
                    Thong ke tong hop
                </h5>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="text-align:left;padding-left:1.5rem;">Ma CP</th>
                            <th>Ten cong ty</th>
                            <th>Gia hien tai</th>
                            <th>Tang truong</th>
                            <th>Cao nhat</th>
                            <th>Thap nhat</th>
                            <th>Chenh lech</th>
                        </tr>
                    </thead>
                    <tbody id="statsTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.0/dist/apexcharts.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/awesomplete/1.1.5/awesomplete.min.js"></script>

<script>
let symbols = '{{ $symbols }}'.split(',').filter(s => s.trim() !== '');
</script>
@vite('resources/frontend/js/stock/compare.js')
@endsection
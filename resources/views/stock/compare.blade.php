@extends('layouts.app')

@section('head')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/awesomplete/1.1.5/awesomplete.min.css" />
<style>
.compare-header {
    background: linear-gradient(145deg, #1e3a8a 0%, #2563eb 45%, #0891b2 100%);
    color: white;
    padding: 3rem 0 0;
    position: relative;
    overflow: hidden;
}
.compare-header::before {
    content: '';
    position: absolute;
    inset: 0;
    background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Cdefs%3E%3Cpattern id='g' width='10' height='10' patternUnits='userSpaceOnUse'%3E%3Cpath d='M 10 0 L 0 0 0 10' fill='none' stroke='rgba(255,255,255,0.06)' stroke-width='0.5'/%3E%3C/pattern%3E%3C/defs%3E%3Crect width='100' height='100' fill='url(%23g)'/%3E%3C/svg%3E");
    opacity: 1;
}

.input-panel {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 8px 32px rgba(37,99,235,0.12);
    border: 1px solid #e5e7eb;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}
.input-panel::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, #2563eb, #7c3aed, #10b981, #f59e0b);
}
.symbol-tag {
    display: inline-flex;
    align-items: center;
    background: white;
    border: 2px solid;
    padding: 6px 14px;
    border-radius: 30px;
    margin: 4px;
    font-weight: 700;
    font-size: 0.9rem;
    transition: all 0.2s;
    animation: tagIn 0.3s ease;
}
.symbol-tag:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
@keyframes tagIn { from { opacity: 0; transform: scale(0.8); } to { opacity: 1; transform: scale(1); } }
.remove-btn { margin-left: 8px; cursor: pointer; font-size: 1.1rem; opacity: 0.7; transition: opacity 0.2s; }
.remove-btn:hover { opacity: 1; }

.stock-compare-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    border: 2px solid;
    position: relative;
    overflow: hidden;
    transition: all 0.3s;
    height: 100%;
}
.stock-compare-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; height: 4px;
    background: currentColor;
    opacity: 0.8;
}
.stock-compare-card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,0.12); }

.chart-wrap {
    background: white;
    border-radius: 20px;
    padding: 1.5rem 1.5rem 0.5rem;
    box-shadow: 0 8px 32px rgba(37,99,235,0.08);
    border: 1px solid #e5e7eb;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}
.chart-wrap::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, #2563eb, #10b981, #f59e0b, #ef4444);
}

.stats-table {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    overflow: hidden;
    border: 1px solid #e5e7eb;
}
.stats-table table { margin: 0; width: 100%; }
.stats-table thead th {
    background: linear-gradient(135deg, #eff6ff, #f1f5f9);
    border: none;
    font-weight: 700;
    color: #374151;
    padding: 1.25rem 1rem;
    text-align: center;
}
.stats-table tbody td {
    border: none;
    padding: 1rem;
    text-align: center;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}
.stats-table tbody tr:last-child td { border-bottom: none; }
.stats-table tbody tr:hover { background: #f8fafc; }

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: #9ca3af;
}

.loading-dots span {
    display: inline-block;
    width: 8px; height: 8px;
    border-radius: 50%;
    background: #2563eb;
    margin: 0 3px;
    animation: bounce 1.2s ease-in-out infinite;
}
.loading-dots span:nth-child(2) { animation-delay: 0.2s; }
.loading-dots span:nth-child(3) { animation-delay: 0.4s; }
@keyframes bounce { 0%, 80%, 100% { transform: scale(0); } 40% { transform: scale(1); } }

.search-input-comp {
    padding: 0.875rem 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 1rem;
    outline: none;
    transition: all 0.3s;
    width: 100%;
}
.search-input-comp:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }

.period-btn2 {
    padding: 5px 14px;
    border: 1px solid #e5e7eb;
    background: white;
    color: #6b7280;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.period-btn2:hover, .period-btn2.active {
    background: #2563eb;
    color: white;
    border-color: #2563eb;
}
</style>
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
const COLORS = ['#2563eb', '#10b981', '#f59e0b', '#ef4444'];

let symbols = '{{ $symbols }}'.split(',').filter(s => s.trim() !== '');
let chartInstance = null;
let allChartData = [];
let activeMonths = 1;

const awesomplete = new Awesomplete(document.getElementById('symbolInput'), {
    minChars: 1, maxItems: 10, autoFirst: true, list: []
});
let searchTimeout;
document.getElementById('symbolInput').addEventListener('input', function() {
    const val = this.value.trim();
    if (val.length < 1) return;
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        fetch('/stocks-list?q=' + encodeURIComponent(val))
            .then(r => r.json())
            .then(data => { awesomplete.list = data.map(item => item.symbol); });
    }, 250);
});
document.getElementById('symbolInput').addEventListener('awesomplete-selectcomplete', function() { addSymbol(); });
document.getElementById('symbolInput').addEventListener('keypress', function(e) { if (e.key === 'Enter') { e.preventDefault(); addSymbol(); } });

function addSymbolDirect(sym) { document.getElementById('symbolInput').value = sym; addSymbol(); }

function renderTags() {
    const container = document.getElementById('selectedSymbols');
    if (symbols.length === 0) {
        container.innerHTML = '';
        document.getElementById('emptyState').style.display = 'block';
        document.getElementById('compareContent').style.display = 'none';
        return;
    }
    container.innerHTML = '<div style="display:flex;flex-wrap:wrap;align-items:center;gap:4px;">' +
        symbols.map((sym, idx) => `
            <div class="symbol-tag" style="border-color:${COLORS[idx]};color:${COLORS[idx]}">
                <span style="width:8px;height:8px;border-radius:50%;background:${COLORS[idx]};display:inline-block;margin-right:4px;"></span>
                ${sym}
                <i class="bi bi-x-circle-fill remove-btn" onclick="removeSymbol('${sym}')"></i>
            </div>`).join('') +
        `<span style="color:#9ca3af;font-size:0.78rem;margin-left:6px;">${symbols.length}/4 ma</span>
    </div>`;
    document.getElementById('emptyState').style.display = 'none';
    fetchCompareData();
}

function addSymbol() {
    const input = document.getElementById('symbolInput');
    const val = input.value.trim().toUpperCase();
    if (!val) return;
    if (symbols.includes(val)) { input.value = ''; return; }
    if (symbols.length >= 4) {
        if (typeof showToast === 'function') showToast('Chi so sanh toi da 4 ma cung luc!', 'warning');
        return;
    }
    symbols.push(val);
    input.value = '';
    updateURL();
    renderTags();
}

function removeSymbol(sym) {
    symbols = symbols.filter(s => s !== sym);
    updateURL();
    renderTags();
}

function updateURL() {
    const url = new URL(window.location);
    url.searchParams.set('symbols', symbols.join(','));
    window.history.pushState({}, '', url);
}

async function fetchCompareData() {
    document.getElementById('loadingState').style.display = 'block';
    document.getElementById('compareContent').style.display = 'none';
    try {
        const res = await fetch(`/stock/compare-data?symbols=${symbols.join(',')}`);
        const data = await res.json();
        allChartData = data;
        renderCards(data);
        renderChart(data, activeMonths);
        renderTable(data);
        document.getElementById('loadingState').style.display = 'none';
        document.getElementById('compareContent').style.display = 'block';
    } catch (e) {
        document.getElementById('loadingState').style.display = 'none';
        const el = document.getElementById('emptyState');
        el.style.display = 'block';
        el.innerHTML = `<i class="bi bi-exclamation-circle" style="font-size:3rem;color:#ef4444;display:block;margin-bottom:1rem;"></i><h4 style="color:#6b7280;font-weight:700;">Loi tai du lieu</h4><p style="color:#9ca3af;">Vui long thu lai sau</p>`;
    }
}

function renderCards(data) {
    const container = document.getElementById('stockCards');
    const emojis = ['📈','📊','💹','📉'];
    container.innerHTML = data.map((stock, idx) => {
        const color = COLORS[idx];
        const isPos = stock.change_percent >= 0;
        return `<div class="col-6 col-md-3 mb-3">
            <div class="stock-compare-card" style="border-color:${color}40;color:${color};">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
                    <span style="background:${color};color:white;padding:4px 12px;border-radius:8px;font-weight:800;font-size:0.9rem;">${stock.symbol}</span>
                    <span style="font-size:1.3rem;">${emojis[idx]}</span>
                </div>
                <div style="font-size:0.78rem;color:#6b7280;font-weight:500;margin-bottom:0.5rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${stock.name || stock.symbol}</div>
                <div style="font-size:1.5rem;font-weight:800;color:#1f2937;">${Number(stock.latest_close).toLocaleString('vi-VN')}<span style="font-size:0.7rem;color:#9ca3af;margin-left:2px;">d</span></div>
                <div style="display:flex;align-items:center;gap:4px;margin-top:4px;font-weight:700;font-size:0.9rem;color:${isPos ? '#10b981' : '#ef4444'};">
                    <i class="bi bi-${isPos ? 'arrow-up-circle-fill' : 'arrow-down-circle-fill'}"></i>
                    ${isPos ? '+' : ''}${stock.change_percent}%
                </div>
                <div style="margin-top:0.75rem;display:flex;justify-content:space-between;font-size:0.75rem;color:#9ca3af;">
                    <span>Cao: <b style="color:#10b981;">${Number(stock.high).toLocaleString('vi-VN')}</b></span>
                    <span>Thap: <b style="color:#ef4444;">${Number(stock.low).toLocaleString('vi-VN')}</b></span>
                </div>
                <div style="height:4px;background:#f3f4f6;border-radius:4px;margin-top:0.75rem;overflow:hidden;">
                    <div style="height:100%;width:${Math.min(100, Math.max(5, 50 + stock.change_percent * 2))}%;background:${color};border-radius:4px;transition:width 0.8s ease;"></div>
                </div>
            </div>
        </div>`;
    }).join('');
}

function filterData(data, months) {
    if (!months) return data;
    const cutoff = new Date();
    cutoff.setMonth(cutoff.getMonth() - months);
    return data.map(stock => ({
        ...stock,
        prices: stock.prices.filter(p => new Date(p.time) >= cutoff)
    }));
}

function renderChart(data, months) {
    const filtered = filterData(data, months);
    const series = filtered.map((stock, idx) => ({
        name: stock.symbol,
        data: (stock.prices || []).map(p => [new Date(p.time).getTime(), parseFloat(p.percent || 0)])
    }));

    const options = {
        series,
        chart: {
            type: 'line',
            height: 420,
            toolbar: { show: true },
            animations: { enabled: true, easing: 'easeinout', speed: 700 },
            fontFamily: 'Inter, sans-serif',
        },
        stroke: { curve: 'smooth', width: 2.5 },
        colors: COLORS.slice(0, data.length),
        xaxis: {
            type: 'datetime',
            labels: { datetimeFormatter: { month: 'MM/yyyy', day: 'dd/MM' }, style: { fontSize: '11px', colors: '#6b7280' } },
            axisBorder: { show: false }, axisTicks: { show: false }
        },
        yaxis: {
            labels: {
                formatter: v => (v >= 0 ? '+' : '') + v.toFixed(1) + '%',
                style: { fontSize: '11px', colors: '#6b7280' }
            }
        },
        tooltip: {
            shared: true, intersect: false, theme: 'light',
            x: { format: 'dd/MM/yyyy' },
            y: { formatter: v => (v >= 0 ? '+' : '') + v.toFixed(2) + '%' }
        },
        grid: { borderColor: '#f3f4f6', strokeDashArray: 4 },
        legend: {
            position: 'top', horizontalAlign: 'left',
            labels: { colors: '#374151' },
            markers: { width: 12, height: 12, radius: 4 }
        },
        annotations: {
            yaxis: [{ y: 0, borderColor: '#d1d5db', borderWidth: 1, strokeDashArray: 4, label: { text: '0%', style: { color: '#9ca3af', fontSize: '10px' } } }]
        },
        dataLabels: { enabled: false },
        markers: { size: 0, hover: { size: 5 } }
    };

    if (chartInstance) { chartInstance.destroy(); }
    chartInstance = new ApexCharts(document.getElementById('compareChart'), options);
    chartInstance.render();
}

function renderTable(data) {
    const tbody = document.getElementById('statsTableBody');
    tbody.innerHTML = data.map((stock, idx) => {
        const color = COLORS[idx];
        const isPos = stock.change_percent >= 0;
        const high = Number(stock.high), low = Number(stock.low);
        const spread = high - low;
        const spreadPct = low > 0 ? ((spread / low) * 100).toFixed(1) : '—';
        return `<tr>
            <td style="text-align:left;padding-left:1.5rem;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <span style="width:12px;height:12px;border-radius:3px;background:${color};display:inline-block;flex-shrink:0;"></span>
                    <strong style="color:${color};font-size:1rem;">${stock.symbol}</strong>
                </div>
            </td>
            <td style="text-align:left;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${stock.name || '—'}</td>
            <td><strong>${Number(stock.latest_close).toLocaleString('vi-VN')} d</strong></td>
            <td>
                <span style="color:${isPos ? '#10b981' : '#ef4444'};font-weight:700;background:${isPos ? 'rgba(16,185,129,0.08)' : 'rgba(239,68,68,0.08)'};padding:3px 10px;border-radius:20px;">
                    <i class="bi bi-${isPos ? 'arrow-up' : 'arrow-down'}"></i>
                    ${isPos ? '+' : ''}${stock.change_percent}%
                </span>
            </td>
            <td style="color:#10b981;font-weight:600;">${Number(stock.high).toLocaleString('vi-VN')} d</td>
            <td style="color:#ef4444;font-weight:600;">${Number(stock.low).toLocaleString('vi-VN')} d</td>
            <td style="color:#6b7280;font-size:0.85rem;">${Number(spread).toLocaleString('vi-VN')} d <span style="color:#9ca3af;">(${spreadPct}%)</span></td>
        </tr>`;
    }).join('');
}

document.querySelectorAll('.period-btn2').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.period-btn2').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        activeMonths = parseInt(this.dataset.months) || 0;
        if (allChartData.length > 0) renderChart(allChartData, activeMonths);
    });
});

if (symbols.length > 0) { renderTags(); } else { document.getElementById('emptyState').style.display = 'block'; }
</script>
@endsection

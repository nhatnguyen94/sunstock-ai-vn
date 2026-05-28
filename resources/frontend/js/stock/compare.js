const COLORS = ['#2563eb', '#10b981', '#f59e0b', '#ef4444'];

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

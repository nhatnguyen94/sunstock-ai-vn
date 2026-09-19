import { LineSeries, LineStyle, attachLegend, cleanSeries, fmtDec, makeChart, rebase, sliceByDays, toDay } from '../shared/charts.js';
import { stockAutocomplete } from '../shared/autocomplete.js';

const COLORS = ['#2563eb', '#10b981', '#f59e0b', '#ef4444'];

let chartInstance = null;
let allChartData = [];
let activeMonths = 1;

const symbolInput = document.getElementById('symbolInput');
stockAutocomplete(symbolInput, { maxItems: 8, onPick: () => addSymbol() });
symbolInput.addEventListener('keypress', function(e) { if (e.key === 'Enter') { e.preventDefault(); addSymbol(); } });

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

/**
 * Percent series for one stock inside the window, re-based so its first day is 0%.
 * The API's `percent` is measured from each stock's OWN first day, so raw values from stocks listed on
 * different dates are not comparable; `from` (the latest first-day across the selection) puts every
 * stock on the same starting line. Slicing a window without re-basing would also not start at zero.
 */
function windowSeries(stock, months, from) {
    let pts = cleanSeries((stock.prices || []).map((p) => ({ time: toDay(p.time), value: 100 + parseFloat(p.percent || 0) })));
    if (months) pts = sliceByDays(pts, Math.round(months * 30.5));
    if (from) pts = pts.filter((p) => p.time >= from);
    return rebase(pts, 100).map((p) => ({ time: p.time, value: +(p.value - 100).toFixed(2) }));
}

const pct = (v) => (v >= 0 ? '+' : '') + fmtDec(v) + '%';

let compareSeries = [];

function renderChart(data, months) {
    const el = document.getElementById('compareChart');
    if (!chartInstance) {
        el.style.height = '420px';
        chartInstance = makeChart(el, { rightPriceScale: { borderVisible: false, scaleMargins: { top: 0.1, bottom: 0.1 } } });
        attachLegend(chartInstance, el, (param) => {
            const parts = compareSeries.map(({ symbol, color, series }) => {
                const d = param.seriesData.get(series);
                return d && d.value !== undefined
                    ? `<span style="color:${color}">${symbol} <b>${pct(d.value)}</b></span>` : '';
            }).join('');
            return `<span class="lwc-date">${param.time.split('-').reverse().join('/')}</span>${parts}`;
        });
    }

    compareSeries.forEach(({ series }) => chartInstance.removeSeries(series));

    // Common start = the latest "first available day" among the selected stocks (or the window start, if later).
    const firsts = data.map((stock) => windowSeries(stock, months, null)[0]?.time).filter(Boolean).sort();
    const from = firsts.length ? firsts[firsts.length - 1] : null;

    compareSeries = data.map((stock, idx) => {
        const series = chartInstance.addSeries(LineSeries, {
            color: COLORS[idx], lineWidth: 2.5, priceLineVisible: false,
            priceFormat: { type: 'custom', formatter: pct, minMove: 0.01 },
        });
        series.setData(windowSeries(stock, months, from));
        return { symbol: stock.symbol, color: COLORS[idx], series };
    });

    // A dashed zero line makes "above/below where I started" readable at a glance.
    if (compareSeries.length) {
        compareSeries[0].series.createPriceLine({ price: 0, color: '#9ca3af', lineWidth: 1, lineStyle: LineStyle.Dashed, axisLabelVisible: false });
    }
    requestAnimationFrame(() => chartInstance.timeScale().fitContent());
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

// Inline onclick="..." handlers in the rendered markup need these on window (this file is an ES module).
Object.assign(window, { addSymbolDirect, addSymbol, removeSymbol });

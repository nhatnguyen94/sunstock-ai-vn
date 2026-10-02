// Stock page: TradingView Lightweight Charts price chart (candles / area + volume + indicators),
// price history table, financial statements and symbol search.
import {
    AreaSeries, CandlestickSeries, HistogramSeries, LineSeries, LineStyle,
    COLORS, DEC_FORMAT, VND_FORMAT, attachLegend, cleanSeries, fmtDec, fmtInt, makeChart, toDay,
} from '../shared/charts.js';
import { countUp, direction, flash } from '../shared/pricefx.js';
import { PAGE_SIZE, buildRows, pageHtml, pageWindow } from './pricetable.js';
import { stockAutocomplete } from '../shared/autocomplete.js';
import { bollinger, macd, rsi, sma } from '../shared/indicators.js';

window.searchSymbol = function (symbol) {
    document.getElementById('symbol').value = symbol;
    document.querySelector('.search-section form').submit();
};

// The feed quotes stocks in thousands of VND: the page shows whole VND (scale 1000), an index stays in points (scale 1)
const UNIT = (typeof stockUnit !== 'undefined') ? stockUnit : { scale: 1000, decimals: 0, unit: '₫' };
const PRICE_FMT = UNIT.scale > 1 ? VND_FORMAT : DEC_FORMAT;
const fmtP = UNIT.scale > 1 ? fmtInt : fmtDec;

if (typeof rawData !== 'undefined' && rawData.length > 0) {
    initPriceChart();
    initPriceTable();
}
initQuoteFx();

// ─── QUOTE HEADER: the price counts up from the previous close and flashes once in the move's colour ───
function initQuoteFx() {
    const price = document.getElementById('sqPrice');
    if (!price) return;
    const from = Number(price.dataset.from);
    const to = Number(price.dataset.to);
    countUp(price, from, to, { decimals: Number(price.dataset.decimals) || 0 });
    flash(price, direction(from, to));
}

function initPriceChart() {
    const bars = cleanSeries(rawData.map((d) => ({
        time: toDay(d.time),
        open: parseFloat(d.open) * UNIT.scale, high: parseFloat(d.high) * UNIT.scale, low: parseFloat(d.low) * UNIT.scale, close: parseFloat(d.close) * UNIT.scale,
        value: parseFloat(d.close) * UNIT.scale,
        volume: parseFloat(d.volume) || 0,
    })));
    if (!bars.length) return;

    const times = bars.map((b) => b.time);
    const closes = bars.map((b) => b.close);
    const indexByTime = new Map(times.map((t, i) => [t, i]));
    const el = document.getElementById('priceChart');
    const BASE_HEIGHT = 480, SUB_HEIGHT = 170;

    el.querySelector('.sk-chart')?.remove();   // the placeholder shown while the chart library starts
    const chart = makeChart(el);

    // Candles (default) and area (line mode) share one chart so indicators/volume/legend work in both.
    const candle = chart.addSeries(CandlestickSeries, {
        upColor: COLORS.up, downColor: COLORS.down, borderVisible: false,
        wickUpColor: COLORS.up, wickDownColor: COLORS.down, priceFormat: PRICE_FMT,
    });
    candle.setData(bars.map(({ time, open, high, low, close }) => ({ time, open, high, low, close })));

    const area = chart.addSeries(AreaSeries, {
        lineColor: COLORS.blue, topColor: 'rgba(37,99,235,0.35)', bottomColor: 'rgba(37,99,235,0)',
        lineWidth: 2, visible: false, priceFormat: PRICE_FMT,
    });
    area.setData(bars.map(({ time, close }) => ({ time, value: close })));

    // Volume as a translucent histogram pinned to the bottom 20% of the main pane.
    const volume = chart.addSeries(HistogramSeries, {
        priceScaleId: 'volume', priceFormat: { type: 'volume' },
        lastValueVisible: false, priceLineVisible: false,
    });
    chart.priceScale('volume').applyOptions({ scaleMargins: { top: 0.82, bottom: 0 } });
    volume.setData(bars.map((b) => ({
        time: b.time, value: b.volume,
        color: b.close >= b.open ? 'rgba(16,185,129,0.35)' : 'rgba(239,68,68,0.35)',
    })));

    // ── Legend (OHLC + change + volume + active indicator values under the crosshair) ──
    const active = {};   // name -> { color, series: [..primary series for the legend value] }
    const num = (v) => (v === undefined || v === null ? '—' : fmtP(v));
    const vol = (v) => (v === undefined || v === null ? '—' : fmtInt(v));

    function legendFor(time, seriesData) {
        const i = indexByTime.get(time);
        if (i === undefined) return null;
        const b = bars[i];
        const prev = i > 0 ? bars[i - 1].close : null;
        const chg = prev ? ((b.close / prev - 1) * 100) : null;
        const cls = chg === null ? '' : chg >= 0 ? 'up' : 'down';
        const date = time.split('-').reverse().join('/');

        let html = `<span class="lwc-date">${date}</span>` +
            `<span>Mở <b>${num(b.open)}</b></span><span>Cao <b>${num(b.high)}</b></span>` +
            `<span>Thấp <b>${num(b.low)}</b></span><span>Đóng <b class="${cls}">${num(b.close)}</b></span>` +
            (chg === null ? '' : `<span class="${cls}">${chg >= 0 ? '+' : ''}${fmtDec(chg)}%</span>`) +
            `<span>KL <b>${vol(b.volume)}</b></span>`;

        Object.entries(active).forEach(([name, ind]) => {
            const d = seriesData ? seriesData.get(ind.series[0]) : ind.lastValue?.(i);
            const v = d && (d.value ?? undefined);
            if (v !== undefined) html += `<span style="color:${ind.color}">${name} <b>${OVERLAYS[name] ? fmtP(v) : fmtDec(v)}</b></span>`;
        });
        return html;
    }

    const legend = attachLegend(chart, el, (param) => legendFor(param.time, param.seriesData), legendFor(times[times.length - 1], null));
    const refreshLegend = () => legend.setInitial(legendFor(times[times.length - 1], null));

    // ── Overlay indicators (MA / Bollinger) ─────────────────────────────────
    const toLine = (values) => values.map((v, i) => (v === null ? null : { time: times[i], value: v })).filter(Boolean);
    const overlayLine = (color, width = 1.5, extra = {}) => chart.addSeries(LineSeries, {
        color, lineWidth: width, priceLineVisible: false, lastValueVisible: false,
        crosshairMarkerVisible: false, priceFormat: PRICE_FMT, ...extra,
    });

    const OVERLAYS = {
        MA20: () => { const s = overlayLine('#f59e0b'); s.setData(toLine(sma(closes, 20))); return { color: '#f59e0b', series: [s] }; },
        MA50: () => { const s = overlayLine('#3b82f6'); s.setData(toLine(sma(closes, 50))); return { color: '#3b82f6', series: [s] }; },
        MA200: () => { const s = overlayLine('#ec4899'); s.setData(toLine(sma(closes, 200))); return { color: '#ec4899', series: [s] }; },
        BB: () => {
            const b = bollinger(closes, 20, 2);
            const up = overlayLine('#8b5cf6', 1), mid = overlayLine('rgba(139,92,246,0.5)', 1, { lineStyle: LineStyle.Dashed }), lo = overlayLine('#8b5cf6', 1);
            up.setData(toLine(b.upper)); mid.setData(toLine(b.mid)); lo.setData(toLine(b.lower));
            return { color: '#8b5cf6', series: [mid, up, lo] };
        },
    };

    // ── Sub-indicators live in extra panes of the SAME chart: shared time axis + crosshair ──
    const subPanes = [];   // names in pane order: 'RSI' | 'MACD'

    function relayout() {
        el.style.height = (BASE_HEIGHT + SUB_HEIGHT * subPanes.length) + 'px';
        const panes = chart.panes();
        panes.forEach((p, idx) => p.setStretchFactor(idx === 0 ? 3 : 1));
    }

    const SUB = {
        RSI: (pane) => {
            const s = chart.addSeries(LineSeries, {
                color: '#06b6d4', lineWidth: 2, priceLineVisible: false,
                priceFormat: DEC_FORMAT,
                autoscaleInfoProvider: () => ({ priceRange: { minValue: 0, maxValue: 100 } }),
            }, pane);
            s.setData(toLine(rsi(closes, 14)));
            s.createPriceLine({ price: 70, color: COLORS.down, lineWidth: 1, lineStyle: LineStyle.Dashed, axisLabelVisible: true, title: 'Quá mua' });
            s.createPriceLine({ price: 30, color: COLORS.up, lineWidth: 1, lineStyle: LineStyle.Dashed, axisLabelVisible: true, title: 'Quá bán' });
            return { color: '#06b6d4', series: [s] };
        },
        MACD: (pane) => {
            const m = macd(closes, 12, 26, 9);
            const hist = chart.addSeries(HistogramSeries, { priceLineVisible: false, lastValueVisible: false, priceFormat: DEC_FORMAT }, pane);
            const line = chart.addSeries(LineSeries, { color: '#10b981', lineWidth: 1.5, priceLineVisible: false, lastValueVisible: false, priceFormat: DEC_FORMAT }, pane);
            const sig = chart.addSeries(LineSeries, { color: '#f59e0b', lineWidth: 1.5, priceLineVisible: false, lastValueVisible: false, priceFormat: DEC_FORMAT }, pane);
            hist.setData(m.hist.map((v, i) => (v === null ? null : { time: times[i], value: v, color: v >= 0 ? 'rgba(16,185,129,0.6)' : 'rgba(239,68,68,0.6)' })).filter(Boolean));
            line.setData(toLine(m.line));
            sig.setData(toLine(m.signal));
            return { color: '#10b981', series: [line, sig, hist] };
        },
    };

    function toggleIndicator(name, on) {
        if (on && !active[name]) {
            if (SUB[name]) {
                subPanes.push(name);
                active[name] = SUB[name](subPanes.length);   // pane 0 is the price pane
                relayout();
            } else {
                active[name] = OVERLAYS[name]();
            }
        } else if (!on && active[name]) {
            active[name].series.forEach((s) => chart.removeSeries(s));   // an emptied pane disappears by itself
            delete active[name];
            const at = subPanes.indexOf(name);
            if (at >= 0) { subPanes.splice(at, 1); relayout(); }
        }
        refreshLegend();
    }

    [['indMA20', 'MA20'], ['indMA50', 'MA50'], ['indMA200', 'MA200'], ['indBB', 'BB'], ['indRSI', 'RSI'], ['indMACD', 'MACD']]
        .forEach(([id, name]) => document.getElementById(id)?.addEventListener('change', (e) => toggleIndicator(name, e.target.checked)));

    // ── Candle / line toggle ────────────────────────────────────────────────
    function setMode(mode) {
        candle.applyOptions({ visible: mode === 'candle' });
        area.applyOptions({ visible: mode === 'line' });
        document.getElementById('btnCandle').classList.toggle('active', mode === 'candle');
        document.getElementById('btnLine').classList.toggle('active', mode === 'line');
    }
    document.getElementById('btnCandle').addEventListener('click', () => setMode('candle'));
    document.getElementById('btnLine').addEventListener('click', () => setMode('line'));

    // ── Period buttons zoom the time axis (all history stays scrollable) ────
    function setPeriod(months) {
        if (!months) { chart.timeScale().fitContent(); return; }
        const last = new Date(times[times.length - 1] + 'T00:00:00Z');
        const from = new Date(last.getTime() - months * 30.5 * 86400000).toISOString().slice(0, 10);
        const first = times.find((t) => t >= from) || times[0];
        chart.timeScale().setVisibleRange({ from: first, to: times[times.length - 1] });
    }
    document.querySelectorAll('.period-btn[data-months]').forEach((btn) => btn.addEventListener('click', function () {
        document.querySelectorAll('.period-btn[data-months]').forEach((b) => b.classList.remove('active'));
        this.classList.add('active');
        setPeriod(parseInt(this.dataset.months, 10) || 0);
    }));

    // ── Save as PNG (replaces the old toolbar's download button) ────────────
    document.getElementById('btnShot')?.addEventListener('click', () => {
        chart.takeScreenshot().toBlob((blob) => {
            if (!blob) return;
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `${typeof stockSymbol !== 'undefined' ? stockSymbol : 'chart'}-${times[times.length - 1]}.png`;
            a.click();
            setTimeout(() => URL.revokeObjectURL(a.href), 1000);
        });
    });

    chart.timeScale().fitContent();
}

// ─── DATA TABLE ─────────────────────────────────────────────────────────────
function initPriceTable() {
    let currentPage = 1;
    const rows = buildRows(rawData, UNIT.scale);   // newest first, with the change against the previous session
    const totalPages = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));
    const body = document.getElementById('priceTableBody');
    const pager = document.getElementById('tablePagination');

    const rangeEl = document.getElementById('pxRange');
    if (rangeEl) rangeEl.textContent = `${rows.length.toLocaleString('vi-VN')} phiên`;

    function renderTable(page) {
        currentPage = Math.min(Math.max(1, page), totalPages);
        body.innerHTML = pageHtml(rows, currentPage, { decimals: UNIT.decimals });
        renderPagination();
    }

    function renderPagination() {
        const item = (label, page, extra = '') => `<li class="page-item ${extra}"><a class="page-link" href="#" data-page="${page}">${label}</a></li>`;
        let html = '';
        if (currentPage > 1) html += item('<i class="bi bi-chevron-left"></i>', currentPage - 1);
        pageWindow(currentPage, totalPages).forEach((p) => {
            html += p === '…' ? '<li class="page-item disabled"><span class="page-link">…</span></li>' : item(p, p, p === currentPage ? 'active' : '');
        });
        if (currentPage < totalPages) html += item('<i class="bi bi-chevron-right"></i>', currentPage + 1);
        pager.innerHTML = html;
    }

    renderTable(1);
    pager.addEventListener('click', (e) => {
        const link = e.target.closest('[data-page]');
        if (!link) return;
        e.preventDefault();
        renderTable(parseInt(link.dataset.page, 10));
        document.getElementById('priceTable')?.scrollIntoView({ block: 'nearest' });
    });
}
// ─── FINANCE SECTION ─────────────────────────────────────────────────────────
(function () {
    const btnLoad = document.getElementById('btnLoadFinance');
    if (!btnLoad) return;

    let finType   = 'income';
    let finPeriod = 'quarter';
    let loaded    = false;

    function formatFinVal(val) {
        if (val === null || val === undefined) return '<span style="color:#d1d5db;">—</span>';
        const num = parseFloat(val);
        if (isNaN(num)) return String(val);
        const abs = Math.abs(num);
        let formatted;
        if (abs >= 1e9)       formatted = (num / 1e9).toFixed(1) + ' tỷ';
        else if (abs >= 1e6)  formatted = (num / 1e6).toFixed(1) + ' tr';
        else if (abs >= 1e3)  formatted = (num / 1e3).toFixed(1) + 'K';
        else                  formatted = num.toLocaleString('vi-VN', { maximumFractionDigits: 2 });
        return `<span style="color:${num < 0 ? '#ef4444' : 'inherit'}">${formatted}</span>`;
    }

    function renderFinanceTable(data, periods) {
        if (!data || !data.length) {
            return '<p style="color:#9ca3af;padding:1.5rem;">Không có dữ liệu.</p>';
        }
        const cols = periods.slice(-8).reverse();
        let html = '<div class="table-responsive"><table class="table data-table"><thead><tr>';
        html += '<th style="min-width:200px;">Chỉ tiêu</th>';
        cols.forEach(c => { html += `<th style="text-align:right;font-size:0.85rem;">${c}</th>`; });
        html += '</tr></thead><tbody>';

        data.forEach(row => {
            const isHeader = row.levels === 1;
            const rowStyle = isHeader ? 'font-weight:700;background:#f8fafc;color:#1e3a5f;' : '';
            const cellStyle = isHeader ? 'font-weight:700;' : 'padding-left:2rem;color:#374151;';
            html += `<tr style="${rowStyle}">`;
            html += `<td style="${cellStyle}">${row.item || ''}</td>`;
            cols.forEach(c => {
                html += `<td style="text-align:right;font-size:0.88rem;">${formatFinVal(row[c])}</td>`;
            });
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        html += `<p style="font-size:0.78rem;color:#9ca3af;margin-top:8px;"><i class="bi bi-info-circle"></i> Đơn vị: triệu VNĐ &nbsp;|&nbsp; Hiển thị ${cols.length} kỳ gần nhất</p>`;
        return html;
    }

    function financeSkeleton() {
        const cell = '<td><i class="sk sk-text is-wide"></i></td>';
        const row = `<tr><td><i class="sk sk-text is-wide is-left"></i></td>${cell.repeat(6)}</tr>`;
        return `<div class="table-responsive" aria-busy="true"><table class="table data-table"><tbody>${row.repeat(9)}</tbody></table></div>`;
    }

    function loadFinance() {
        const symbol = (typeof stockSymbol !== 'undefined') ? stockSymbol : '';
        if (!symbol) return;
        const body = document.getElementById('financeBody');
        // first load: grey placeholder rows; switching tab/period: keep the old table, dimmed, so the page does not jump
        if (body.querySelector('table')) body.classList.add('is-loading-soft');
        else body.innerHTML = financeSkeleton();

        fetch(`/stock/finance?symbol=${encodeURIComponent(symbol)}&type=${finType}&period=${finPeriod}`)
            .then(r => r.json())
            .then(json => {
                body.classList.remove('is-loading-soft');
                if (json.error) {
                    body.innerHTML = `<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> ${json.error}</div>`;
                    return;
                }
                body.innerHTML = renderFinanceTable(json.data || [], json.periods || []);
            })
            .catch(() => {
                body.classList.remove('is-loading-soft');
                body.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle"></i> Lỗi kết nối. Vui lòng thử lại.</div>';
            });
    }

    btnLoad.addEventListener('click', function () {
        if (!loaded) {
            loaded = true;
            document.getElementById('financeTabBar').style.display = '';
            this.closest('div').style.display = 'none';
        }
        loadFinance();
    });

    document.addEventListener('click', function (e) {
        const typeBtn   = e.target.closest('.fin-type-btn');
        const periodBtn = e.target.closest('.fin-period-btn');
        if (typeBtn && document.getElementById('financeTabBar')) {
            finType = typeBtn.dataset.type;
            document.querySelectorAll('.fin-type-btn').forEach(b => b.classList.toggle('active', b === typeBtn));
            loadFinance();
        }
        if (periodBtn && document.getElementById('financeTabBar')) {
            finPeriod = periodBtn.dataset.period;
            document.querySelectorAll('.fin-period-btn').forEach(b => b.classList.toggle('active', b === periodBtn));
            loadFinance();
        }
    });
})();

// Symbol autocomplete
const stockSearchForm = document.querySelector('.search-section form');
stockAutocomplete(document.getElementById('symbol'), {
    maxItems: 8,
    onResults: (data) => document.getElementById('notFoundMsg').classList.toggle('show', Array.isArray(data) && data.length === 0),
    onPick: () => stockSearchForm.requestSubmit(),   // picking a suggestion searches straight away
});
document.getElementById('symbol').addEventListener('focus', () => document.getElementById('notFoundMsg').classList.remove('show'));
document.querySelector('.search-section form').addEventListener('submit', function() {
    const btn = this.querySelector('.search-btn');
    const btnText = btn.querySelector('.btn-text');
    const btnIcon = btn.querySelector('i');
    btn.disabled = true;
    if (btnIcon) btnIcon.className = 'loading-spinner';
    if (btnText) btnText.textContent = 'Đang tìm...';
    setTimeout(() => { btn.disabled = false; if (btnIcon) btnIcon.className = 'bi bi-search'; if (btnText) btnText.textContent = 'Tra cứu'; }, 5000);
});
document.addEventListener('keydown', e => { if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); document.getElementById('symbol').focus(); } });

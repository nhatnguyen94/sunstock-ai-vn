// Stock page: TradingView Lightweight Charts price chart (candles / area + volume + indicators),
// price history table, financial statements and symbol search.
import {
    AreaSeries, CandlestickSeries, HistogramSeries, LineSeries, LineStyle,
    COLORS, DEC_FORMAT, PRICE_FORMAT, attachLegend, cleanSeries, fmtDec, fmtInt, fmtPrice, makeChart, toDay,
} from '../shared/charts.js';
import { stockAutocomplete } from '../shared/autocomplete.js';
import { bollinger, macd, rsi, sma } from '../shared/indicators.js';

window.searchSymbol = function (symbol) {
    document.getElementById('symbol').value = symbol;
    document.querySelector('.search-section form').submit();
};

if (typeof rawData !== 'undefined' && rawData.length > 0) {
    initPriceChart();
    initPriceTable();
}

function initPriceChart() {
    const bars = cleanSeries(rawData.map((d) => ({
        time: toDay(d.time),
        open: parseFloat(d.open), high: parseFloat(d.high), low: parseFloat(d.low), close: parseFloat(d.close),
        value: parseFloat(d.close),
        volume: parseFloat(d.volume) || 0,
    })));
    if (!bars.length) return;

    const times = bars.map((b) => b.time);
    const closes = bars.map((b) => b.close);
    const indexByTime = new Map(times.map((t, i) => [t, i]));
    const el = document.getElementById('priceChart');
    const BASE_HEIGHT = 480, SUB_HEIGHT = 170;

    const chart = makeChart(el);

    // Candles (default) and area (line mode) share one chart so indicators/volume/legend work in both.
    const candle = chart.addSeries(CandlestickSeries, {
        upColor: COLORS.up, downColor: COLORS.down, borderVisible: false,
        wickUpColor: COLORS.up, wickDownColor: COLORS.down, priceFormat: PRICE_FORMAT,
    });
    candle.setData(bars.map(({ time, open, high, low, close }) => ({ time, open, high, low, close })));

    const area = chart.addSeries(AreaSeries, {
        lineColor: COLORS.blue, topColor: 'rgba(37,99,235,0.35)', bottomColor: 'rgba(37,99,235,0)',
        lineWidth: 2, visible: false, priceFormat: PRICE_FORMAT,
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
    const num = (v) => (v === undefined || v === null ? '—' : fmtPrice(v));
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
            `<span>M <b>${num(b.open)}</b></span><span>C <b>${num(b.high)}</b></span>` +
            `<span>T <b>${num(b.low)}</b></span><span>Đ <b class="${cls}">${num(b.close)}</b></span>` +
            (chg === null ? '' : `<span class="${cls}">${chg >= 0 ? '+' : ''}${fmtDec(chg)}%</span>`) +
            `<span>KL <b>${vol(b.volume)}</b></span>`;

        Object.entries(active).forEach(([name, ind]) => {
            const d = seriesData ? seriesData.get(ind.series[0]) : ind.lastValue?.(i);
            const v = d && (d.value ?? undefined);
            if (v !== undefined) html += `<span style="color:${ind.color}">${name} <b>${fmtDec(v)}</b></span>`;
        });
        return html;
    }

    const legend = attachLegend(chart, el, (param) => legendFor(param.time, param.seriesData), legendFor(times[times.length - 1], null));
    const refreshLegend = () => legend.setInitial(legendFor(times[times.length - 1], null));

    // ── Overlay indicators (MA / Bollinger) ─────────────────────────────────
    const toLine = (values) => values.map((v, i) => (v === null ? null : { time: times[i], value: v })).filter(Boolean);
    const overlayLine = (color, width = 1.5, extra = {}) => chart.addSeries(LineSeries, {
        color, lineWidth: width, priceLineVisible: false, lastValueVisible: false,
        crosshairMarkerVisible: false, priceFormat: PRICE_FORMAT, ...extra,
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
    const pageSize = 20;
    let currentPage = 1;
    const newestFirst = rawData.slice().reverse();

    function renderTable(page) {
        currentPage = page;
        const start = (page - 1) * pageSize;
        const tbody = document.getElementById('priceTableBody');
        tbody.innerHTML = newestFirst.slice(start, start + pageSize).map((item) => {
            const date = new Date(item.time).toLocaleDateString('vi-VN');
            const isUp = parseFloat(item.close) >= parseFloat(item.open);
            return `<tr>
                <td style="font-weight:600;">${date}</td>
                <td>${Number(item.open).toLocaleString()}</td>
                <td class="price-positive">${Number(item.high).toLocaleString()}</td>
                <td class="price-negative">${Number(item.low).toLocaleString()}</td>
                <td style="font-weight:700;color:${isUp ? '#10b981' : '#ef4444'};">
                    <i class="bi bi-${isUp ? 'arrow-up' : 'arrow-down'}"></i>
                    ${Number(item.close).toLocaleString()}
                </td>
                <td class="volume-cell">${Number(item.volume).toLocaleString()}</td>
                <td><span class="badge badge-primary">${item.currency || 'VND'}</span></td>
            </tr>`;
        }).join('');
        renderPagination();
    }

    function renderPagination() {
        const totalPages = Math.ceil(rawData.length / pageSize);
        const p = document.getElementById('tablePagination');
        let h = '';
        if (currentPage > 1) h += `<li class="page-item"><a class="page-link" href="#" data-page="${currentPage - 1}"><i class="bi bi-chevron-left"></i></a></li>`;
        const sp = Math.max(1, currentPage - 2), ep = Math.min(totalPages, currentPage + 2);
        if (sp > 1) { h += `<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`; if (sp > 2) h += `<li class="page-item disabled"><span class="page-link">...</span></li>`; }
        for (let i = sp; i <= ep; i++) h += `<li class="page-item${i === currentPage ? ' active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
        if (ep < totalPages) { if (ep < totalPages - 1) h += `<li class="page-item disabled"><span class="page-link">...</span></li>`; h += `<li class="page-item"><a class="page-link" href="#" data-page="${totalPages}">${totalPages}</a></li>`; }
        if (currentPage < totalPages) h += `<li class="page-item"><a class="page-link" href="#" data-page="${currentPage + 1}"><i class="bi bi-chevron-right"></i></a></li>`;
        p.innerHTML = h;
    }

    renderTable(1);
    document.getElementById('tablePagination').addEventListener('click', (e) => {
        const link = e.target.closest('[data-page]');
        if (!link) return;
        e.preventDefault();
        renderTable(parseInt(link.dataset.page, 10));
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

    function loadFinance() {
        const symbol = (typeof stockSymbol !== 'undefined') ? stockSymbol : '';
        if (!symbol) return;
        const body = document.getElementById('financeBody');
        body.innerHTML = '<div style="text-align:center;padding:2rem;color:#6b7280;"><i class="bi bi-hourglass-split" style="font-size:2rem;"></i><p style="margin-top:12px;">Đang tải dữ liệu tài chính...</p></div>';

        fetch(`/stock/finance?symbol=${encodeURIComponent(symbol)}&type=${finType}&period=${finPeriod}`)
            .then(r => r.json())
            .then(json => {
                if (json.error) {
                    body.innerHTML = `<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> ${json.error}</div>`;
                    return;
                }
                body.innerHTML = renderFinanceTable(json.data || [], json.periods || []);
            })
            .catch(() => {
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

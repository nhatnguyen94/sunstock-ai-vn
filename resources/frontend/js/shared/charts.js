// Shared TradingView Lightweight Charts setup (bundled locally by Vite — no CDN request at runtime).
//
// One place defines the look (fonts, grid, crosshair, colours, Vietnamese formatting) so every
// chart in the app matches, and pure helpers (toDay, sliceByDays, rebase) live here so they can be
// unit-tested in Node without a DOM.

import {
    AreaSeries,
    BaselineSeries,
    CandlestickSeries,
    ColorType,
    CrosshairMode,
    HistogramSeries,
    LineSeries,
    LineStyle,
    createChart,
} from 'lightweight-charts';

export { AreaSeries, BaselineSeries, CandlestickSeries, HistogramSeries, LineSeries, LineStyle };

export const COLORS = {
    up: '#10b981',
    down: '#ef4444',
    blue: '#2563eb',
    text: '#6b7280',
    grid: '#f1f5f9',
    palette: ['#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'],
};

const nf0 = new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 });
const nf2 = new Intl.NumberFormat('vi-VN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export const fmtInt = (v) => nf0.format(v);
export const fmtDec = (v) => nf2.format(v);
/** Whole-VND price format for a series (set per series: a chart-wide priceFormatter would also hit RSI/MACD/volume). */
/** Prices in this app are either thousands of VND with decimals (73,1 — stock feed) or whole VND (95.326 — fund NAV). */
export const fmtPrice = (v) => (Math.abs(v) >= 1000 ? fmtInt(v) : fmtDec(v));
/** 2-decimal Vietnamese format for indicator panes (RSI, MACD, index levels). */
export const DEC_FORMAT = { type: 'custom', formatter: fmtDec, minMove: 0.01 };
export const PRICE_FORMAT = { type: 'custom', formatter: fmtPrice, minMove: 0.01 };

export const fmtCompact = (v) => {
    const a = Math.abs(v);
    if (a >= 1e9) return (v / 1e9).toFixed(1).replace('.', ',') + ' tỷ';
    if (a >= 1e6) return (v / 1e6).toFixed(1).replace('.', ',') + ' tr';
    if (a >= 1e3) return (v / 1e3).toFixed(1).replace('.', ',') + 'K';
    return String(Math.round(v));
};

/**
 * Epoch-ms (midnight UTC *or* midnight Vietnam time, whichever the server produced) -> 'YYYY-MM-DD'.
 * The +12h shift makes both land on the intended calendar day; a plain toISOString() would move
 * VN-midnight timestamps back one day.
 */
export function toDay(ms) {
    return new Date(Number(ms) + 12 * 3600 * 1000).toISOString().slice(0, 10);
}

/** Drop points with a non-finite value, sort ascending and keep one point per day (LWC requires strictly ascending times). */
export function cleanSeries(points) {
    const out = [];
    points
        .filter((p) => p && p.time && Number.isFinite(p.value ?? p.close))
        .sort((a, b) => (a.time < b.time ? -1 : a.time > b.time ? 1 : 0))
        .forEach((p) => {
            if (out.length && out[out.length - 1].time === p.time) out[out.length - 1] = p;
            else out.push(p);
        });
    return out;
}

/** Points whose day is within `days` before the last point ('YYYY-MM-DD' strings compare lexicographically). */
export function sliceByDays(points, days, key = 'time') {
    if (!days || !points.length) return points;
    const last = new Date(points[points.length - 1][key] + 'T00:00:00Z').getTime();
    const cutoff = new Date(last - days * 86400000).toISOString().slice(0, 10);
    return points.filter((p) => p[key] >= cutoff);
}

/** Rebase a {time,value} series so its first point is `base` (default 100). */
export function rebase(points, base = 100) {
    if (!points.length || !points[0].value) return [];
    const k = base / points[0].value;
    return points.map((p) => ({ time: p.time, value: +(p.value * k).toFixed(4) }));
}

export function baseOptions(extra = {}) {
    return {
        autoSize: true,
        layout: {
            background: { type: ColorType.Solid, color: 'transparent' },
            textColor: COLORS.text,
            fontFamily: 'Inter, sans-serif',
            fontSize: 12,
            panes: { separatorColor: '#e5e7eb', separatorHoverColor: 'rgba(37,99,235,0.15)', enableResize: true },
        },
        grid: {
            vertLines: { color: COLORS.grid, style: LineStyle.Dotted },
            horzLines: { color: COLORS.grid, style: LineStyle.Dotted },
        },
        crosshair: {
            mode: CrosshairMode.Normal,
            vertLine: { color: '#94a3b8', width: 1, style: LineStyle.Dashed, labelBackgroundColor: '#1e3a5f' },
            horzLine: { color: '#94a3b8', width: 1, style: LineStyle.Dashed, labelBackgroundColor: '#1e3a5f' },
        },
        rightPriceScale: { borderVisible: false, scaleMargins: { top: 0.08, bottom: 0.08 } },
        timeScale: { borderVisible: false, timeVisible: false, rightOffset: 4, minBarSpacing: 0.4 },
        localization: { locale: 'vi-VN', dateFormat: 'dd/MM/yyyy' },
        handleScale: { axisPressedMouseMove: true },
        ...extra,
    };
}

export function makeChart(el, extra = {}) {
    const chart = createChart(el, baseOptions(extra));

    // A chart created inside a hidden container (display:none => width 0) fits its content to a 0px
    // viewport and ends up squeezed against the right edge once shown. Re-fit on the first real size.
    if (el.clientWidth === 0 && 'ResizeObserver' in window) {
        const ro = new ResizeObserver(() => {
            if (el.clientWidth > 0) {
                chart.timeScale().fitContent();
                ro.disconnect();
            }
        });
        ro.observe(el);
    }

    return chart;
}

/**
 * Floating legend inside `el` (position:relative is set here). `render(param)` receives the crosshair
 * event (or null when the cursor leaves) and returns an HTML string; it must escape anything untrusted.
 */
export function attachLegend(chart, el, render, initial = '') {
    if (getComputedStyle(el).position === 'static') el.style.position = 'relative';
    const box = document.createElement('div');
    box.className = 'lwc-legend';
    box.innerHTML = initial;
    el.appendChild(box);

    chart.subscribeCrosshairMove((param) => {
        const html = param && param.time ? render(param) : null;
        box.innerHTML = html ?? initial;
    });

    return { box, setInitial: (html) => { initial = html; box.innerHTML = html; } };
}

/** Keep charts crisp/responsive without polling: LWC's autoSize uses ResizeObserver; this just fits content once shown. */
export function fitWhenVisible(chart) {
    chart.timeScale().fitContent();
}

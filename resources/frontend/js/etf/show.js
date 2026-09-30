// ETF detail: closing-price chart (Lightweight Charts) with a range switch. The series is embedded by the page as
// [[date, close in feed units (thousands of VND), volume]]; prices are shown in whole VND like the rest of the page.

import { AreaSeries, COLORS, HistogramSeries, attachLegend, cleanSeries, fmtInt, makeChart, sliceByDays } from '../shared/charts.js';

const cfg = window.__ETF_DETAIL__ || { series: [] };
const el = document.getElementById('etfChart');

if (el && cfg.series.length >= 2) {
    const points = cleanSeries(cfg.series.map(([time, close]) => ({ time, value: Math.round(close * 1000) })));
    const volumes = new Map(cfg.series.map(([time, , volume]) => [time, volume]));

    const chart = makeChart(el);
    const area = chart.addSeries(AreaSeries, {
        lineColor: COLORS.blue, topColor: 'rgba(37,99,235,0.30)', bottomColor: 'rgba(37,99,235,0)', lineWidth: 2,
        priceFormat: { type: 'custom', formatter: fmtInt, minMove: 1 },
    });
    const vol = chart.addSeries(HistogramSeries, {
        priceScaleId: 'volume', priceFormat: { type: 'volume' }, color: 'rgba(148,163,184,0.45)', lastValueVisible: false, priceLineVisible: false,
    });
    chart.priceScale('volume').applyOptions({ scaleMargins: { top: 0.8, bottom: 0 } });

    attachLegend(chart, el, (param) => {
        const d = param.seriesData.get(area);
        if (!d || d.value === undefined) return null;
        const v = volumes.get(param.time);
        return `<span class="lwc-date">${param.time.split('-').reverse().join('/')}</span>` +
            `<span>Giá <b>${fmtInt(d.value)} ₫</b></span>` +
            (v ? `<span>KL <b>${fmtInt(v)}</b></span>` : '');
    });

    let days = 365;
    function render() {
        const pts = sliceByDays(points, days);
        area.setData(pts);
        vol.setData(pts.map((p) => ({ time: p.time, value: volumes.get(p.time) || 0 })));
        chart.timeScale().fitContent();
    }

    document.querySelectorAll('#etfRanges button').forEach((btn) => {
        btn.addEventListener('click', () => {
            days = Number(btn.dataset.days);
            document.querySelectorAll('#etfRanges button').forEach((b) => b.classList.toggle('active', b === btn));
            render();
        });
    });

    render();
}

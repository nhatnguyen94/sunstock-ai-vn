// Fund detail page: NAV chart (Lightweight Charts baseline series + range switch + per-range stats)
// and portfolio breakdown (dependency-free donut / bars).

import { BaselineSeries, COLORS, attachLegend, cleanSeries, fmtDec, fmtInt, makeChart } from '../shared/charts.js';
import { bars, donut } from '../shared/svgcharts.js';
import { escapeHtml, fmtNum, fmtPct, getJson, sliceNav, trend } from './format.js';

const card = document.getElementById('fdNavCard');
const $ = (id) => document.getElementById(id);

let detail = null;
let chart = null;
let series = null;
let range = '1Y';

function renderStats() {
    const s = detail.stats?.[range] || {};
    const set = (id, text, cls) => {
        const el = $(id);
        el.textContent = text;
        el.className = cls || '';
    };
    set('fdStReturn', fmtPct(s.return_pct ?? null, 2, true), trend(s.return_pct ?? null));
    set('fdStDD', fmtPct(s.max_drawdown ?? null, 2), s.max_drawdown ? 'is-down' : '');
    set('fdStVol', fmtPct(s.volatility ?? null, 2), '');
}

function ensureChart() {
    if (chart) return;
    const el = $('fdNavChart');
    chart = makeChart(el);
    // Baseline series: green above the NAV at the start of the window, red below — gain/loss at a glance.
    series = chart.addSeries(BaselineSeries, {
        topLineColor: COLORS.up, topFillColor1: 'rgba(16,185,129,0.28)', topFillColor2: 'rgba(16,185,129,0.02)',
        bottomLineColor: COLORS.down, bottomFillColor1: 'rgba(239,68,68,0.02)', bottomFillColor2: 'rgba(239,68,68,0.28)',
        lineWidth: 2, priceFormat: { type: 'custom', formatter: fmtInt, minMove: 1 },
        baseValue: { type: 'price', price: 0 },
    });

    attachLegend(chart, el, (param) => {
        const d = param.seriesData.get(series);
        if (!d || d.value === undefined) return null;
        const base = series.options().baseValue.price;
        const chg = base ? (d.value / base - 1) * 100 : null;
        const cls = chg === null ? '' : chg >= 0 ? 'up' : 'down';
        return `<span class="lwc-date">${param.time.split('-').reverse().join('/')}</span>` +
            `<span>NAV <b>${fmtInt(d.value)} ₫</b></span>` +
            (chg === null ? '' : `<span class="${cls}">${chg >= 0 ? '+' : ''}${fmtDec(chg)}% so với đầu kỳ</span>`);
    });
}

function renderNav() {
    ensureChart();
    const pts = cleanSeries(sliceNav(detail.nav, range).map(([time, value]) => ({ time, value })));
    series.setData(pts);
    if (pts.length) series.applyOptions({ baseValue: { type: 'price', price: pts[0].value } });
    chart.timeScale().fitContent();
    renderStats();
}

function renderHoldings() {
    const hasAny = detail.assets.length || detail.industries.length || detail.top_holdings.length;
    if (!hasAny) return;
    $('fdHoldings').hidden = false;

    donut($('fdAssetChart'), {
        labels: detail.assets.map((a) => a.name),
        series: detail.assets.map((a) => a.percent),
        centerLabel: 'Tổng tài sản',
    });

    const top = [...detail.industries].sort((a, b) => b.percent - a.percent).slice(0, 8);
    bars($('fdIndustryChart'), {
        groups: [{ label: '', bars: top.map((i) => ({ name: i.name, value: i.percent, color: COLORS.palette[1] })) }],
        signed: false,
    });

    $('fdTopBody').innerHTML = detail.top_holdings.length
        ? detail.top_holdings.map((h, i) => `
            <tr>
                <td>${i + 1}</td>
                <td>${h.code ? `<a class="fd-code" href="${window.__COMPANY_URL__}/${encodeURIComponent(h.code)}">${escapeHtml(h.code)}</a>` : '—'}</td>
                <td>${escapeHtml(h.industry || '—')}</td>
                <td class="num">${fmtNum(h.percent, 2)}%</td>
            </tr>`).join('')
        : '<tr><td colspan="4" class="fd-empty">Chưa có dữ liệu.</td></tr>';

    if (detail.as_of) {
        const [y, m, d] = detail.as_of.split('-');
        $('fdAsOf').textContent = `(tại ${d}/${m}/${y})`;
    }
}

async function load() {
    $('fdError').hidden = true;
    $('fdLoading').hidden = false;
    try {
        detail = await getJson(card.dataset.url);
        $('fdLoading').hidden = true;
        $('fdNavContent').hidden = false;
        renderNav();
        renderHoldings();
    } catch (e) {
        $('fdLoading').hidden = true;
        $('fdErrorText').textContent = e.message;
        $('fdError').hidden = false;
    }
}

$('fdRanges').addEventListener('click', (ev) => {
    const btn = ev.target.closest('button[data-range]');
    if (!btn || !detail) return;
    range = btn.dataset.range;
    $('fdRanges').querySelectorAll('button').forEach((b) => b.classList.toggle('active', b === btn));
    renderNav();
});
$('fdRetry').addEventListener('click', load);

load();

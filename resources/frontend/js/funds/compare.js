// Fund comparison: NAV rebased to 100, returns per window, and a best-of-row metrics table.
// Adding/removing a fund reloads the page with ?codes=..., so the server-rendered `selected`
// list (with the catalog returns) is always the single source of truth; NAV histories are
// then fetched in parallel from /funds/{code}/detail (each cached server-side for hours).

import { DEC_FORMAT, LineSeries, LineStyle, attachLegend, cleanSeries, fmtDec, makeChart, rebase } from '../shared/charts.js';
import { bars } from '../shared/svgcharts.js';
import { toast } from '../shared/toast.js';
import { COLORS, RANGE_DAYS, escapeHtml, fmtNum, fmtPct, getJson, sliceNav } from './format.js';

const cfg = window.__COMPARE__;
const $ = (id) => document.getElementById(id);
const selected = cfg.selected;

let details = {};   // code -> detail JSON (or null when it failed to load)
let range = '1Y';
let lineChart = null;
let lineSeries = [];   // [{ code, color, series }]

const goTo = (codes) => {
    const q = codes.length ? '?codes=' + encodeURIComponent(codes.join(',')) : '';
    window.location.href = window.location.pathname + q;
};

// ── Picker + chips ──────────────────────────────────────────────────────────
function renderPicker() {
    const sel = $('fdAdd');
    const chosen = new Set(selected.map((f) => f.code.toUpperCase()));
    const full = selected.length >= cfg.max;
    sel.innerHTML = `<option value="">${full ? `Đã chọn tối đa ${cfg.max} quỹ` : 'Chọn quỹ…'}</option>` +
        cfg.picker
            .filter((f) => !chosen.has(f.code.toUpperCase()))
            .map((f) => `<option value="${escapeHtml(f.code)}">${escapeHtml(f.code)} — ${escapeHtml(f.type)}</option>`)
            .join('');
    sel.disabled = full;
    sel.addEventListener('change', () => {
        if (sel.value) goTo(selected.map((f) => f.code).concat(sel.value));
    });

    $('fdChips').innerHTML = selected.map((f, i) => `
        <span class="fd-chip" style="border-color:${COLORS[i]};color:${COLORS[i]}">
            <a href="/funds/${encodeURIComponent(f.code)}" style="color:inherit">${escapeHtml(f.code)}</a>
            <button type="button" data-remove="${escapeHtml(f.code)}" aria-label="Bỏ ${escapeHtml(f.code)}">&times;</button>
        </span>`).join('');
    $('fdChips').addEventListener('click', (ev) => {
        const code = ev.target.closest('[data-remove]')?.dataset.remove;
        if (code) goTo(selected.filter((f) => f.code !== code).map((f) => f.code));
    });
}

// ── Charts ──────────────────────────────────────────────────────────────────
/** Latest NAV date across all loaded funds — every window ends on the same day. */
function commonEnd() {
    const ends = selected.map((f) => details[f.code]?.nav?.at(-1)?.[0]).filter(Boolean);
    return ends.length ? ends.sort().at(-1) : undefined;
}

function renderLine() {
    const end = commonEnd();
    const days = RANGE_DAYS[range];
    // A fund whose first point sits more than ~10 days after the window start is simply younger than the window
    // (the first point inside a window normally lands within a few days of the cutoff).
    const lateAfter = end && days ? new Date(new Date(end + 'T00:00:00Z').getTime() - (days - 10) * 86400000).toISOString().slice(0, 10) : null;

    const el = $('fdLineChart');
    if (!lineChart) {
        lineChart = makeChart(el);
        attachLegend(lineChart, el, (param) => {
            const parts = lineSeries.map(({ code, color, series }) => {
                const d = param.seriesData.get(series);
                return d && d.value !== undefined ? `<span style="color:${color}">${escapeHtml(code)} <b>${fmtDec(d.value)}</b></span>` : '';
            }).join('');
            return `<span class="lwc-date">${param.time.split('-').reverse().join('/')}</span>${parts}`;
        });
    }
    lineSeries.forEach(({ series }) => lineChart.removeSeries(series));

    const notes = [];
    lineSeries = selected.map((f, i) => {
        const nav = details[f.code]?.nav;
        const pts = nav ? cleanSeries(sliceNav(nav, range, end).map(([time, value]) => ({ time, value }))) : [];
        const series = lineChart.addSeries(LineSeries, {
            color: COLORS[i], lineWidth: 2.5, priceLineVisible: false,
            priceFormat: DEC_FORMAT,
        });
        series.setData(rebase(pts, 100));   // every fund starts the window at 100
        if (pts.length && lateAfter && pts[0].time > lateAfter) {
            notes.push(f.code + ' chỉ có dữ liệu từ ' + pts[0].time.split('-').reverse().join('/'));
        }
        return { code: f.code, color: COLORS[i], series };
    });
    if (lineSeries.length) {
        lineSeries[0].series.createPriceLine({ price: 100, color: '#9ca3af', lineWidth: 1, lineStyle: LineStyle.Dashed, axisLabelVisible: false });
    }
    lineChart.timeScale().fitContent();
    $('fdLineNote').textContent = notes.join(' · ');
}

function renderBar() {
    // Since-inception return (often +100s of %) would flatten every other bar; it stays in the table.
    const keys = Object.keys(cfg.returnLabels).filter((k) => k !== 'nav_change_inception');
    bars($('fdBarChart'), {
        groups: keys.map((k) => ({
            label: cfg.returnLabels[k],
            bars: selected.map((f, i) => ({ name: f.code, value: f.returns[k], color: COLORS[i] })),
        })),
    });
}

// ── Table ───────────────────────────────────────────────────────────────────
/** Row with `best` = 'max' | 'min' | null; highlights the winning cell(s). */
function row(label, values, format, best) {
    const nums = values.filter((v) => v !== null && v !== undefined);
    const target = !best || nums.length < 2 ? null : best === 'max' ? Math.max(...nums) : Math.min(...nums);
    return `<tr><td>${label}</td>` + values.map((v) =>
        `<td class="${target !== null && v === target ? 'is-best' : ''}">${format(v)}</td>`).join('') + '</tr>';
}

const group = (label) => `<tr class="fd-group"><td colspan="${selected.length + 1}">${label}</td></tr>`;

function renderTable() {
    const stat = (f, key) => details[f.code]?.stats?.[range]?.[key] ?? null;
    const rangeLabel = range === 'ALL' ? 'toàn bộ lịch sử' : range;
    $('fdTableRange').textContent = `(kỳ so sánh: ${rangeLabel})`;

    const head = '<thead><tr><th></th>' + selected.map((f, i) =>
        `<th style="background:${COLORS[i]}">${escapeHtml(f.code)}</th>`).join('') + '</tr></thead>';

    let body = group('Thông tin quỹ');
    body += row('Loại quỹ', selected.map((f) => f.type), (v) => escapeHtml(v), null);
    body += row('NAV / CCQ (₫)', selected.map((f) => f.nav), (v) => fmtNum(v), null);
    body += row('Phí quản lý / năm', selected.map((f) => f.fee), (v) => fmtPct(v, 2), 'min');

    body += group('Lợi suất (số liệu Fmarket)');
    Object.entries(cfg.returnLabels).forEach(([key, label]) => {
        body += row(label, selected.map((f) => f.returns[key]), (v) => fmtPct(v, 2, true), 'max');
    });

    body += group(`Trong kỳ ${rangeLabel} (tính từ chuỗi NAV)`);
    body += row('Lợi suất kỳ', selected.map((f) => stat(f, 'return_pct')), (v) => fmtPct(v, 2, true), 'max');
    body += row('Sụt giảm tối đa', selected.map((f) => stat(f, 'max_drawdown')), (v) => fmtPct(v, 2), 'max');
    body += row('Biến động (năm hóa)', selected.map((f) => stat(f, 'volatility')), (v) => fmtPct(v, 2), 'min');

    $('fdCompareTable').innerHTML = head + '<tbody>' + body + '</tbody>';
}

function renderRange() {
    renderLine();
    renderTable();
}

// ── Boot ────────────────────────────────────────────────────────────────────
async function boot() {
    renderPicker();

    if (selected.length < 2) {
        $('fdEmpty').hidden = false;
        return;
    }

    $('fdCompare').hidden = false;
    renderBar();
    renderTable(); // catalog numbers show immediately; NAV-derived rows fill in below

    const results = await Promise.allSettled(
        selected.map((f) => getJson(`${cfg.detailUrl}/${encodeURIComponent(f.code)}/detail`))
    );
    results.forEach((r, i) => {
        details[selected[i].code] = r.status === 'fulfilled' ? r.value : null;
        if (r.status === 'rejected') toast(`${selected[i].code}: ${r.reason.message}`, 'warning', 6000);
    });

    $('fdLoading').hidden = true;
    renderRange();
}

$('fdRanges').addEventListener('click', (ev) => {
    const btn = ev.target.closest('button[data-range]');
    if (!btn) return;
    range = btn.dataset.range;
    $('fdRanges').querySelectorAll('button').forEach((b) => b.classList.toggle('active', b === btn));
    if ($('fdLoading').hidden) renderRange();
});

boot();

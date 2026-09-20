// Gold page: chart (Lightweight Charts), lượng/chỉ toggle, calculator, refresh.

import { LineSeries, LineType, attachLegend, fmtInt, makeChart } from '../shared/charts.js';
import { toast } from '../shared/toast.js';

const cfg = window.__GOLD__ || { options: [] };
const $ = (id) => document.getElementById(id);
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// ── Refresh ─────────────────────────────────────────────────────────────────
const refreshBtn = $('gdRefresh');
refreshBtn?.addEventListener('click', async () => {
    if (refreshBtn.disabled) return;
    refreshBtn.disabled = true;
    refreshBtn.classList.add('is-loading');
    try {
        const res = await fetch(cfg.refreshUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' } });
        const data = await res.json().catch(() => ({}));
        if (res.ok && data.success) {
            sessionStorage.setItem('gd-toast', data.message);
            window.location.reload();
            return;
        }
        toast(data.message || 'Không cập nhật được, thử lại sau.', 'warning', 5000);
    } catch (e) {
        toast('Mất kết nối, vui lòng thử lại.', 'error', 5000);
    }
    refreshBtn.disabled = false;
    refreshBtn.classList.remove('is-loading');
});
try {
    const msg = sessionStorage.getItem('gd-toast');
    if (msg) { sessionStorage.removeItem('gd-toast'); toast(msg, 'success', 4000); }
} catch (e) { /* no storage: no toast */ }

// ── lượng / chỉ toggle for every price cell ─────────────────────────────────
let unit = 'luong';
const priceCells = [...document.querySelectorAll('.gd-price[data-luong]')];
const format = (v) => (v === null || v === '' || Number.isNaN(v) ? '—' : fmtInt(Math.round(v)));

function renderUnit() {
    const div = unit === 'chi' ? 10 : 1;
    priceCells.forEach((el) => {
        const v = parseFloat(el.dataset.luong);
        el.textContent = Number.isFinite(v) ? format(v / div) : '—';
    });
    document.querySelectorAll('.gd-unit-label').forEach((el) => { el.textContent = unit === 'chi' ? 'chỉ' : 'lượng'; });
    document.querySelectorAll('#gdUnit button').forEach((b) => b.classList.toggle('active', b.dataset.unit === unit));
}
$('gdUnit')?.addEventListener('click', (e) => {
    const b = e.target.closest('button[data-unit]');
    if (b) { unit = b.dataset.unit; renderUnit(); }
});

// ── Calculator ──────────────────────────────────────────────────────────────
const calcProduct = $('gdCalcProduct');
if (calcProduct) {
    calcProduct.innerHTML = cfg.options.map((o, i) => `<option value="${i}">${o.label.replace(/[<>&]/g, '')}</option>`).join('');

    const calc = () => {
        const o = cfg.options[parseInt(calcProduct.value, 10)];
        const qty = parseFloat($('gdQty').value) || 0;
        const lượng = $('gdQtyUnit').value === 'chi' ? qty / 10 : qty;
        const cost = parseFloat($('gdCost').value);

        const sellNow = o && o.buy ? o.buy * lượng : null;     // you sell → the shop buys at its "mua vào" price
        const buyNow = o && o.sell ? o.sell * lượng : null;     // you buy → you pay its "bán ra" price
        $('gdSellNow').textContent = sellNow === null ? '—' : fmtInt(Math.round(sellNow)) + ' ₫';
        $('gdBuyNow').textContent = buyNow === null ? '—' : fmtInt(Math.round(buyNow)) + ' ₫';

        const row = $('gdPnlRow');
        if (cost > 0 && sellNow !== null && lượng > 0) {
            const pnl = sellNow - cost * lượng;
            const pct = (pnl / (cost * lượng)) * 100;
            $('gdPnl').textContent = `${pnl >= 0 ? '+' : ''}${fmtInt(Math.round(pnl))} ₫ (${pct >= 0 ? '+' : ''}${pct.toFixed(2).replace('.', ',')}%)`;
            $('gdPnl').className = pnl >= 0 ? 'up' : 'down';
            row.hidden = false;
        } else {
            row.hidden = true;
        }
    };
    ['input', 'change'].forEach((ev) => ['gdCalcProduct', 'gdQty', 'gdQtyUnit', 'gdCost'].forEach((id) => $(id).addEventListener(ev, calc)));
    calc();
}

// ── Chart ───────────────────────────────────────────────────────────────────
const chartEl = $('gdChart');
if (chartEl && cfg.options.length) {
    const chart = makeChart(chartEl, { timeScale: { timeVisible: true, secondsVisible: false, borderVisible: false, rightOffset: 3 } });
    const priceFormat = () => ({ type: 'custom', formatter: (v) => fmtInt(v / (unit === 'chi' ? 10 : 1)), minMove: 1 });
    const line = (color) => chart.addSeries(LineSeries, {
        color, lineWidth: 2, lineType: LineType.WithSteps, priceLineVisible: false, lastValueVisible: true,
        priceFormat: priceFormat(),
    });
    const buy = line('#16a34a');
    const sell = line('#d97706');

    attachLegend(chart, chartEl, (param) => {
        const b = param.seriesData.get(buy)?.value;
        const s = param.seriesData.get(sell)?.value;
        if (b === undefined && s === undefined) return null;
        const d = new Date(param.time * 1000).toLocaleString('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit', timeZone: 'Asia/Ho_Chi_Minh' });
        const div = unit === 'chi' ? 10 : 1;
        return `<span class="lwc-date">${d}</span>` +
            (b !== undefined ? `<span style="color:#16a34a">Mua <b>${fmtInt(b / div)}</b></span>` : '') +
            (s !== undefined ? `<span style="color:#d97706">Bán <b>${fmtInt(s / div)}</b></span>` : '');
    });

    let range = '7D';
    let seq = 0;

    async function load() {
        const id = $('gdProduct').value;
        const my = ++seq;
        try {
            const res = await fetch(`${cfg.historyUrl}/${encodeURIComponent(id)}?range=${range}`, { headers: { Accept: 'application/json' } });
            const data = await res.json();
            if (my !== seq) return;
            if (!res.ok || !data.success) throw new Error();

            // LWC needs strictly increasing times: keep the last quote per second
            const uniq = new Map();
            data.points.forEach((p) => uniq.set(p.t, p));
            const pts = [...uniq.values()].sort((a, b) => a.t - b.t);

            buy.setData(pts.map((p) => ({ time: p.t, value: p.buy })));
            sell.setData(pts.filter((p) => p.sell).map((p) => ({ time: p.t, value: p.sell })));
            chart.timeScale().fitContent();
            $('gdChartNote').textContent = pts.length < 3
                ? 'Mới có ít điểm dữ liệu cho khoảng thời gian này — biểu đồ sẽ dày dần vì hệ thống ghi lại giá mỗi 15 phút.'
                : `${pts.length} mức giá trong khoảng thời gian đã chọn.`;
        } catch (e) {
            if (my === seq) $('gdChartNote').textContent = 'Không tải được dữ liệu biểu đồ.';
        }
    }

    $('gdProduct').addEventListener('change', load);
    $('gdRanges').addEventListener('click', (e) => {
        const b = e.target.closest('button[data-range]');
        if (!b) return;
        range = b.dataset.range;
        $('gdRanges').querySelectorAll('button').forEach((x) => x.classList.toggle('active', x === b));
        load();
    });
    // the price axis follows the lượng/chỉ toggle
    $('gdUnit')?.addEventListener('click', () => {
        buy.applyOptions({ priceFormat: priceFormat() });
        sell.applyOptions({ priceFormat: priceFormat() });
    });

    load();
}

renderUnit();

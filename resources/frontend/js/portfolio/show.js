// Portfolio detail page: refresh prices, edit / delete holdings (modals), performance chart, allocation donut.
//
// This file is an ES module, so nothing here is reachable from inline onclick="..." attributes — every
// handler is attached with addEventListener. (The previous version defined global-looking functions and
// called them from inline handlers, which never worked once Vite bundled it as a module.)

import { AreaSeries, LineSeries, LineType, attachLegend, cleanSeries, fmtInt, makeChart } from '../shared/charts.js';
import { donut } from '../shared/svgcharts.js';
import { toast } from '../shared/toast.js';
import { stockAutocomplete } from '../shared/autocomplete.js';
import { estimateFee, tradePreview } from './ledger.js';

const cfg = window.__PF__ || {};
const $id = (id) => document.getElementById(id);
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// A toast that must survive a page reload (refresh → reload → "Đã cập nhật giá …")
try {
    const pending = sessionStorage.getItem('pf-toast');
    if (pending) {
        sessionStorage.removeItem('pf-toast');
        const { message, type } = JSON.parse(pending);
        toast(message, type, 6000);
    }
} catch (e) { /* storage unavailable: no toast, no problem */ }

// ── Overflow menu ───────────────────────────────────────────────────────────
const menu = $id('pfMenu');
if (menu) {
    const btn = $id('pfMenuBtn');
    const setOpen = (open) => {
        menu.classList.toggle('open', open);
        btn.setAttribute('aria-expanded', String(open));
    };
    btn.addEventListener('click', (e) => { e.stopPropagation(); setOpen(!menu.classList.contains('open')); });
    document.addEventListener('click', (e) => { if (!menu.contains(e.target)) setOpen(false); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setOpen(false); });
    menu.querySelectorAll('[data-toggle="modal"]').forEach((b) => b.addEventListener('click', () => setOpen(false)));
}

// ── Refresh prices ──────────────────────────────────────────────────────────
const refreshBtn = $id('pfRefresh');
refreshBtn?.addEventListener('click', async () => {
    if (refreshBtn.disabled) return;
    refreshBtn.disabled = true;
    refreshBtn.classList.add('is-loading');

    try {
        const res = await fetch(cfg.refreshUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        });
        const data = await res.json().catch(() => ({}));

        if (res.ok && data.success) {
            sessionStorage.setItem('pf-toast', JSON.stringify({ message: data.message, type: data.missing?.length ? 'warning' : 'success' }));
            window.location.reload();
            return;
        }
        toast(data.message || 'Không cập nhật được giá, vui lòng thử lại.', 'error', 6000);
    } catch (e) {
        toast('Mất kết nối, vui lòng thử lại.', 'error', 6000);
    }

    refreshBtn.disabled = false;
    refreshBtn.classList.remove('is-loading');
});

// ── Edit holding (modal, PUT /portfolio/item/{id}) ──────────────────────────
document.querySelectorAll('.pf-edit').forEach((b) => b.addEventListener('click', () => {
    const d = b.dataset;
    $id('pfEditForm').action = `${cfg.itemUrl}/${d.id}`;
    $id('pfEditSymbol').textContent = d.symbol;
    $id('pfEQty').value = d.quantity;
    $id('pfEBuy').value = d.buy;
    $id('pfETarget').value = d.target;
    $id('pfEStop').value = d.stop;
    $id('pfENotes').value = d.notes || '';
    window.$('#pfEditModal').modal('show');
}));

// ── Delete holding ──────────────────────────────────────────────────────────
document.querySelectorAll('.pf-del').forEach((b) => b.addEventListener('click', () => {
    $id('pfDeleteForm').action = `${cfg.itemUrl}/${b.dataset.id}`;
    $id('pfDelSymbol').textContent = b.dataset.symbol;
    window.$('#pfDeleteModal').modal('show');
}));

// Double-submit guard on the modal forms
document.querySelectorAll('.pf-modal form').forEach((f) => f.addEventListener('submit', () => {
    f.querySelectorAll('button[type="submit"]').forEach((s) => { s.disabled = true; });
}));

// ── Allocation donut ────────────────────────────────────────────────────────
if (cfg.allocation && $id('pfAllocChart')) {
    donut($id('pfAllocChart'), { ...cfg.allocation, centerLabel: 'Tỷ trọng' });
}

// ── Performance chart ───────────────────────────────────────────────────────
const perfEl = $id('pfPerfChart');
if (perfEl && Array.isArray(cfg.performance) && cfg.performance.length > 1) {
    const chart = makeChart(perfEl, { rightPriceScale: { borderVisible: false, scaleMargins: { top: 0.12, bottom: 0.08 } } });

    const value = chart.addSeries(AreaSeries, {
        lineColor: '#2563eb', topColor: 'rgba(37,99,235,0.28)', bottomColor: 'rgba(37,99,235,0)', lineWidth: 2,
        priceFormat: { type: 'custom', formatter: fmtInt, minMove: 1 },
    });
    const invested = chart.addSeries(LineSeries, {
        color: '#94a3b8', lineWidth: 2, lineType: LineType.WithSteps, priceLineVisible: false, lastValueVisible: false,
        priceFormat: { type: 'custom', formatter: fmtInt, minMove: 1 },
    });

    value.setData(cleanSeries(cfg.performance.map((p) => ({ time: p.date, value: p.value }))));
    invested.setData(cleanSeries(cfg.performance.map((p) => ({ time: p.date, value: p.invested }))));

    attachLegend(chart, perfEl, (param) => {
        const v = param.seriesData.get(value)?.value;
        const i = param.seriesData.get(invested)?.value;
        if (v === undefined) return null;
        const pl = i ? ((v / i - 1) * 100) : null;
        const cls = pl === null ? '' : pl >= 0 ? 'up' : 'down';
        return `<span class="lwc-date">${param.time.split('-').reverse().join('/')}</span>` +
            `<span>Giá trị <b>${fmtInt(v)} ₫</b></span><span>Vốn <b>${fmtInt(i ?? 0)} ₫</b></span>` +
            (pl === null ? '' : `<span class="${cls}">${pl >= 0 ? '+' : ''}${pl.toFixed(2).replace('.', ',')}%</span>`);
    });

    chart.timeScale().fitContent();
} else if (perfEl) {
    perfEl.innerHTML = '<p class="pf-hint" style="padding:2rem 0;text-align:center">Chưa đủ dữ liệu giá để vẽ biểu đồ. Biểu đồ sẽ xuất hiện khi các mã trong danh mục có lịch sử giá.</p>';
    perfEl.style.height = 'auto';
}

// ── Trade modal (buy / sell → POST /portfolio/{id}/transactions) ────────────
const tradeForm = $id('pfTradeForm');
if (tradeForm) {
    const held = cfg.holdings || {};
    const el = { sym: $id('pfTSymbol'), qty: $id('pfTQty'), price: $id('pfTPrice'), fee: $id('pfTFee'), info: $id('pfTInfo'), submit: $id('pfTSubmit'), feeLabel: $id('pfTFeeLabel') };
    const typeOf = () => tradeForm.querySelector('input[name="type"]:checked').value;
    let feeTouched = false;
    let quoteSeq = 0;

    function refresh() {
        const type = typeOf();
        const symbol = el.sym.value.trim().toUpperCase();
        const qty = parseInt(el.qty.value, 10) || 0;
        const price = parseFloat(el.price.value) || 0;

        el.feeLabel.textContent = type === 'sell' ? 'Phí + thuế bán' : 'Phí giao dịch';
        $id('pfTradeTitle').textContent = type === 'sell' ? 'Bán cổ phiếu' : 'Mua cổ phiếu';
        el.submit.classList.toggle('pf-btn-danger-solid', type === 'sell');
        el.submit.classList.toggle('pf-btn-primary', type !== 'sell');

        if (!feeTouched && qty && price) el.fee.value = estimateFee(type, qty, price);

        const p = tradePreview({ type, qty, price, fee: parseFloat(el.fee.value) || 0, holding: held[symbol] || null });
        el.info.hidden = !p;
        if (p) {
            el.info.className = 'pf-trade-info ' + (p.tone || '');
            el.info.innerHTML = p.html;
        }
    }

    function openTrade(type, symbol) {
        tradeForm.querySelector(`input[name="type"][value="${type}"]`).checked = true;
        el.sym.value = symbol || '';
        el.sym.readOnly = !!symbol;
        el.qty.value = '';
        el.price.value = symbol && held[symbol] ? held[symbol].price : '';
        el.fee.value = 0;
        feeTouched = false;
        $id('pfTNotes').value = '';
        if (symbol && type === 'sell' && held[symbol]) el.qty.value = held[symbol].qty;   // sell = usually the whole position
        refresh();
        window.$('#pfTradeModal').modal('show');
        setTimeout(() => (symbol ? el.qty : el.sym).focus(), 350);
    }

    document.querySelectorAll('.pf-trade').forEach((b) => b.addEventListener('click', () => openTrade(b.dataset.type, b.dataset.symbol)));
    tradeForm.querySelectorAll('input[name="type"]').forEach((r) => r.addEventListener('change', refresh));
    [el.sym, el.qty, el.price].forEach((i) => i.addEventListener('input', refresh));
    el.fee.addEventListener('input', () => { feeTouched = true; refresh(); });

    // Free symbol (header button): pick from the suggestions, then prefill today's price
    stockAutocomplete(el.sym, {
        maxItems: 6,
        onPick: async (symbol) => {
            el.sym.value = symbol;
            const seq = ++quoteSeq;
            try {
                const res = await fetch(`/portfolio/quote/${encodeURIComponent(symbol)}`, { headers: { Accept: 'application/json' } });
                const q = await res.json();
                if (seq === quoteSeq && q.success && q.price && !el.price.value) { el.price.value = Math.round(q.price); refresh(); }
            } catch (e) { /* price stays manual */ }
            refresh();
        },
    });
}

// ── Undo a transaction ──────────────────────────────────────────────────────
document.querySelectorAll('.pf-undo').forEach((b) => b.addEventListener('click', () => {
    $id('pfUndoForm').action = `/portfolio/transactions/${b.dataset.id}`;
    $id('pfUndoLabel').textContent = `Giao dịch: ${b.dataset.label}`;
    window.$('#pfUndoModal').modal('show');
}));

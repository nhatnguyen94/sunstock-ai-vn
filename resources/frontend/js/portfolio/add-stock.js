// Add-stock form: symbol autocomplete → company name + current price pre-filled, live totals,
// one-click target / stop-loss presets. Replaces the old hardcoded 8-company "mock lookup".

import { stockAutocomplete } from '../shared/autocomplete.js';

const cfg = window.__PF__ || {};
const $ = (id) => document.getElementById(id);
const fmt = new Intl.NumberFormat('vi-VN');

const symbolInput = $('stock_symbol');
const nameInput = $('stock_name');
const qty = $('quantity');
const price = $('buy_price');

let quote = null;

// ── Live totals ─────────────────────────────────────────────────────────────
function recalc() {
    const total = (parseInt(qty.value, 10) || 0) * (parseFloat(price.value) || 0);
    $('totalInvestment').textContent = fmt.format(Math.round(total)) + '₫';

    const base = cfg.portfolioValue || 0;
    $('portfolioPercent').textContent = total > 0 ? ((total / (base + total)) * 100).toFixed(1).replace('.', ',') + '%' : '—';
}
qty.addEventListener('input', recalc);
price.addEventListener('input', recalc);

// ── Quote panel ─────────────────────────────────────────────────────────────
const panel = $('pfQuote');
const panelText = $('pfQuoteText');
let quoteSeq = 0;

function showPanel(html, isError = false) {
    panelText.innerHTML = html;
    panel.classList.add('show');
    panel.classList.toggle('err', isError);
    $('pfUseQuote').style.display = isError || !quote?.price ? 'none' : '';
}

async function loadQuote(symbol) {
    symbol = (symbol || '').trim().toUpperCase();
    if (!/^[A-Z0-9]{2,10}$/.test(symbol)) return;

    const seq = ++quoteSeq;
    try {
        const res = await fetch(`${cfg.quoteUrl}/${encodeURIComponent(symbol)}`, { headers: { Accept: 'application/json' } });
        const data = await res.json();
        if (seq !== quoteSeq) return;   // a newer symbol was chosen meanwhile

        if (!res.ok || !data.success) {
            quote = null;
            nameInput.value = '';
            showPanel('Không tìm thấy mã <b>' + symbol.replace(/[<>&]/g, '') + '</b>. Hãy chọn từ danh sách gợi ý.', true);
            return;
        }

        quote = data;
        nameInput.value = data.name;

        const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const chg = data.change_percent;
        const chgHtml = chg === null ? '' : ` <b style="color:${chg >= 0 ? '#047857' : '#b91c1c'}">${chg >= 0 ? '+' : ''}${chg.toFixed(2).replace('.', ',')}%</b>`;
        const date = data.date ? data.date.split('-').reverse().join('/') : null;

        showPanel(data.price
            ? `<b>${esc(data.symbol)}</b> · ${esc(data.name)} · giá hiện tại <b>${fmt.format(Math.round(data.price))}₫</b>${chgHtml}${date ? ` <span style="opacity:.7">(phiên ${date})</span>` : ''}`
            : `<b>${esc(data.symbol)}</b> · ${esc(data.name)} — chưa có dữ liệu giá, bạn nhập giá mua thủ công.`);

        // Pre-fill only when the user has not typed a price yet
        if (data.price && !price.value) {
            price.value = Math.round(data.price);
            recalc();
        }
    } catch (e) {
        if (seq === quoteSeq) showPanel('Không tải được giá hiện tại, bạn có thể nhập giá mua thủ công.', true);
    }
}

$('pfUseQuote').addEventListener('click', () => {
    if (quote?.price) {
        price.value = Math.round(quote.price);
        recalc();
        price.focus();
    }
});

// ── Autocomplete ────────────────────────────────────────────────────────────
stockAutocomplete(symbolInput, {
    maxItems: 8,
    onPick: (symbol) => { loadQuote(symbol); qty.focus(); },
});
symbolInput.addEventListener('blur', () => { if (symbolInput.value && !quote) loadQuote(symbolInput.value); });
symbolInput.addEventListener('input', () => { quote = null; nameInput.value = ''; panel.classList.remove('show'); });

// ── Target / stop-loss presets ──────────────────────────────────────────────
document.querySelectorAll('[data-preset]').forEach((b) => b.addEventListener('click', () => {
    const base = parseFloat(price.value);
    if (!base) {
        price.focus();
        return;
    }
    const target = $(b.dataset.preset === 'target' ? 'target_price' : 'stop_loss_price');
    target.value = Math.round(base * (1 + parseFloat(b.dataset.pct) / 100));
}));

// Prefilled from ?symbol= (stock page "add to portfolio") or a failed validation round-trip
if (symbolInput.value) loadQuote(symbolInput.value);
recalc();

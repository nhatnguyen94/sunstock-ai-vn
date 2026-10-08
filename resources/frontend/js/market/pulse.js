// Home page "Nhịp thị trường" cards that are drawn from data in the browser: the foreign-flow card and the sentiment gauge.
// Everything here is pure (data in, HTML string out) and unit-tested in tests/js/pulse.test.mjs; home.js puts the result in the page,
// on load and again with every poll. All text that comes from data goes through esc().

import { esc, exchangeLabel, fmtPct, fmtValue } from './format.js';

const clamp = (n, lo, hi) => Math.min(hi, Math.max(lo, n));

/** The five bands of the gauge (same cut-offs as App\Support\MarketSentiment::BANDS). */
export const BANDS = [
    { from: 0, to: 20, color: '#dc2626' },
    { from: 20, to: 40, color: '#f97316' },
    { from: 40, to: 60, color: '#f5b800' },
    { from: 60, to: 80, color: '#84cc16' },
    { from: 80, to: 100, color: '#16a34a' },
];

/** Needle angle in degrees for a 0..100 score on a half-circle: 0 -> -90 (far left), 50 -> 0 (up), 100 -> 90 (far right). */
export const needleAngle = (score) => Math.round((clamp(Number(score) || 0, 0, 100) / 100) * 180 - 90);

/** SVG arc along a half circle (centre cx,cy, radius r) between two scores of the 0..100 scale. */
export function arcPath(cx, cy, r, from, to) {
    const point = (score) => {
        const theta = Math.PI * (1 - clamp(score, 0, 100) / 100);
        return [Math.round((cx + r * Math.cos(theta)) * 100) / 100, Math.round((cy - r * Math.sin(theta)) * 100) / 100];
    };
    const [x0, y0] = point(from);
    const [x1, y1] = point(to);

    return `M ${x0} ${y0} A ${r} ${r} 0 0 1 ${x1} ${y1}`;
}

/** The numbers the foreign card shows, or null without data. */
export function foreignModel(f) {
    if (!f || typeof f.net_value !== 'number') return null;
    const buy = Number(f.buy_value) || 0;
    const sell = Number(f.sell_value) || 0;
    const total = buy + sell;

    return {
        net: f.net_value,
        dir: f.net_value > 0 ? 'up' : f.net_value < 0 ? 'down' : 'flat',
        buy,
        sell,
        buyShare: total > 0 ? Math.round((buy / total) * 1000) / 10 : 50,
        byExchange: Object.entries(f.by_exchange || {}).map(([code, v]) => ({ code, net: Number(v.net) || 0 })),
        topBuy: (f.top_buy || []).slice(0, 5),
        topSell: (f.top_sell || []).slice(0, 5),
    };
}

const stockRows = (rows, dir) => (rows.length
    ? rows.map((r) => `<li><a class="mk-sym" href="/stock?symbol=${encodeURIComponent(r.symbol)}">${esc(r.symbol)}</a><span class="${dir}">${esc(fmtValue(Math.abs(r.net_value)))}</span></li>`).join('')
    : '<li class="mk-fx-none">Không có mã nổi bật</li>');

/** The foreign card's body. */
export function renderForeign(f) {
    const m = foreignModel(f);
    if (!m) return '<p class="mk-fx-empty">Chưa có dữ liệu khối ngoại ở lần cập nhật gần nhất. Dữ liệu sẽ có sau lần cập nhật thị trường kế tiếp.</p>';

    const lead = m.dir === 'up' ? 'Mua ròng' : m.dir === 'down' ? 'Bán ròng' : 'Cân bằng';
    const exchanges = m.byExchange
        .map((e) => `<span class="${e.net > 0 ? 'up' : e.net < 0 ? 'down' : 'flat'}">${esc(exchangeLabel(e.code))} ${esc((e.net > 0 ? '+' : e.net < 0 ? '-' : '') + fmtValue(Math.abs(e.net)))}</span>`)
        .join('');

    return `<div class="mk-fx-net ${m.dir}"><small>${lead} toàn thị trường</small><b>${esc(fmtValue(Math.abs(m.net)))} ₫</b></div>`
        + `<div class="mk-fx-bar" role="img" aria-label="Mua ${esc(fmtValue(m.buy))}, bán ${esc(fmtValue(m.sell))}"><span class="buy" style="width:${m.buyShare}%"></span><span class="sell" style="width:${Math.round((100 - m.buyShare) * 10) / 10}%"></span></div>`
        + `<div class="mk-fx-legend"><span class="up">Mua ${esc(fmtValue(m.buy))}</span><span class="down">Bán ${esc(fmtValue(m.sell))}</span></div>`
        + `<div class="mk-fx-ex">${exchanges}</div>`
        + '<div class="mk-fx-cols">'
        + `<div><h5>Mua ròng nhiều nhất</h5><ul>${stockRows(m.topBuy, 'up')}</ul></div>`
        + `<div><h5>Bán ròng nhiều nhất</h5><ul>${stockRows(m.topSell, 'down')}</ul></div>`
        + '</div>'
        + '<p class="mk-pc-note">Ước tính = khối lượng ròng × giá hiện tại; bảng giá không có giá trị khớp thật của khối ngoại.</p>';
}

/** The sentiment gauge and its four parts. The needle starts at the far left; home.js turns it to `data-angle` so it sweeps in. */
export function renderSentiment(s) {
    if (!s || typeof s.score !== 'number') return '<p class="mk-fx-empty">Chưa đủ dữ liệu để tính chỉ báo tâm lý.</p>';

    const arcs = BANDS.map((b) => `<path d="${arcPath(100, 100, 78, b.from + 0.6, b.to - 0.6)}" stroke="${b.color}" stroke-width="15" fill="none"/>`).join('');
    const parts = (s.components || [])
        .map((c) => `<li><span>${esc(c.title)} <em>${esc(String(c.weight))}%</em></span><i style="--w:${clamp(Number(c.score) || 0, 0, 100)}%"></i><small>${esc(c.detail)}</small></li>`)
        .join('');

    return `<div class="mk-gauge"><svg viewBox="0 0 200 134" role="img" aria-label="Chỉ báo tâm lý thị trường: ${esc(String(s.score))} trên 100, ${esc(s.label)}">`
        + `${arcs}<g class="mk-needle" data-angle="${needleAngle(s.score)}" style="transform: rotate(-90deg)"><line x1="100" y1="100" x2="100" y2="34" stroke="currentColor" stroke-width="3" stroke-linecap="round"/><circle cx="100" cy="100" r="6" fill="currentColor"/></g>`
        + `<text x="100" y="130" text-anchor="middle" class="mk-gauge-score">${esc(String(s.score))}</text></svg>`
        + `<div class="mk-gauge-label ${esc(s.tone)}">${esc(s.label)}</div></div>`
        + `<ul class="mk-gauge-parts">${parts}</ul>`
        + `<p class="mk-pc-note">${esc(s.note || '')}</p>`;
}

/** "+0,35%" with the sign, for the small change chips; '' when unknown. */
export const chip = (pct) => (pct === null || pct === undefined ? '' : fmtPct(pct));

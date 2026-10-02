// Price history table: pure row model + markup, so the numbers (change against the previous session, volume bar
// width, the "busy day" flag) can be unit-tested in Node without a DOM.

import { formatNumber } from '../shared/pricefx.js';

export const PAGE_SIZE = 20;

const WEEKDAYS = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];

/** HTML-escape anything that did not come from our own code (the feed is trusted, but a row is never markup). */
export function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

/**
 * Rows newest first, converted to the displayed unit, each with its change against the session before it.
 *
 * @param {Array<{time:number, open:any, high:any, low:any, close:any, volume:any}>} raw oldest first, feed units
 * @param {number} scale 1000 for stocks/ETFs (feed is in thousands of VND), 1 for an index
 */
export function buildRows(raw, scale = 1) {
    const rows = raw.map((r) => ({
        time: Number(r.time),
        open: Number(r.open) * scale,
        high: Number(r.high) * scale,
        low: Number(r.low) * scale,
        close: Number(r.close) * scale,
        volume: Number(r.volume) || 0,
    }));

    const out = [];
    for (let i = rows.length - 1; i >= 0; i--) {
        const r = rows[i];
        const prev = i > 0 ? rows[i - 1].close : null;
        const change = prev !== null && prev > 0 ? r.close - prev : null;
        const pct = prev !== null && prev > 0 ? (r.close / prev - 1) * 100 : null;
        out.push({ ...r, change, pct, dir: change === null || Math.abs(change) < 1e-9 ? 'flat' : change > 0 ? 'up' : 'down' });
    }
    return out;
}

/** Width (0..100) of a volume bar relative to the busiest session in the page being shown. */
export function volumeShare(volume, maxVolume) {
    return maxVolume > 0 ? Math.min(100, Math.round((volume / maxVolume) * 100)) : 0;
}

/** dd/MM/yyyy and the weekday, from the epoch-ms the repository returns (midnight UTC or Vietnam time). */
export function dateParts(ms) {
    const d = new Date(Number(ms) + 12 * 3600 * 1000);   // the +12h lands both kinds of midnight on the intended day
    const dd = String(d.getUTCDate()).padStart(2, '0');
    const mm = String(d.getUTCMonth() + 1).padStart(2, '0');
    return { date: `${dd}/${mm}/${d.getUTCFullYear()}`, weekday: WEEKDAYS[d.getUTCDay()] };
}

/** One table row. `decimals` is 0 for VND and 2 for index points; `avgVolume` marks a session well above normal. */
export function rowHtml(row, { decimals = 0, maxVolume = 0, avgVolume = 0 } = {}) {
    const { date, weekday } = dateParts(row.time);
    const sign = row.change > 0 ? '+' : '';
    const changeCell = row.change === null
        ? '<span class="is-flat">—</span>'
        : `${sign}${formatNumber(row.change, decimals)}<small>${sign}${formatNumber(row.pct, 2)}%</small>`;
    const hot = avgVolume > 0 && row.volume >= avgVolume * 1.5;

    return `<tr class="${hot ? 'is-hot' : ''}">` +
        `<td class="px-date px-left">${esc(date)}<small>${esc(weekday)}</small></td>` +
        `<td class="px-close is-${row.dir}">${formatNumber(row.close, decimals)}</td>` +
        `<td class="px-change is-${row.dir}">${changeCell}</td>` +
        `<td>${formatNumber(row.open, decimals)}</td>` +
        `<td class="is-up">${formatNumber(row.high, decimals)}</td>` +
        `<td class="is-down">${formatNumber(row.low, decimals)}</td>` +
        `<td class="px-vol"><span class="px-vol-cell"><i class="px-vol-bar" style="width:${volumeShare(row.volume, maxVolume)}%"></i><span>${formatNumber(row.volume, 0)}</span></span></td>` +
        '</tr>';
}

/** The rows of one page as HTML, with the bar scale and the "busy day" threshold taken from the whole history. */
export function pageHtml(rows, page, { decimals = 0, pageSize = PAGE_SIZE } = {}) {
    const slice = rows.slice((page - 1) * pageSize, page * pageSize);
    const maxVolume = Math.max(0, ...slice.map((r) => r.volume));
    const avgVolume = rows.length ? rows.reduce((sum, r) => sum + r.volume, 0) / rows.length : 0;
    return slice.map((r) => rowHtml(r, { decimals, maxVolume, avgVolume })).join('');
}

/** Page numbers to show around the current one: [1, '…', 4, 5, 6, '…', 20]. */
export function pageWindow(current, total, around = 2) {
    const pages = new Set([1, total]);
    for (let p = current - around; p <= current + around; p++) {
        if (p >= 1 && p <= total) pages.add(p);
    }
    const sorted = [...pages].sort((a, b) => a - b);
    const out = [];
    sorted.forEach((p, i) => {
        if (i > 0 && p - sorted[i - 1] > 1) out.push('…');
        out.push(p);
    });
    return out;
}

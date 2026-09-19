// Shared helpers for the fund pages.

export const COLORS = ['#2563eb', '#10b981', '#f59e0b', '#ef4444'];
export const RANGE_DAYS = { '1M': 30, '3M': 91, '6M': 182, '1Y': 365, '3Y': 1095, ALL: null };

const nf = (d) => new Intl.NumberFormat('vi-VN', { minimumFractionDigits: d, maximumFractionDigits: d });

export const fmtNum = (v, d = 0) => (v === null || v === undefined ? '—' : nf(d).format(v));
export const fmtPct = (v, d = 2, signed = false) =>
    v === null || v === undefined ? '—' : (signed && v > 0 ? '+' : '') + nf(d).format(v) + '%';
export const trend = (v) => (v === null || v === undefined ? 'is-na' : v > 0 ? 'is-up' : v < 0 ? 'is-down' : 'is-flat');

/** nav = [["YYYY-MM-DD", value], ...] ascending. Points inside the window ending at `endDate` (default: last point). */
export function sliceNav(nav, range, endDate) {
    const days = RANGE_DAYS[range];
    if (!nav.length || days === null || days === undefined) return nav;
    const end = new Date((endDate || nav[nav.length - 1][0]) + 'T00:00:00Z').getTime();
    const cutoff = new Date(end - days * 86400000).toISOString().slice(0, 10);
    return nav.filter((p) => p[0] >= cutoff);
}

export async function getJson(url) {
    const res = await fetch(url, { headers: { Accept: 'application/json' } });
    let data = {};
    try { data = await res.json(); } catch (e) { /* non-JSON error page */ }
    if (!res.ok || !data.success) {
        throw new Error(data.error || 'Không tải được dữ liệu (mã lỗi ' + res.status + ')');
    }
    return data;
}

export const escapeHtml = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

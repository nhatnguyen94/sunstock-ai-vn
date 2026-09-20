// Pure formatting helpers for the market widgets (no DOM — unit-tested in Node).

const nf = (min, max = min) => new Intl.NumberFormat('vi-VN', { minimumFractionDigits: min, maximumFractionDigits: max });
const NF0 = nf(0);
const NF1 = nf(1);
const NF2 = nf(2);

export const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

/** 'up' | 'down' | 'flat' for a signed number (null/NaN -> flat). */
export function dirClass(v) {
    const n = Number(v);
    if (!Number.isFinite(n) || n === 0) return 'flat';
    return n > 0 ? 'up' : 'down';
}

/** 1234567 -> '1.234.567' ; null -> '—' */
export function fmtInt(v) {
    return v === null || v === undefined || Number.isNaN(Number(v)) ? '—' : NF0.format(Math.round(Number(v)));
}

/** 2.5 -> '+2,50%' ; -1 -> '-1,00%' ; 0 -> '0,00%' */
export function fmtPct(v, signed = true) {
    if (v === null || v === undefined || Number.isNaN(Number(v))) return '—';
    const n = Number(v);
    return (signed && n > 0 ? '+' : '') + NF2.format(n) + '%';
}

/** VND traded value in the unit people read: nghìn tỷ / tỷ / triệu. */
export function fmtValue(v) {
    if (v === null || v === undefined || Number.isNaN(Number(v))) return '—';
    const n = Number(v);
    const a = Math.abs(n);
    if (a >= 1e12) return NF1.format(n / 1e12) + ' nghìn tỷ';
    if (a >= 1e9) return NF0.format(n / 1e9) + ' tỷ';
    if (a >= 1e6) return NF0.format(n / 1e6) + ' triệu';
    return NF0.format(n);
}

/** Share volume: 15500700 -> '15,5 tr' ; 45300 -> '45,3K' */
export function fmtVolume(v) {
    if (v === null || v === undefined || Number.isNaN(Number(v))) return '—';
    const n = Number(v);
    const a = Math.abs(n);
    if (a >= 1e6) return NF1.format(n / 1e6) + ' tr';
    if (a >= 1e3) return NF1.format(n / 1e3) + 'K';
    return NF0.format(n);
}

/** Exchange codes as people write them: the symbol list stores HOSE as 'HSX', KBS says 'UPCOM'. */
export function exchangeLabel(code) {
    return { HSX: 'HOSE', HOSE: 'HOSE', HNX: 'HNX', UPCOM: 'UPCoM' }[code] ?? (code || '');
}

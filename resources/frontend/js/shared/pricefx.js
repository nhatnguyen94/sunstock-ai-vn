// Small visual cues for numbers that move: a count-up from the previous value and a one-shot flash in the move's colour.
// The pure helpers (steps, direction, formatting) are unit-tested in Node; the DOM helpers only apply them.

const nfCache = new Map();

/** Vietnamese number format with a fixed number of decimals (1.234,50), cached per precision. */
export function formatNumber(value, decimals = 0) {
    if (!nfCache.has(decimals)) {
        nfCache.set(decimals, new Intl.NumberFormat('vi-VN', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }));
    }
    return nfCache.get(decimals).format(value);
}

/** 'up' | 'down' | 'flat' for the move from `from` to `to` (non-numbers count as flat). */
export function direction(from, to) {
    // Number(null) is 0 and Number('') is 0: a missing value must not look like a price of zero
    const toNum = (v) => (v === null || v === undefined || v === '' ? NaN : Number(v));
    const a = toNum(from);
    const b = toNum(to);
    if (!Number.isFinite(a) || !Number.isFinite(b) || Math.abs(b - a) < 1e-9) return 'flat';
    return b > a ? 'up' : 'down';
}

/** Ease-out cubic: fast start, gentle landing. t is clamped to 0..1. */
export function easeOutCubic(t) {
    const x = Math.min(1, Math.max(0, t));
    return 1 - Math.pow(1 - x, 3);
}

/** The value shown `t` (0..1) of the way through a count-up from `from` to `to`; always lands exactly on `to` at t = 1. */
export function valueAt(from, to, t) {
    if (t >= 1) return to;
    return from + (to - from) * easeOutCubic(t);
}

/** True when the visitor asked the OS for less motion. */
export function prefersReducedMotion() {
    return typeof window !== 'undefined' && !!window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/** Flash `el` once in the colour of the move; does nothing for a flat move or with reduced motion. */
export function flash(el, dir) {
    if (!el || dir === 'flat' || prefersReducedMotion()) return;
    const cls = dir === 'up' ? 'fx-flash-up' : 'fx-flash-down';
    el.classList.remove('fx-flash-up', 'fx-flash-down');
    void el.offsetWidth;   // restart the animation if it is already running
    el.classList.add(cls);
    el.addEventListener('animationend', () => el.classList.remove(cls), { once: true });
}

/**
 * Count the number in `el` from `from` to `to`. The markup already holds the final text (so it is right without
 * JavaScript and for reduced motion); this only plays the movement and then restores that exact text.
 */
export function countUp(el, from, to, { decimals = 0, duration = 700 } = {}) {
    if (!el || !Number.isFinite(from) || !Number.isFinite(to) || from === to || prefersReducedMotion()) return;
    const finalText = el.textContent;
    const start = performance.now();

    const step = (now) => {
        const t = (now - start) / duration;
        if (t >= 1) {
            el.textContent = finalText;
            return;
        }
        el.textContent = formatNumber(valueAt(from, to, t), decimals);
        requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
}

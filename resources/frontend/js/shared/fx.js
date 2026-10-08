// Visual effects for the home page: cards that tilt towards the pointer (3D), sections that rise into view while scrolling, and the
// sticky jump bar that follows the section being read. All of it is decoration: it is skipped for visitors who ask for reduced
// motion, and the tilt only runs for a real mouse (not on touch screens). The pure parts are unit-tested in tests/js/fx.test.mjs.

/** Rotation in degrees for a pointer at (x, y) inside a box: the card leans towards the pointer, at most `max` degrees on each axis. */
export function tiltAngles(x, y, width, height, max = 7) {
    if (!(width > 0) || !(height > 0)) return { rx: 0, ry: 0 };
    const nx = Math.min(1, Math.max(0, x / width)) * 2 - 1;     // -1 (left) … 1 (right)
    const ny = Math.min(1, Math.max(0, y / height)) * 2 - 1;    // -1 (top) … 1 (bottom)
    return { rx: Math.round(-ny * max * 100) / 100 || 0, ry: Math.round(nx * max * 100) / 100 || 0 };   // || 0 turns -0 into 0
}

/** The section being read: the last one whose top has passed `line` pixels from the top of the window (the first one when none has). */
export function activeIndex(tops, line) {
    let active = 0;
    tops.forEach((top, i) => { if (top <= line) active = i; });
    return active;
}

const calm = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
const mouse = () => window.matchMedia?.('(hover: hover) and (pointer: fine)').matches;

/** Make every element matching `selector` lean towards the mouse, with a soft light that follows it. */
export function initTilt(selector, { max = 7 } = {}) {
    if (calm() || !mouse()) return;
    document.querySelectorAll(selector).forEach((el) => {
        el.classList.add('fx-tilt');
        el.addEventListener('pointermove', (e) => {
            const r = el.getBoundingClientRect();
            const { rx, ry } = tiltAngles(e.clientX - r.left, e.clientY - r.top, r.width, r.height, max);
            el.style.setProperty('--rx', `${rx}deg`);
            el.style.setProperty('--ry', `${ry}deg`);
            el.style.setProperty('--lx', `${((e.clientX - r.left) / r.width) * 100}%`);
            el.style.setProperty('--ly', `${((e.clientY - r.top) / r.height) * 100}%`);
            el.classList.add('is-tilting');
        });
        el.addEventListener('pointerleave', () => {
            el.style.setProperty('--rx', '0deg');
            el.style.setProperty('--ry', '0deg');
            el.classList.remove('is-tilting');
        });
    });
}

/** Let the elements rise into view when they are scrolled to, one after another (`--d` is the stagger). */
export function initReveal(selector) {
    const els = [...document.querySelectorAll(selector)];
    if (calm() || !('IntersectionObserver' in window)) return;
    const io = new IntersectionObserver((entries) => {
        entries.forEach((en) => {
            if (!en.isIntersecting) return;
            en.target.classList.add('in');
            io.unobserve(en.target);
        });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.06 });
    els.forEach((el, i) => {
        el.classList.add('fx-reveal');
        el.style.setProperty('--d', `${(i % 4) * 70}ms`);
        io.observe(el);
    });
}

/** The jump bar: clicking scrolls smoothly, and the link of the section being read is highlighted. */
export function initJumpBar(nav) {
    if (!nav) return;
    const links = [...nav.querySelectorAll('a[data-sec]')];
    const targets = links.map((a) => document.getElementById(a.dataset.sec));
    const paint = () => {
        const tops = targets.map((t) => (t ? t.getBoundingClientRect().top : Infinity));
        const atEnd = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4;
        const on = atEnd ? links.length - 1 : activeIndex(tops, window.innerHeight * 0.35);
        links.forEach((a, i) => a.classList.toggle('active', i === on));
    };
    let queued = false;
    window.addEventListener('scroll', () => {
        if (queued) return;
        queued = true;
        requestAnimationFrame(() => { queued = false; paint(); });
    }, { passive: true });
    paint();
}

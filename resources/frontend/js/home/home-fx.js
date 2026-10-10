// Home page polish (decoration only, skipped for reduced motion / touch where noted):
//  1. spotlight + glowing border that follow the pointer on the cards and the heat-map tiles
//  2. a sliding "ink" under the active tab of every tablist (spring easing in CSS)
// No framework: the pure geometry helpers are unit-tested in tests/js/homefx.test.mjs, the DOM parts only apply them.

/** Pointer position inside a box, in pixels (clamped to the box so a fast exit never leaves the glow far outside). */
export function glowPosition(clientX, clientY, rect) {
    const x = Math.min(rect.width, Math.max(0, clientX - rect.left));
    const y = Math.min(rect.height, Math.max(0, clientY - rect.top));
    return { x: Math.round(x * 10) / 10, y: Math.round(y * 10) / 10 };
}

/** Where the ink goes for `tab` inside `list` (offsets relative to the list's padding box). Null for a missing or hidden tab. */
export function inkBox(tab, list) {
    if (!tab || !tab.offsetWidth) return null;
    return { x: tab.offsetLeft, y: tab.offsetTop, w: tab.offsetWidth, h: tab.offsetHeight, scrollLeft: list.scrollLeft || 0 };
}

const calm = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
const ACTIVE_TAB = '[role="tab"].active, a.active';
const fineMouse = () => window.matchMedia?.('(hover: hover) and (pointer: fine)').matches;

/** Linear interpolation: the fraction `t` (0..1) of the way from `from` to `to`. */
export function lerp(from, to, t) { return from + (to - from) * t; }

/** Pull of a magnetic control: the pointer's offset from its centre, scaled and capped; nothing beyond `radius`. */
export function magnetOffset(dx, dy, radius = 90, strength = 0.28, cap = 12) {
    if (Math.hypot(dx, dy) > radius) return { x: 0, y: 0 };
    const c = (v) => Math.max(-cap, Math.min(cap, v * strength));
    return { x: Math.round(c(dx) * 10) / 10, y: Math.round(c(dy) * 10) / 10 };
}

/** A Vietnamese-formatted number ("1.735,09", "-3,88") as a JS number; NaN when it is not one. */
export function parseVi(text) {
    const t = String(text ?? '').replace(/[^\d,.\-]/g, '').replace(/\./g, '').replace(',', '.');
    return t === '' || t === '-' ? NaN : Number(t);
}

/** 'up' | 'down' for a displayed figure that moved from `prev` to `next`; null when it did not move or is not numeric. */
export function rollDirection(prev, next) {
    const a = parseVi(prev);
    const b = parseVi(next);
    if (!Number.isFinite(a) || !Number.isFinite(b) || a === b) return null;
    return b > a ? 'up' : 'down';
}

const CARDS = '.mk-card, .mk-idx, .stock-card, .news-card, .info-section';

/** One delegated listener: cards get a ring + spotlight (inserted lazily), heat-map tiles just get the pointer variables. */
export function initSpotlight(selector = CARDS) {
    if (calm() || !fineMouse()) return;
    let frame = 0;
    let last = null;

    const ensureLayers = (card) => {
        if (card.querySelector(':scope > .fx-ring')) return;
        const spot = document.createElement('i');
        spot.className = 'fx-spot';
        spot.setAttribute('aria-hidden', 'true');
        const ring = document.createElement('i');
        ring.className = 'fx-ring';
        ring.setAttribute('aria-hidden', 'true');
        card.prepend(spot, ring);
        card.classList.add('fx-glow');
    };

    document.addEventListener('pointermove', (e) => {
        last = e;
        if (frame) return;
        frame = requestAnimationFrame(() => {
            frame = 0;
            const target = last.target.closest?.(`${selector}, .hm-tile`);
            if (!target) return;
            if (!target.classList.contains('hm-tile')) ensureLayers(target);
            const { x, y } = glowPosition(last.clientX, last.clientY, target.getBoundingClientRect());
            target.style.setProperty('--mx', `${x}px`);
            target.style.setProperty('--my', `${y}px`);
        });
    }, { passive: true });
}

/** Add a sliding indicator to a tablist that tabs.js drives (it toggles `.active`; we only follow it). */
export function initTabInk(list) {
    if (!list) return;
    const ink = document.createElement('span');
    ink.className = 'fx-ink';
    ink.setAttribute('aria-hidden', 'true');
    list.classList.add(list.classList.contains('mk-side-tabs') ? 'has-ink-line' : 'has-ink');
    if (list.classList.contains('home-jump')) list.classList.add('is-jump');
    list.prepend(ink);

    const place = (animate) => {
        const box = inkBox(list.querySelector(ACTIVE_TAB), list);
        if (!box) { ink.style.opacity = '0'; return; }
        if (!animate) ink.style.transition = 'none';
        ink.style.opacity = '1';
        ink.style.width = `${box.w}px`;
        ink.style.height = `${box.h}px`;
        ink.style.transform = `translate(${box.x}px, ${box.y}px)`;
        if (!animate) { void ink.offsetWidth; ink.style.transition = ''; }
    };

    place(false);
    new MutationObserver(() => place(!calm())).observe(list, { subtree: true, attributes: true, attributeFilter: ['class'] });
    if ('ResizeObserver' in window) new ResizeObserver(() => place(false)).observe(list);
    document.fonts?.ready.then(() => place(false));
}

/** The hero reacts to the mouse: the blobs drift towards the pointer at different depths (smoothed every frame). */
export function initHeroPointer() {
    const hero = document.querySelector('.hero-search');
    if (!hero || calm() || !fineMouse()) return;
    let tx = 0; let ty = 0; let x = 0; let y = 0; let running = false;
    const tick = () => {
        x = lerp(x, tx, 0.06);
        y = lerp(y, ty, 0.06);
        hero.style.setProperty('--hx', x.toFixed(3));
        hero.style.setProperty('--hy', y.toFixed(3));
        if (Math.abs(tx - x) > 0.002 || Math.abs(ty - y) > 0.002) requestAnimationFrame(tick); else running = false;
    };
    const go = () => { if (!running) { running = true; requestAnimationFrame(tick); } };
    hero.addEventListener('pointermove', (e) => {
        const r = hero.getBoundingClientRect();
        tx = ((e.clientX - r.left) / r.width - 0.5) * 2;      // -1 … 1
        ty = ((e.clientY - r.top) / r.height - 0.5) * 2;
        go();
    }, { passive: true });
    hero.addEventListener('pointerleave', () => { tx = 0; ty = 0; go(); });
}

/** Buttons and chips lean a few pixels towards the pointer when it is near (the independent `translate` property). */
export function initMagnetic(selector = '.search-btn, .hero-feats a, .mk-btn-primary, #aiChatOpenBtn') {
    if (calm() || !fineMouse()) return;
    document.addEventListener('pointermove', (e) => {
        document.querySelectorAll(selector).forEach((el) => {
            const r = el.getBoundingClientRect();
            if (!r.width || r.bottom < -100 || r.top > innerHeight + 100) return;
            const radius = Math.max(r.width, r.height) * 0.9 + 20;
            const { x, y } = magnetOffset(e.clientX - (r.left + r.width / 2), e.clientY - (r.top + r.height / 2), radius);
            el.style.translate = x || y ? `${x}px ${y}px` : '';
        });
    }, { passive: true });
}

/** The hero title letter by letter so each rides its own wave (the h1 keeps an aria-label with the whole name). */
export function initTitleWave() {
    const h1 = document.querySelector('.hero-title');
    if (!h1 || h1.dataset.waved) return;
    const node = [...h1.childNodes].find((n) => n.nodeType === 3 && n.textContent.trim());
    if (!node) return;
    const text = node.textContent.trim();
    const wrap = document.createElement('span');
    wrap.className = 'ttl';
    wrap.setAttribute('aria-hidden', 'true');
    [...text].forEach((ch, i) => {
        const s = document.createElement('span');
        s.className = 'ch';
        s.style.setProperty('--i', i);
        s.textContent = ch === ' ' ? ' ' : ch;
        wrap.appendChild(s);
    });
    h1.setAttribute('aria-label', text);
    h1.dataset.waved = '1';
    node.replaceWith(wrap);
}

/** A figure rolls in from below (it rose) or from above (it fell) once its text has settled on a new value. */
export function initRoll(selector = '.mk-idx-close') {
    if (calm()) return;
    document.querySelectorAll(selector).forEach((el) => {
        let settled = el.textContent;
        let timer = 0;
        new MutationObserver(() => {
            clearTimeout(timer);
            timer = setTimeout(() => {
                const dir = rollDirection(settled, el.textContent);
                settled = el.textContent;
                if (!dir) return;
                el.classList.remove('fx-roll-up', 'fx-roll-down');
                void el.offsetWidth;
                el.classList.add(dir === 'up' ? 'fx-roll-up' : 'fx-roll-down');
            }, 180);
        }).observe(el, { childList: true, characterData: true, subtree: true });
    });
}

/** Pause the hero's endless animations while it is off screen or the tab is hidden (saves the GPU). */
export function initHeroPause() {
    const hero = document.querySelector('.hero-search');
    if (!hero) return;
    let visible = true;
    const apply = () => hero.classList.toggle('is-paused', !visible || document.hidden);
    if ('IntersectionObserver' in window) new IntersectionObserver(([en]) => { visible = en.isIntersecting; apply(); }).observe(hero);
    document.addEventListener('visibilitychange', apply);
}

export function initHomeFx() {
    initHeroPause();
    initTitleWave();
    initHeroPointer();
    initMagnetic();
    initRoll();
    initSpotlight();
    ['mkSideTabs', 'mkViewTabs', 'exploreTabs', 'homeJump'].forEach((id) => initTabInk(document.getElementById(id)));
}

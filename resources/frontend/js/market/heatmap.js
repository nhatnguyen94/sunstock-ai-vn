// Market heat map on the home page: a treemap of the busiest stocks grouped by industry (size = traded value, colour = % change).
// Layout/colour maths is pure and lives in treemap.js; this file only draws it, animates changes and handles pointer/keyboard.
//
// Tiles and industry panels are absolutely positioned elements keyed by symbol / industry, so when the exchange filter, the
// zoom or a fresh snapshot changes the layout the elements are MOVED (CSS transition) instead of being rebuilt.

import { esc, exchangeLabel, fmtInt, fmtPct, fmtValue, fmtVolume } from './format.js';
import { LEGEND_STOPS, groupItems, labelFor, layoutGroups, tileColor } from './treemap.js';

const reduceMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
const px = (n) => `${Math.round(n * 10) / 10}px`;

const STATE_KEY = 'sunstock-heatmap';

export function createHeatmap({ stage, tip, legend, back, chips, summary, data, card, toggle }) {
    let items = data?.items || [];
    let exchange = 'ALL';
    let focus = null;          // industry name while zoomed in
    let first = true;

    const tiles = new Map();   // symbol -> element
    const empty = document.createElement('div');   // shown when a filter leaves nothing (an older snapshot has no exchange per stock yet)
    empty.className = 'hm-empty';
    empty.textContent = 'Chưa có dữ liệu cho bộ lọc này. Dữ liệu sẽ có sau lần cập nhật thị trường kế tiếp.';
    empty.hidden = true;
    stage.appendChild(empty);
    const panels = new Map();  // industry -> element

    if (legend) legend.style.background = `linear-gradient(90deg, ${LEGEND_STOPS.map(tileColor).join(', ')})`;

    function tileEl(item) {
        const a = document.createElement('a');
        a.className = 'hm-tile';
        a.href = `/stock?symbol=${encodeURIComponent(item.s)}`;
        a.dataset.s = item.s;
        a.innerHTML = '<span class="hm-sym"></span><span class="hm-pct"></span><span class="hm-val"></span>';
        stage.appendChild(a);
        tiles.set(item.s, a);
        return a;
    }

    function panelEl(name) {
        const d = document.createElement('div');
        d.className = 'hm-group';
        d.innerHTML = '<button type="button" class="hm-gh"><span class="hm-gn"></span><em class="hm-gp"></em></button>';
        d.querySelector('.hm-gh').addEventListener('click', () => setFocus(d.dataset.name));
        d.dataset.name = name;
        stage.appendChild(d);
        panels.set(name, d);
        return d;
    }

    function setFocus(name) {
        focus = name === focus ? null : name;
        if (back) back.hidden = focus === null;
        render();
    }

    function render({ flashChanges = false } = {}) {
        const width = stage.clientWidth;
        const height = stage.clientHeight;
        if (!width || !height) return;

        let groups = groupItems(items, exchange);
        if (focus) {
            groups = groups.filter((g) => g.name === focus);
            if (!groups.length) { focus = null; if (back) back.hidden = true; groups = groupItems(items, exchange); }
        }
        const layout = layoutGroups(groups, { width, height, gap: groups.length === 1 ? 0 : 4, header: 22 });

        empty.hidden = groups.length > 0;
        const seenTiles = new Set();
        const seenPanels = new Set();
        let order = 0;

        for (const L of layout) {
            seenPanels.add(L.group.name);
            const panel = panels.get(L.group.name) || panelEl(L.group.name);
            panel.style.cssText = `left:${px(L.rect.x)};top:${px(L.rect.y)};width:${px(L.rect.w)};height:${px(L.rect.h)}`;
            panel.classList.toggle('has-header', L.header);
            panel.querySelector('.hm-gn').textContent = L.group.name;
            const gp = panel.querySelector('.hm-gp');
            gp.textContent = fmtPct(L.group.percent);
            gp.className = 'hm-gp ' + (L.group.percent > 0.05 ? 'up' : L.group.percent < -0.05 ? 'down' : 'flat');
            panel.querySelector('.hm-gh').title = focus ? 'Quay lại toàn bộ ngành' : `Phóng to ngành ${L.group.name}`;

            for (const T of L.tiles) {
                const it = T.item;
                seenTiles.add(it.s);
                const isNew = !tiles.has(it.s);
                const el = tiles.get(it.s) || tileEl(it);
                const lab = labelFor(T.rect);
                const prev = el._c;

                el.style.cssText = `left:${px(T.rect.x)};top:${px(T.rect.y)};width:${px(T.rect.w)};height:${px(T.rect.h)};background:${tileColor(it.c)};--fs:${lab.size}px`;
                el.dataset.level = String(lab.level);
                el.children[0].textContent = it.s;
                el.children[1].textContent = fmtPct(it.c);
                el.children[2].textContent = fmtValue(it.v);
                el.setAttribute('aria-label', `${it.s}, ${fmtPct(it.c)}, giá ${fmtInt(it.p)} đồng, giá trị giao dịch ${fmtValue(it.v)}`);
                el._item = it;
                el._c = it.c;

                if (isNew && first && !reduceMotion()) {
                    el.classList.add('hm-enter');
                    el.style.animationDelay = `${Math.min(order * 5, 650)}ms`;
                    el.addEventListener('animationend', () => { el.classList.remove('hm-enter'); el.style.animationDelay = ''; }, { once: true });
                } else if (flashChanges && prev !== undefined && prev !== it.c && !reduceMotion()) {
                    el.classList.remove('hm-blink');
                    void el.offsetWidth;   // restart the animation
                    el.classList.add('hm-blink');
                }
                order++;
            }
        }

        // anything that left the picture (filtered out, dropped from the top 150) fades away
        for (const [s, el] of tiles) {
            if (!seenTiles.has(s)) { el.remove(); tiles.delete(s); }
        }
        for (const [n, el] of panels) {
            if (!seenPanels.has(n)) { el.remove(); panels.delete(n); }
        }
        first = false;

        if (summary) {
            const shown = groups.flatMap((g) => g.items);
            const up = shown.filter((t) => t.c > 0).length;
            const down = shown.filter((t) => t.c < 0).length;
            const text = `${shown.length} mã · ${up} tăng · ${down} giảm · ${fmtValue(shown.reduce((t, i) => t + i.v, 0))} ₫`;
            [].concat(summary).forEach((node) => { if (node) node.textContent = text; });
        }
    }

    // ── tooltip ──────────────────────────────────────────────────────────────
    function showTip(el, x, y) {
        const it = el?._item;
        if (!it || !tip) return hideTip();
        const d = it.c > 0 ? 'up' : it.c < 0 ? 'down' : 'flat';
        tip.innerHTML = `<div class="hm-tip-top"><b>${esc(it.s)}</b><span class="${d}">${esc(fmtPct(it.c))}</span></div>`
            + `<div class="hm-tip-name">${esc(it.n || '')}</div>`
            + `<div class="hm-tip-meta">${esc(it.i)}${it.x ? ' · ' + esc(exchangeLabel(it.x)) : ''}</div>`
            + `<dl><dt>Giá</dt><dd>${esc(fmtInt(it.p))} ₫</dd><dt>GTGD</dt><dd>${esc(fmtValue(it.v))} ₫</dd><dt>KLGD</dt><dd>${esc(fmtVolume(it.q))}</dd></dl>`;
        tip.hidden = false;
        const box = stage.getBoundingClientRect();
        const tw = tip.offsetWidth;
        const th = tip.offsetHeight;
        const left = Math.min(Math.max(8, x - box.left + 14), Math.max(8, box.width - tw - 8));
        const top = Math.min(Math.max(8, y - box.top + 14), Math.max(8, box.height - th - 8));
        tip.style.transform = `translate(${Math.round(left)}px, ${Math.round(top)}px)`;
    }
    const hideTip = () => { if (tip) tip.hidden = true; };

    stage.addEventListener('pointermove', (e) => {
        if (e.pointerType === 'touch') return;
        const el = e.target.closest?.('.hm-tile');
        if (el) showTip(el, e.clientX, e.clientY); else hideTip();
    });
    stage.addEventListener('pointerleave', hideTip);
    stage.addEventListener('focusin', (e) => {
        const el = e.target.closest?.('.hm-tile');
        if (!el) return;
        const r = el.getBoundingClientRect();
        showTip(el, r.left + r.width / 2, r.top + r.height / 2);
    });
    stage.addEventListener('focusout', hideTip);

    // ── controls ─────────────────────────────────────────────────────────────
    chips?.addEventListener('click', (e) => {
        const b = e.target.closest('button[data-ex]');
        if (!b) return;
        exchange = b.dataset.ex;
        chips.querySelectorAll('button').forEach((x) => x.classList.toggle('active', x === b));
        render();
    });
    back?.addEventListener('click', () => setFocus(null));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && focus) setFocus(null); });

    // ── collapse / expand (the choice is remembered; the partial applies it before the first paint) ──
    toggle?.addEventListener('click', () => {
        const collapsed = card.classList.toggle('is-collapsed');
        toggle.setAttribute('aria-expanded', String(!collapsed));
        if (collapsed) hideTip();
        try { localStorage.setItem(STATE_KEY, collapsed ? 'closed' : 'open'); } catch (e) { /* not remembered in private mode */ }
    });
    card?.querySelector('.mk-card-head h3')?.addEventListener('click', (e) => {
        if (e.target.closest('.mk-heat-toggle')) return;   // the button handles itself
        toggle?.click();
    });

    let raf = 0;
    const relayout = () => { cancelAnimationFrame(raf); raf = requestAnimationFrame(() => render()); };
    if ('ResizeObserver' in window) new ResizeObserver(relayout).observe(stage); else window.addEventListener('resize', relayout);

    render();

    return {
        /** A fresh snapshot arrived: move/recolour the tiles and blink the ones that changed. */
        update(next) {
            if (!next?.items) return;
            items = next.items;
            render({ flashChanges: true });
        },
    };
}

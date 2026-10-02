// Global command palette: Ctrl/⌘+K (or "/" outside a field, or any [data-palette-open] button) opens a box that jumps to a
// stock, a page, or hands a question to the AI chat. Logic that does not need the DOM is in palette-core.js.

import { buildSections, flatten, highlight, moveSelection, pushRecent } from './palette-core.js';

const RECENT_KEY = 'sunstock-recent';

const readRecent = () => {
    try {
        const v = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');
        return Array.isArray(v) ? v.filter((x) => typeof x === 'string').slice(0, 5) : [];
    } catch (e) {
        return [];
    }
};
const writeRecent = (list) => {
    try { localStorage.setItem(RECENT_KEY, JSON.stringify(list)); } catch (e) { /* private mode: not remembered */ }
};

const el = (tag, cls, text) => {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined) n.textContent = text;
    return n;
};

export function initPalette() {
    const root = document.getElementById('cmdPalette');
    const dataNode = document.getElementById('paletteData');
    if (!root || !dataNode) return;

    let pages = [];
    try { pages = JSON.parse(dataNode.textContent).pages || []; } catch (e) { pages = []; }

    const input = root.querySelector('#cpInput');
    const list = root.querySelector('#cpList');
    const hl = el('div', 'cp-hl');          // the highlight that glides to the selected row
    hl.setAttribute('aria-hidden', 'true');

    let stocks = [];
    let query = '';
    let flat = [];
    let selected = 0;
    let opener = null;
    let timer = 0;
    let controller = null;

    const isOpen = () => !root.hidden;

    // ── rendering ────────────────────────────────────────────────────────────
    function paintSelection(smooth = true) {
        const rows = list.querySelectorAll('.cp-item');
        rows.forEach((r, i) => {
            const on = i === selected;
            r.classList.toggle('is-selected', on);
            r.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        const row = rows[selected];
        if (!row) { hl.style.opacity = '0'; input.removeAttribute('aria-activedescendant'); return; }
        input.setAttribute('aria-activedescendant', row.id);
        if (!smooth) hl.style.transition = 'none';
        hl.style.opacity = '1';
        hl.style.height = `${row.offsetHeight}px`;
        hl.style.transform = `translateY(${row.offsetTop}px)`;
        if (!smooth) { void hl.offsetWidth; hl.style.transition = ''; }
        row.scrollIntoView({ block: 'nearest' });
    }

    function markup(text) {
        const frag = document.createDocumentFragment();
        for (const part of highlight(text, query)) {
            frag.appendChild(part.hit ? el('mark', '', part.text) : document.createTextNode(part.text));
        }
        return frag;
    }

    function render() {
        const sections = buildSections({ query, pages, stocks, recent: readRecent() });
        flat = flatten(sections);
        list.textContent = '';
        list.appendChild(hl);

        let index = 0;
        for (const section of sections) {
            const head = el('li', 'cp-head', section.title);
            head.setAttribute('role', 'presentation');
            list.appendChild(head);
            for (const item of section.items) {
                const i = index++;
                const li = el('li', 'cp-item');
                li.id = `cpItem${i}`;
                li.setAttribute('role', 'option');
                li.style.setProperty('--i', String(i));
                li.dataset.index = String(i);

                const icon = el('span', 'cp-icon');
                if (item.kind === 'stock' || item.kind === 'recent') {
                    icon.classList.add('is-ticker');
                    icon.textContent = (item.symbol || '').slice(0, 4);
                } else {
                    icon.innerHTML = '<i class="bi bi-' + (item.icon || 'arrow-right') + '"></i>';
                }
                const body = el('span', 'cp-body');
                const title = el('span', 'cp-title');
                title.appendChild(item.kind === 'ai' ? document.createTextNode(item.title) : markup(item.title));
                body.appendChild(title);
                if (item.sub) {
                    const sub = el('span', 'cp-sub');
                    sub.appendChild(markup(item.sub));
                    body.appendChild(sub);
                }
                li.append(icon, body);
                if (item.kind === 'stock' && item.exchange) li.appendChild(el('span', 'cp-tag', item.exchange === 'HSX' ? 'HOSE' : item.exchange));
                if (item.kind === 'page') li.appendChild(el('i', 'bi bi-arrow-return-left cp-go'));
                list.appendChild(li);
            }
        }

        selected = Math.min(selected, Math.max(flat.length - 1, 0));
        paintSelection(false);
    }

    // ── actions ──────────────────────────────────────────────────────────────
    function openAi(text) {
        const open = document.getElementById('aiChatOpenBtn');
        const field = document.getElementById('aiChatInput');
        if (!open || !field) return;
        if (open.getAttribute('aria-expanded') !== 'true') open.click();
        field.value = text;
        setTimeout(() => { field.focus(); field.setSelectionRange(field.value.length, field.value.length); }, 80);
    }

    function activate(item) {
        if (!item) return;
        if (item.kind === 'stock' || item.kind === 'recent') {
            writeRecent(pushRecent(readRecent(), item.symbol));
            close(false);
            window.location.href = `/stock?symbol=${encodeURIComponent(item.symbol)}`;
        } else if (item.kind === 'page') {
            close(false);
            window.location.href = item.url;
        } else if (item.kind === 'ai') {
            close();
            openAi(item.query);
        }
    }

    // ── stock search (the same endpoint as the home search box) ───────────────
    function searchStocks(q) {
        clearTimeout(timer);
        if (controller) controller.abort();
        if (!q.trim()) { stocks = []; render(); return; }
        timer = setTimeout(async () => {
            controller = new AbortController();
            try {
                const res = await fetch(`/stocks-list?q=${encodeURIComponent(q.trim())}`, { signal: controller.signal, headers: { Accept: 'application/json' } });
                const rows = res.ok ? await res.json() : [];
                if (q === query) { stocks = Array.isArray(rows) ? rows.slice(0, 6) : []; selected = 0; render(); }
            } catch (e) { /* aborted by a newer keystroke, or offline: keep what is listed */ }
        }, 140);
    }

    // ── open / close ─────────────────────────────────────────────────────────
    function open() {
        if (isOpen()) return;
        opener = document.activeElement;
        query = '';
        stocks = [];
        selected = 0;
        input.value = '';
        root.hidden = false;
        document.body.classList.add('cp-lock');
        requestAnimationFrame(() => root.classList.add('is-open'));
        render();
        setTimeout(() => input.focus(), 30);
    }

    function close(restoreFocus = true) {
        if (!isOpen()) return;
        clearTimeout(timer);
        controller?.abort();
        root.classList.remove('is-open');
        document.body.classList.remove('cp-lock');
        root.hidden = true;
        if (restoreFocus && opener?.focus) opener.focus();
    }

    input.addEventListener('input', () => {
        query = input.value;
        selected = 0;
        searchStocks(query);
        render();
    });

    root.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { e.preventDefault(); close(); return; }
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            selected = moveSelection(selected, e.key === 'ArrowDown' ? 1 : -1, flat.length);
            paintSelection();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            activate(flat[selected]);
        } else if (e.key === 'Tab') {
            e.preventDefault();   // the input is the only thing to focus: keep the focus inside the dialog
        }
    });

    list.addEventListener('mousemove', (e) => {
        const row = e.target.closest('.cp-item');
        if (row && Number(row.dataset.index) !== selected) { selected = Number(row.dataset.index); paintSelection(); }
    });
    list.addEventListener('click', (e) => {
        const row = e.target.closest('.cp-item');
        if (row) activate(flat[Number(row.dataset.index)]);
    });
    root.querySelector('[data-cp-close]')?.addEventListener('click', () => close());

    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-palette-open]')) { e.preventDefault(); open(); }
    });
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); isOpen() ? close() : open(); return; }
        const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement?.tagName || '') || document.activeElement?.isContentEditable;
        if (e.key === '/' && !typing && !isOpen() && !e.ctrlKey && !e.metaKey && !e.altKey) { e.preventDefault(); open(); }
    });
}

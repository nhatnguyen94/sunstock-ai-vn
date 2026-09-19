// Stock-symbol autocomplete used by the home search, the stock page search and the compare page.
//
// Awesomplete (bundled from npm — no CDN) does the keyboard/ARIA work; this module adds what the old
// per-page copies each did slightly differently and slightly wrong:
//   * the row markup is built with DOM nodes (textContent) — never HTML strings, so a company name can't inject markup
//     and typing "b" or "span" can't corrupt the highlight (Awesomplete's default `<mark>` replace runs on raw HTML)
//   * the matched part of the symbol / name is highlighted with a soft mark instead of Awesomplete's neon yellow
//   * mouse hover and keyboard selection are ONE highlight (hovering moves the selection) instead of two
//     competing colours at once
//   * debounced fetch with AbortController, so a slow earlier response can never overwrite a newer one
//   * loading spinner + clear (×) button on the input

import Awesomplete from 'awesomplete';

const norm = (s) => String(s ?? '').toLowerCase();

/** Append `text` to `parent`, wrapping the first occurrence of `query` in <mark>. */
function appendHighlighted(parent, text, query) {
    const i = query ? norm(text).indexOf(norm(query)) : -1;
    if (i < 0) {
        parent.append(document.createTextNode(text));
        return;
    }
    const mark = document.createElement('mark');
    mark.textContent = text.slice(i, i + query.length);
    parent.append(document.createTextNode(text.slice(0, i)), mark, document.createTextNode(text.slice(i + query.length)));
}

/**
 * @param {HTMLInputElement} input
 * @param {object}  [opts]
 * @param {number}  [opts.maxItems=8]
 * @param {number}  [opts.debounce=200]
 * @param {(symbol:string)=>void} [opts.onPick]      called after a suggestion is chosen (mouse click or Enter)
 * @param {(results:Array)=>void} [opts.onResults]   called with every fetched list (e.g. to show a "not found" note)
 * @returns {Awesomplete}
 */
export function stockAutocomplete(input, { maxItems = 8, debounce = 200, onPick, onResults } = {}) {
    const meta = new Map();   // symbol -> { symbol, name, exchange }
    let query = '';

    const awe = new Awesomplete(input, {
        minChars: 1,
        maxItems,
        autoFirst: true,
        list: [],
        filter: () => true,   // the server already filtered; Awesomplete's client filter would hide name-only matches
        sort: false,          // and the server already ranked (exact symbol → prefix → contains → name)
        replace(suggestion) {
            this.input.value = suggestion.value;
        },
        item(suggestion) {
            const info = meta.get(suggestion.value) || { symbol: suggestion.value, name: '' };
            const li = document.createElement('li');
            li.setAttribute('role', 'option');
            li.setAttribute('aria-selected', 'false');

            const chip = document.createElement('span');
            chip.className = 'ac-symbol';
            appendHighlighted(chip, info.symbol, query);

            const body = document.createElement('span');
            body.className = 'ac-body';
            const name = document.createElement('span');
            name.className = 'ac-name';
            appendHighlighted(name, info.name || '', query);
            body.append(name);

            li.append(chip, body);
            if (info.exchange) {
                const ex = document.createElement('span');
                ex.className = 'ac-exchange';
                ex.textContent = info.exchange;
                li.append(ex);
            }
            return li;
        },
    });

    const wrapper = input.closest('.awesomplete');

    // ── Clear (×) button ────────────────────────────────────────────────────
    const clear = document.createElement('button');
    clear.type = 'button';
    clear.className = 'ac-clear';
    clear.setAttribute('aria-label', 'Xóa nội dung tìm kiếm');
    clear.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';
    wrapper.append(clear);
    const syncClear = () => wrapper.classList.toggle('has-value', input.value.length > 0);
    clear.addEventListener('mousedown', (e) => e.preventDefault());   // keep focus in the input
    clear.addEventListener('click', () => {
        input.value = '';
        awe.close();
        syncClear();
        input.focus();
    });
    syncClear();

    // ── One highlight: hovering moves the selection (without Awesomplete's scroll jump) ──
    awe.ul.addEventListener('mousemove', (e) => {
        const li = e.target.closest('li');
        if (!li || !awe.ul.contains(li)) return;
        const items = [...awe.ul.children];
        const idx = items.indexOf(li);
        if (idx < 0 || idx === awe.index) return;
        if (awe.index > -1 && items[awe.index]) items[awe.index].setAttribute('aria-selected', 'false');
        awe.index = idx;
        li.setAttribute('aria-selected', 'true');
        input.setAttribute('aria-activedescendant', li.id || '');
    });

    // ── Fetch (debounced, cancellable) ──────────────────────────────────────
    let timer = null;
    let controller = null;

    input.addEventListener('input', (e) => {
        syncClear();
        if (e.isTrusted === false) return;   // programmatic value set by a selection

        clearTimeout(timer);
        controller?.abort();
        query = input.value.trim();

        if (!query) {
            wrapper.classList.remove('is-loading');
            awe.list = [];
            awe.close();
            onResults?.(null);
            return;
        }

        wrapper.classList.add('is-loading');
        timer = setTimeout(async () => {
            controller = new AbortController();
            try {
                const res = await fetch('/stocks-list?q=' + encodeURIComponent(query), {
                    signal: controller.signal,
                    headers: { Accept: 'application/json' },
                });
                const data = await res.json();
                meta.clear();
                data.forEach((d) => meta.set(d.symbol, d));
                awe.list = data.map((d) => ({ label: d.symbol, value: d.symbol }));
                onResults?.(data);
            } catch (err) {
                if (err.name === 'AbortError') return;   // superseded by a newer keystroke
                onResults?.([]);
            } finally {
                if (!controller.signal.aborted) wrapper.classList.remove('is-loading');
            }
        }, debounce);
    });

    input.addEventListener('awesomplete-selectcomplete', () => {
        syncClear();
        onPick?.(input.value);
    });

    return awe;
}

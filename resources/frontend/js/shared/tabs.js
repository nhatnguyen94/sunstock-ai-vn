// Accessible tabs (the WAI-ARIA tabs pattern) for the home page: the market side card, the heat map / VN-Index switch, the Explore card.
//
// Markup convention: a container with `role="tablist"` holding `role="tab"` buttons, each with `aria-controls="<panel id>"`; the panels are
// `role="tabpanel"` elements. Several tabs may control the same panel (the movers list is shared by Tăng / Giảm / Thanh khoản): the panel
// stays visible while one of its tabs is selected. The pure keyboard rule is unit-tested in tests/js/tabs.test.mjs.

/** Which tab a key moves to: arrows wrap around, Home / End jump to the ends, anything else stays where it is. */
export function nextIndex(count, current, key) {
    if (count <= 0) return -1;
    switch (key) {
        case 'ArrowRight': return (current + 1) % count;
        case 'ArrowLeft': return (current - 1 + count) % count;
        case 'Home': return 0;
        case 'End': return count - 1;
        default: return current;
    }
}

/**
 * Wire one tablist. `onChange(tab)` runs after a tab is selected by a click or the keyboard (not for `select(..., {silent: true})`).
 * @returns {{select: (tab: HTMLElement, opts?: {focus?: boolean, silent?: boolean}) => void, tabs: HTMLElement[]}|null}
 */
export function initTabs(list, { onChange } = {}) {
    if (!list) return null;
    const tabs = [...list.querySelectorAll('[role="tab"]')];
    if (!tabs.length) return null;
    const panels = [...new Set(tabs.map((t) => t.getAttribute('aria-controls')))].map((id) => document.getElementById(id)).filter(Boolean);

    function select(tab, { focus = false, silent = false } = {}) {
        const target = document.getElementById(tab.getAttribute('aria-controls'));
        tabs.forEach((t) => {
            const on = t === tab;
            t.classList.toggle('active', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            t.tabIndex = on ? 0 : -1;
        });
        panels.forEach((p) => { p.hidden = p !== target; });
        if (focus) tab.focus();
        if (!silent) onChange?.(tab);
    }

    tabs.forEach((tab, i) => {
        tab.addEventListener('click', () => select(tab));
        tab.addEventListener('keydown', (e) => {
            const n = nextIndex(tabs.length, i, e.key);
            if (n !== i) {
                e.preventDefault();
                select(tabs[n], { focus: true });
            }
        });
    });

    return { select, tabs };
}

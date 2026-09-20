// Pure helpers for the admin shell (no DOM, no Tabler): unit-tested in Node.

const fold = (s) => String(s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'D').toLowerCase();

/** Rank palette items for a query (accent-insensitive: "quan ly" finds "Quản lý"). Empty query keeps the order. */
export function filterCommands(items, query) {
    const q = fold(query).trim();
    if (!q) return items.slice();
    const words = q.split(/\s+/);

    return items
        .map((it) => {
            const hay = fold(`${it.title} ${it.group || ''} ${it.keywords || ''}`);
            if (!words.every((w) => hay.includes(w))) return null;
            const title = fold(it.title);
            const score = (title.startsWith(q) ? 0 : title.includes(q) ? 1 : 2) + (it.rank || 0) * 0.01;
            return { it, score };
        })
        .filter(Boolean)
        .sort((a, b) => a.score - b.score)
        .map((x) => x.it);
}

/** Keep the selection inside the list when it wraps around with the arrow keys. */
export function moveSelection(current, delta, length) {
    if (length <= 0) return -1;
    return (current + delta + length) % length;
}

/** 'info' | 'success' | 'warning' | 'error' + its Tabler icon. */
export function toastIcon(type) {
    return { success: 'ti-circle-check', error: 'ti-alert-circle', warning: 'ti-alert-triangle', info: 'ti-info-circle' }[type] || 'ti-info-circle';
}

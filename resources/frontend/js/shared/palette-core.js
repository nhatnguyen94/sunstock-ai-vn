// Pure logic of the global command palette (Ctrl+K): what to list for a query, in which order, and how to mark the match.
// No DOM: unit-tested in tests/js/palette.test.mjs. The DOM side is shared/palette.js.
//
// Items: {kind: 'stock'|'page'|'ai'|'recent', title, sub?, icon?, url?, symbol?, query?}

import { filterCommands, moveSelection } from '../admin/helpers.js';

export { moveSelection };

export const fold = (s) => String(s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'D').toLowerCase();

/** Split `text` into [{text, hit}] so the part matching `query` (accent- and case-insensitive) can be marked. */
export function highlight(text, query) {
    const t = String(text ?? '');
    const q = fold(query).trim();
    if (t === '') return [];
    if (!q) return [{ text: t, hit: false }];
    const f = fold(t);
    if (f.length !== t.length) return [{ text: t, hit: false }];   // decomposed accents shift the offsets: do not guess
    const at = f.indexOf(q);
    if (at < 0) return [{ text: t, hit: false }];

    return [
        { text: t.slice(0, at), hit: false },
        { text: t.slice(at, at + q.length), hit: true },
        { text: t.slice(at + q.length), hit: false },
    ].filter((p) => p.text !== '');
}

/** Most recent symbol first, no duplicates, at most `max`. */
export function pushRecent(list, symbol, max = 5) {
    const s = String(symbol ?? '').trim().toUpperCase();
    if (!s) return list.slice(0, max);

    return [s, ...list.filter((x) => x !== s)].slice(0, max);
}

/**
 * Sections for the palette.
 * @param {{query:string, pages:object[], stocks:object[], recent:string[], pageLimit?:number}} p
 * @returns {{title:string, items:object[]}[]}
 */
export function buildSections({ query, pages, stocks, recent, pageLimit = 5 }) {
    const q = String(query ?? '').trim();
    const sections = [];

    if (q === '') {
        if (recent.length) {
            sections.push({ title: 'Xem gần đây', items: recent.map((symbol) => ({ kind: 'recent', title: symbol, symbol, icon: 'clock-history' })) });
        }
        sections.push({ title: 'Đi tới', items: pages.map((p) => ({ kind: 'page', ...p })) });
        sections.push({ title: 'Hỏi AI', items: [{ kind: 'ai', title: 'Hỏi Sun Stock AI về thị trường', query: '', icon: 'robot' }] });

        return sections;
    }

    if (stocks.length) {
        sections.push({
            title: 'Cổ phiếu',
            items: stocks.map((s) => ({ kind: 'stock', title: s.symbol, sub: s.name || '', symbol: s.symbol, exchange: s.exchange || '', icon: 'graph-up' })),
        });
    }
    const matched = filterCommands(pages, q).slice(0, pageLimit);
    if (matched.length) sections.push({ title: 'Trang', items: matched.map((p) => ({ kind: 'page', ...p })) });
    sections.push({ title: 'Hỏi AI', items: [{ kind: 'ai', title: `Hỏi AI: “${q}”`, query: q, icon: 'robot' }] });

    return sections;
}

/** The sections' items as one list, in the order the arrow keys walk through them. */
export const flatten = (sections) => sections.flatMap((s) => s.items);

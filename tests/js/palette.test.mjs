import assert from 'node:assert/strict';
import { test } from 'node:test';

import { buildSections, flatten, fold, highlight, moveSelection, pushRecent } from '../../resources/frontend/js/shared/palette-core.js';

const pages = [
    { title: 'Trang chủ', icon: 'house-door', url: '/', keywords: 'home tong quan', group: 'Trang' },
    { title: 'Giá vàng', icon: 'coin', url: '/gold', keywords: 'vang sjc gold', group: 'Trang' },
    { title: 'Tỷ giá ngoại tệ', icon: 'currency-exchange', url: '/exchange-rate', keywords: 'ty gia usd vcb', group: 'Trang' },
    { title: 'Danh mục đầu tư', icon: 'briefcase', url: '/portfolio', keywords: 'portfolio', group: 'Trang' },
    { title: 'Tin tức', icon: 'newspaper', url: '/news', keywords: 'news', group: 'Trang' },
];

test('fold: accents, case and đ are ignored', () => {
    assert.equal(fold('Đầu tư Vàng'), 'dau tu vang');
    assert.equal(fold(null), '');
});

test('highlight: marks the matching part accent-insensitively and keeps the original text', () => {
    assert.deepEqual(highlight('Giá vàng', 'vang'), [{ text: 'Giá ', hit: false }, { text: 'vàng', hit: true }]);
    assert.deepEqual(highlight('VCB', 'vc'), [{ text: 'VC', hit: true }, { text: 'B', hit: false }]);
});

test('highlight: no match, empty query or odd input never throws and never invents a match', () => {
    assert.deepEqual(highlight('Giá vàng', 'zzz'), [{ text: 'Giá vàng', hit: false }]);
    assert.deepEqual(highlight('Giá vàng', '   '), [{ text: 'Giá vàng', hit: false }]);
    assert.deepEqual(highlight(undefined, 'a'), []);
});

test('highlight: text with decomposed accents is shown plain rather than mis-marked', () => {
    const decomposed = 'Vàng';                       // "Vàng" as a + combining grave
    assert.deepEqual(highlight(decomposed, 'vang'), [{ text: decomposed, hit: false }]);
});

test('highlight: markup in the text is returned as text (the DOM side uses textContent)', () => {
    const parts = highlight('<img src=x onerror=1>', 'img');
    assert.equal(parts.map((p) => p.text).join(''), '<img src=x onerror=1>');
});

test('pushRecent: newest first, no duplicates, upper-cased, capped', () => {
    assert.deepEqual(pushRecent(['FPT', 'VNM'], 'vnm'), ['VNM', 'FPT']);
    assert.deepEqual(pushRecent(['A', 'B', 'C', 'D', 'E'], 'F'), ['F', 'A', 'B', 'C', 'D']);
    assert.deepEqual(pushRecent(['FPT'], '  '), ['FPT']);
    assert.deepEqual(pushRecent([], 'acb', 3), ['ACB']);
});

test('moveSelection wraps in both directions and survives an empty list', () => {
    assert.equal(moveSelection(0, -1, 4), 3);
    assert.equal(moveSelection(3, 1, 4), 0);
    assert.equal(moveSelection(1, 1, 4), 2);
    assert.equal(moveSelection(0, 1, 0), -1);
});

test('buildSections with no query: recent stocks, every page, and an Ask-AI entry', () => {
    const s = buildSections({ query: '', pages, stocks: [], recent: ['FPT', 'VCB'] });

    assert.deepEqual(s.map((x) => x.title), ['Xem gần đây', 'Đi tới', 'Hỏi AI']);
    assert.deepEqual(s[0].items.map((i) => [i.kind, i.symbol]), [['recent', 'FPT'], ['recent', 'VCB']]);
    assert.equal(s[1].items.length, pages.length);
    assert.equal(s[2].items[0].kind, 'ai');
});

test('buildSections with no query and nothing recent has no "recent" section', () => {
    assert.deepEqual(buildSections({ query: '', pages, stocks: [], recent: [] }).map((x) => x.title), ['Đi tới', 'Hỏi AI']);
});

test('buildSections with a query: stocks first, matching pages next, Ask-AI always last', () => {
    const stocks = [{ symbol: 'VCB', name: 'Vietcombank', exchange: 'HSX' }];
    const s = buildSections({ query: 'vang', pages, stocks, recent: [] });

    assert.deepEqual(s.map((x) => x.title), ['Cổ phiếu', 'Trang', 'Hỏi AI']);
    assert.deepEqual(s[1].items.map((i) => i.title), ['Giá vàng']);
    assert.equal(s.at(-1).items[0].query, 'vang');
    assert.equal(s.at(-1).items[0].title, 'Hỏi AI: “vang”');
});

test('buildSections: page search is accent-insensitive and uses the keywords', () => {
    const titles = (q) => buildSections({ query: q, pages, stocks: [], recent: [] }).find((x) => x.title === 'Trang')?.items.map((i) => i.title);

    assert.deepEqual(titles('ty gia'), ['Tỷ giá ngoại tệ']);
    assert.deepEqual(titles('portfolio'), ['Danh mục đầu tư']);
    assert.deepEqual(titles('đầu tư'), ['Danh mục đầu tư']);
});

test('buildSections: a query that matches no page and no stock still offers the AI', () => {
    const s = buildSections({ query: 'xyz lạ', pages, stocks: [], recent: ['FPT'] });

    assert.deepEqual(s.map((x) => x.title), ['Hỏi AI']);
});

test('buildSections: stock items carry the symbol, name and exchange; the page limit is honoured', () => {
    const many = Array.from({ length: 12 }, (_, i) => ({ title: `Trang ${i}`, url: `/${i}`, keywords: 'trang', group: 'Trang' }));
    const s = buildSections({ query: 'trang', pages: many, stocks: [{ symbol: 'ABC', name: 'Công ty ABC', exchange: 'HNX' }], recent: [], pageLimit: 4 });

    assert.deepEqual(s[0].items[0], { kind: 'stock', title: 'ABC', sub: 'Công ty ABC', symbol: 'ABC', exchange: 'HNX', icon: 'graph-up' });
    assert.equal(s.find((x) => x.title === 'Trang').items.length, 4);
});

test('flatten lists the items in the order the arrow keys walk through them', () => {
    const s = buildSections({ query: 'vang', pages, stocks: [{ symbol: 'VNG', name: 'x', exchange: '' }], recent: [] });

    assert.deepEqual(flatten(s).map((i) => i.kind), ['stock', 'page', 'ai']);
});

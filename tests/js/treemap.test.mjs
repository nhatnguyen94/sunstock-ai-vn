import assert from 'node:assert/strict';
import { test } from 'node:test';

import { LEGEND_STOPS, groupItems, labelFor, layoutGroups, squarify, tileColor } from '../../resources/frontend/js/market/treemap.js';

const area = (r) => r.w * r.h;
const sum = (a) => a.reduce((t, n) => t + n, 0);
const near = (a, b, eps = 1e-6) => assert.ok(Math.abs(a - b) <= eps, `${a} !~ ${b}`);

const overlap = (a, b) => Math.min(a.x + a.w, b.x + b.w) - Math.max(a.x, b.x) > 1e-6 && Math.min(a.y + a.h, b.y + b.h) - Math.max(a.y, b.y) > 1e-6;

// ── squarify ────────────────────────────────────────────────────────────────

test('squarify: rectangle areas are proportional to the values and tile the whole box', () => {
    const values = [60, 30, 20, 10, 5, 5];
    const box = { x: 10, y: 20, w: 400, h: 250 };
    const rects = squarify(values, box);

    near(sum(rects.map(area)), box.w * box.h, 1e-3);
    rects.forEach((r, i) => near(area(r) / (box.w * box.h), values[i] / sum(values), 1e-6));
});

test('squarify: nothing overlaps and everything stays inside the box', () => {
    const values = [500, 300, 120, 90, 60, 40, 22, 10, 5, 3];
    const box = { x: 0, y: 0, w: 640, h: 360 };
    const rects = squarify(values, box);

    rects.forEach((r) => {
        assert.ok(r.x >= -1e-6 && r.y >= -1e-6 && r.x + r.w <= box.w + 1e-6 && r.y + r.h <= box.h + 1e-6, 'inside');
    });
    for (let i = 0; i < rects.length; i++) for (let j = i + 1; j < rects.length; j++) assert.equal(overlap(rects[i], rects[j]), false, `${i} overlaps ${j}`);
});

test('squarify: results keep the order of the input, whatever the sizes', () => {
    const rects = squarify([1, 100, 10], { x: 0, y: 0, w: 100, h: 100 });

    assert.ok(area(rects[1]) > area(rects[2]) && area(rects[2]) > area(rects[0]));
});

test('squarify: tiles are reasonably square (no needles) for a typical market', () => {
    const values = Array.from({ length: 40 }, (_, i) => 1000 / (i + 1));
    const rects = squarify(values, { x: 0, y: 0, w: 800, h: 450 });
    const worst = Math.max(...rects.map((r) => Math.max(r.w / r.h, r.h / r.w)));

    assert.ok(worst < 8, `worst aspect ratio ${worst}`);
});

test('squarify: a single value fills the box; zeros and negatives get an empty rectangle', () => {
    assert.deepEqual(squarify([5], { x: 1, y: 2, w: 30, h: 40 }), [{ x: 1, y: 2, w: 30, h: 40 }]);

    const rects = squarify([10, 0, -3, 10], { x: 0, y: 0, w: 100, h: 50 });
    assert.equal(area(rects[1]), 0);
    assert.equal(area(rects[2]), 0);
    near(area(rects[0]) + area(rects[3]), 5000, 1e-3);
});

test('squarify: degenerate input does not throw or produce NaN', () => {
    assert.deepEqual(squarify([], { x: 0, y: 0, w: 10, h: 10 }), []);
    for (const box of [{ x: 0, y: 0, w: 0, h: 10 }, { x: 0, y: 0, w: 10, h: -5 }]) {
        squarify([1, 2, 3], box).forEach((r) => assert.ok([r.x, r.y, r.w, r.h].every(Number.isFinite)));
    }
    squarify([0, 0], { x: 0, y: 0, w: 10, h: 10 }).forEach((r) => assert.equal(area(r), 0));
});

// ── grouping ────────────────────────────────────────────────────────────────

const tile = (s, i, v, c, x = 'HOSE') => ({ s, n: s, x, i, p: 10000, c, v, q: 1 });

test('groupItems: tiles are grouped by industry, busiest industry and busiest tile first', () => {
    const groups = groupItems([tile('A', 'Ngân hàng', 100, 1), tile('B', 'Thép', 500, -1), tile('C', 'Ngân hàng', 300, 2)]);

    assert.deepEqual(groups.map((g) => g.name), ['Thép', 'Ngân hàng']);
    assert.deepEqual(groups[1].items.map((t) => t.s), ['C', 'A']);
    assert.equal(groups[1].value, 400);
    assert.equal(groups[1].count, 2);
});

test('groupItems: the industry percentage is weighted by traded value', () => {
    const [g] = groupItems([tile('A', 'X', 300, 1), tile('B', 'X', 100, -3)]);

    near(g.percent, (300 * 1 + 100 * -3) / 400);
});

test('groupItems: "Khác" is always last, even when it is the biggest', () => {
    const groups = groupItems([tile('A', 'Khác', 900, 0), tile('B', 'Thép', 10, 0), tile('C', '', 5, 0)]);

    assert.deepEqual(groups.map((g) => g.name), ['Thép', 'Khác']);
    assert.equal(groups[1].count, 2, 'a tile with no industry joins Khác');
});

test('groupItems: the exchange filter understands the codes the data uses', () => {
    const items = [tile('A', 'X', 1, 0, 'HOSE'), tile('B', 'X', 1, 0, 'HSX'), tile('C', 'X', 1, 0, 'HNX'), tile('D', 'X', 1, 0, 'UPCOM'), tile('E', 'X', 1, 0, '')];

    const syms = (ex) => groupItems(items, ex).flatMap((g) => g.items.map((t) => t.s)).sort();
    assert.deepEqual(syms('ALL'), ['A', 'B', 'C', 'D', 'E']);
    assert.deepEqual(syms('HOSE'), ['A', 'B']);
    assert.deepEqual(syms('HNX'), ['C']);
    assert.deepEqual(syms('UPCOM'), ['D']);
});

test('groupItems: tiles without traded value are dropped, an empty list gives no groups', () => {
    assert.deepEqual(groupItems([tile('A', 'X', 0, 1), tile('B', 'X', -5, 1)]), []);
    assert.deepEqual(groupItems([]), []);
});

// ── layout ──────────────────────────────────────────────────────────────────

test('layoutGroups: every tile sits inside its industry panel and inside the stage, with no overlaps', () => {
    const items = [];
    for (let k = 0; k < 60; k++) items.push(tile(`S${k}`, `Ngành ${k % 7}`, 1000 / (k + 1), (k % 9) - 4));
    const groups = groupItems(items);
    const layout = layoutGroups(groups, { width: 900, height: 480 });

    const all = [];
    for (const L of layout) {
        for (const T of L.tiles) {
            assert.ok(T.rect.x >= L.rect.x - 1e-6 && T.rect.y >= L.rect.y - 1e-6 && T.rect.x + T.rect.w <= L.rect.x + L.rect.w + 1e-6 && T.rect.y + T.rect.h <= L.rect.y + L.rect.h + 1e-6, 'tile inside its panel');
            all.push(T.rect);
        }
        assert.ok(L.rect.x >= 0 && L.rect.y >= 0 && L.rect.x + L.rect.w <= 900 + 1e-6 && L.rect.y + L.rect.h <= 480 + 1e-6, 'panel inside the stage');
    }
    assert.equal(all.length, 60);
    for (let i = 0; i < all.length; i++) for (let j = i + 1; j < all.length; j++) assert.equal(overlap(all[i], all[j]), false);
});

test('layoutGroups: a header strip is reserved only where there is room, and the tiles start below it', () => {
    const layout = layoutGroups(groupItems([tile('A', 'Big', 1000, 1), tile('B', 'Tiny', 1, 1)]), { width: 600, height: 300, header: 20 });
    const big = layout.find((l) => l.group.name === 'Big');
    const tiny = layout.find((l) => l.group.name === 'Tiny');

    assert.equal(big.header, true);
    assert.ok(big.tiles[0].rect.y >= big.rect.y + 20 - 1e-6);
    assert.equal(tiny.header, false);
});

test('layoutGroups: a tile is larger when it traded more', () => {
    const [L] = layoutGroups(groupItems([tile('BIG', 'X', 900, 1), tile('SMALL', 'X', 100, 1)]), { width: 500, height: 300, gap: 0, tileGap: 0 });
    const by = Object.fromEntries(L.tiles.map((t) => [t.item.s, area(t.rect)]));

    assert.ok(by.BIG > by.SMALL * 5);
});

test('layoutGroups: no groups, no layout', () => {
    assert.deepEqual(layoutGroups([], { width: 500, height: 300 }), []);
});

// ── colour and labels ───────────────────────────────────────────────────────

const lightness = (hsl) => Number(/hsl\(\d+ \d+% (\d+)%\)/.exec(hsl)[1]);
const hue = (hsl) => Number(/hsl\((\d+) /.exec(hsl)[1]);

test('tileColor: green for gains, red for losses, grey when flat or unknown', () => {
    assert.equal(hue(tileColor(2)), 152);
    assert.equal(hue(tileColor(-2)), 3);
    for (const flat of [0, 0.01, -0.04, null, undefined, 'x', NaN]) assert.equal(tileColor(flat), 'hsl(215 14% 52%)');
});

test('tileColor: the bigger the move the deeper the colour, saturating at the daily limit', () => {
    assert.ok(lightness(tileColor(1)) > lightness(tileColor(3)) && lightness(tileColor(3)) > lightness(tileColor(6)));
    assert.ok(lightness(tileColor(-1)) > lightness(tileColor(-3)) && lightness(tileColor(-3)) > lightness(tileColor(-6)));
    assert.equal(tileColor(7), tileColor(9.9), 'beyond the limit it stays the same');
    assert.equal(tileColor(-7), tileColor(-20));
});

test('tileColor: text stays readable, white on every tile (never lighter than the mid tones)', () => {
    for (const p of [0.1, 0.5, 1, 3, 7, -0.1, -0.5, -1, -3, -7]) assert.ok(lightness(tileColor(p)) <= 52, `${p}%`);
});

test('LEGEND_STOPS run from the deepest loss to the deepest gain', () => {
    assert.deepEqual(LEGEND_STOPS, [...LEGEND_STOPS].sort((a, b) => a - b));
    assert.equal(LEGEND_STOPS[0], -7);
    assert.equal(LEGEND_STOPS.at(-1), 7);
});

test('labelFor: a tile shows only what fits', () => {
    assert.equal(labelFor({ w: 20, h: 40 }).level, 0);
    assert.equal(labelFor({ w: 60, h: 14 }).level, 0);
    assert.equal(labelFor({ w: 40, h: 30 }).level, 1);
    assert.equal(labelFor({ w: 80, h: 50 }).level, 2);
    assert.equal(labelFor({ w: 200, h: 120 }).level, 3);
});

test('labelFor: the font grows with the tile but stays within bounds', () => {
    const small = labelFor({ w: 60, h: 40 }).size;
    const big = labelFor({ w: 400, h: 300 }).size;

    assert.ok(small >= 9 && big <= 26 && big > small);
    assert.ok(labelFor({ w: 40, h: 30 }).size <= 11, 'a one-line label is never large');
});

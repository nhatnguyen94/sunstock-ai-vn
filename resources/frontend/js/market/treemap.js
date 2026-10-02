// Pure maths for the market heat map (no DOM): grouping, squarified treemap layout, colour scale and label levels.
// Unit-tested in tests/js/treemap.test.mjs; the DOM side is market/heatmap.js.
//
// A tile is {s: symbol, n: name, x: exchange, i: industry, p: price, c: % change, v: traded value, q: volume}
// (see App\Support\MarketHeatmap). Size = traded value, colour = % change.

export const OTHER = 'Khác';

const sum = (a) => a.reduce((t, n) => t + n, 0);
const clamp = (n, lo, hi) => Math.min(hi, Math.max(lo, n));

const EXCHANGE = { HSX: 'HOSE', HOSE: 'HOSE', HNX: 'HNX', UPCOM: 'UPCOM' };
/** 'HOSE' | 'HNX' | 'UPCOM' for the codes the data uses (the symbol list says HSX, KBS says HOSE/UPCOM). */
export const exchangeKey = (code) => EXCHANGE[String(code ?? '').toUpperCase()] ?? '';

/**
 * Tiles grouped by industry: [{name, value, percent (value-weighted), count, items}], the busiest industry first, tiles inside
 * busiest first. `exchange` 'ALL' keeps everything.
 */
export function groupItems(items, exchange = 'ALL') {
    const byName = new Map();
    for (const it of items) {
        if (exchange !== 'ALL' && exchangeKey(it.x) !== exchange) continue;
        if (!(it.v > 0)) continue;
        const name = it.i || OTHER;
        if (!byName.has(name)) byName.set(name, []);
        byName.get(name).push(it);
    }

    return [...byName.entries()]
        .map(([name, list]) => {
            const sorted = list.slice().sort((a, b) => b.v - a.v);
            const value = sum(sorted.map((t) => t.v));
            return { name, value, percent: value > 0 ? sum(sorted.map((t) => t.v * t.c)) / value : 0, count: sorted.length, items: sorted };
        })
        .sort((a, b) => (a.name === OTHER) - (b.name === OTHER) || b.value - a.value);   // "Khác" always last
}

/** Worst aspect ratio of a row of areas laid along a side of length `side` (Bruls et al.). */
function worst(row, side) {
    const s = sum(row);
    const mx = Math.max(...row);
    const mn = Math.min(...row);
    return Math.max((side * side * mx) / (s * s), (s * s) / (side * side * mn));
}

/**
 * Squarified treemap: rectangles whose areas are proportional to `values` and that tile `rect` exactly, in the order of
 * `values`. Non-positive values get an empty rectangle.
 * @param {number[]} values
 * @param {{x:number,y:number,w:number,h:number}} rect
 */
export function squarify(values, { x, y, w, h }) {
    const out = values.map(() => ({ x, y, w: 0, h: 0 }));
    const total = sum(values.filter((v) => v > 0));
    if (!(total > 0) || !(w > 0) || !(h > 0)) return out;

    const order = values.map((v, i) => ({ v, i })).filter((o) => o.v > 0).sort((a, b) => b.v - a.v);
    const areas = order.map((o) => (o.v * w * h) / total);

    let cx = x, cy = y, cw = w, ch = h;
    let i = 0;
    while (i < areas.length) {
        const side = Math.min(cw, ch);
        const row = [areas[i]];
        let j = i + 1;
        while (j < areas.length && worst([...row, areas[j]], side) <= worst(row, side)) {
            row.push(areas[j]);
            j++;
        }
        const rowSum = sum(row);
        const last = j >= areas.length;

        if (cw >= ch) {                                   // a column at the left
            const colW = last ? cw : rowSum / ch;
            let yy = cy;
            row.forEach((a, k) => {
                const hh = last && k === row.length - 1 ? cy + ch - yy : a / colW;
                out[order[i + k].i] = { x: cx, y: yy, w: colW, h: hh };
                yy += hh;
            });
            cx += colW;
            cw -= colW;
        } else {                                          // a row at the top
            const rowH = last ? ch : rowSum / cw;
            let xx = cx;
            row.forEach((a, k) => {
                const ww = last && k === row.length - 1 ? cx + cw - xx : a / rowH;
                out[order[i + k].i] = { x: xx, y: cy, w: ww, h: rowH };
                xx += ww;
            });
            cy += rowH;
            ch -= rowH;
        }
        i = j;
    }

    return out.map((r) => ({ x: r.x, y: r.y, w: Math.max(0, r.w), h: Math.max(0, r.h) }));
}

const inset = (r, d) => ({ x: r.x + d, y: r.y + d, w: Math.max(0, r.w - 2 * d), h: Math.max(0, r.h - 2 * d) });

/**
 * Lay the industries out in the stage, then the tiles of each inside its industry. Industries big enough get a header strip.
 * @returns {{group:object, rect:object, header:boolean, tiles:{item:object, rect:object}[]}[]}
 */
export function layoutGroups(groups, { width, height, gap = 4, header = 20, tileGap = 1 }) {
    const outer = squarify(groups.map((g) => g.value), { x: 0, y: 0, w: width, h: height });

    return groups.map((g, gi) => {
        const rect = inset(outer[gi], gap / 2);
        const withHeader = rect.w >= 74 && rect.h >= header + 36;
        const body = { x: rect.x, y: rect.y + (withHeader ? header : 0), w: rect.w, h: Math.max(0, rect.h - (withHeader ? header : 0)) };
        const cells = squarify(g.items.map((t) => t.v), body);

        return { group: g, rect, header: withHeader, tiles: g.items.map((item, k) => ({ item, rect: inset(cells[k], tileGap / 2) })) };
    });
}

/**
 * Tile colour for a % change: grey when flat, green for gains, red for losses, deeper as the move grows (the daily limit
 * is about 7 %, so the scale saturates there).
 */
export function tileColor(pct) {
    const p = Number(pct);
    if (!Number.isFinite(p) || Math.abs(p) < 0.05) return 'hsl(215 14% 52%)';
    const e = Math.pow(clamp(Math.abs(p) / 7, 0, 1), 0.7);

    return p > 0
        ? `hsl(152 ${Math.round(56 + e * 12)}% ${Math.round(40 - e * 14)}%)`
        : `hsl(3 ${Math.round(62 + e * 14)}% ${Math.round(52 - e * 15)}%)`;
}

/**
 * What a tile of this size can show. level 0 nothing, 1 symbol, 2 symbol + %, 3 + traded value; `size` is the symbol's font
 * size in px.
 */
export function labelFor({ w, h }) {
    if (w < 26 || h < 18) return { level: 0, size: 0 };
    const size = Math.round(clamp(Math.min(w / 4.4, h / 2.4), 9, 26));
    if (w < 58 || h < 38) return { level: 1, size: Math.min(size, 11) };
    if (w < 112 || h < 74) return { level: 2, size };

    return { level: 3, size };
}

/** Legend stops: the colours for these % changes, left to right. */
export const LEGEND_STOPS = [-7, -4, -1.5, 0, 1.5, 4, 7];

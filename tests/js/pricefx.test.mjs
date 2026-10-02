import assert from 'node:assert/strict';
import { test } from 'node:test';
import { VND_FORMAT, fmtInt } from '../../resources/frontend/js/shared/charts.js';
import { direction, easeOutCubic, formatNumber, valueAt } from '../../resources/frontend/js/shared/pricefx.js';
import { PAGE_SIZE, buildRows, dateParts, esc, pageHtml, pageWindow, rowHtml, volumeShare } from '../../resources/frontend/js/stock/pricetable.js';

const plain = (s) => s.replace(/ /g, ' ');

// 1 Oct 2026 and 2 Oct 2026, midnight Vietnam time (17:00 UTC the day before), as the repository returns them
const D1 = Date.UTC(2026, 8, 30, 17, 0, 0);
const D2 = Date.UTC(2026, 9, 1, 17, 0, 0);
const raw = [
    { time: D1, open: 62.7, high: 63.2, low: 62.4, close: 62.7, volume: 4_000_000 },
    { time: D2, open: 62.7, high: 63.2, low: 62.1, close: 62.1, volume: 3_199_600 },
];

test('formatNumber uses Vietnamese separators and a fixed number of decimals', () => {
    assert.equal(plain(formatNumber(62100, 0)), '62.100');
    assert.equal(plain(formatNumber(1737.71, 2)), '1.737,71');
    assert.equal(plain(formatNumber(5, 2)), '5,00');
    assert.equal(plain(formatNumber(-600, 0)), '-600');
});

test('direction classifies a move and treats non-numbers and tiny noise as flat', () => {
    assert.equal(direction(62700, 62100), 'down');
    assert.equal(direction(62100, 62700), 'up');
    assert.equal(direction(62100, 62100), 'flat');
    assert.equal(direction(62100, 62100.0000000001), 'flat');
    assert.equal(direction('x', 5), 'flat');
    assert.equal(direction(null, 5), 'flat');
    assert.equal(direction(undefined, undefined), 'flat');
});

test('easeOutCubic is clamped, monotonic and starts fast', () => {
    assert.equal(easeOutCubic(-1), 0);
    assert.equal(easeOutCubic(0), 0);
    assert.equal(easeOutCubic(1), 1);
    assert.equal(easeOutCubic(2), 1);
    assert.ok(easeOutCubic(0.5) > 0.5);
    let last = -1;
    for (let t = 0; t <= 1; t += 0.1) {
        assert.ok(easeOutCubic(t) >= last);
        last = easeOutCubic(t);
    }
});

test('valueAt counts from the old value and lands exactly on the new one', () => {
    assert.equal(valueAt(100, 200, 0), 100);
    assert.equal(valueAt(100, 200, 1), 200);
    assert.equal(valueAt(100, 200, 1.5), 200);
    const mid = valueAt(100, 200, 0.5);
    assert.ok(mid > 150 && mid < 200);
    assert.ok(valueAt(200, 100, 0.5) < 150 && valueAt(200, 100, 0.5) > 100);   // counting down works too
});

test('the whole-VND chart format prints 62.100 and never decimals', () => {
    assert.equal(VND_FORMAT.type, 'custom');
    assert.equal(plain(VND_FORMAT.formatter(62100)), '62.100');
    assert.equal(plain(VND_FORMAT.formatter(1_234_500)), '1.234.500');
    assert.equal(VND_FORMAT.formatter, fmtInt);
});

test('buildRows converts feed thousands to dong, newest first, with the change against the previous session', () => {
    const rows = buildRows(raw, 1000);

    assert.equal(rows.length, 2);
    assert.equal(rows[0].time, D2);                       // newest first
    assert.equal(Math.round(rows[0].close), 62100);
    assert.equal(Math.round(rows[0].open), 62700);
    assert.equal(Math.round(rows[0].change), -600);
    assert.ok(Math.abs(rows[0].pct - (62.1 / 62.7 - 1) * 100) < 1e-9);
    assert.equal(rows[0].dir, 'down');
    assert.equal(rows[1].change, null);                   // the oldest row has nothing before it
    assert.equal(rows[1].pct, null);
    assert.equal(rows[1].dir, 'flat');
});

test('buildRows leaves an index in points (scale 1) and keeps junk values finite', () => {
    const rows = buildRows([{ time: D1, open: 1700, high: 1760, low: 1690, close: 1749.3, volume: '1000' }, { time: D2, open: 1749, high: 1750, low: 1730, close: 1737.71, volume: null }], 1);

    assert.equal(rows[0].close, 1737.71);
    assert.ok(Math.abs(rows[0].change - (1737.71 - 1749.3)) < 1e-9);
    assert.equal(rows[0].volume, 0);                      // null volume does not become NaN
    assert.equal(rows[1].volume, 1000);
});

test('a flat session has no direction', () => {
    const rows = buildRows([{ time: D1, open: 10, high: 10, low: 10, close: 10, volume: 1 }, { time: D2, open: 10, high: 10, low: 10, close: 10, volume: 1 }], 1000);

    assert.equal(rows[0].dir, 'flat');
});

test('volumeShare is relative to the busiest row and safe against zero', () => {
    assert.equal(volumeShare(50, 100), 50);
    assert.equal(volumeShare(100, 100), 100);
    assert.equal(volumeShare(150, 100), 100);
    assert.equal(volumeShare(10, 0), 0);
    assert.equal(volumeShare(0, 100), 0);
});

test('dateParts gives dd/MM/yyyy and the weekday for Vietnam-midnight timestamps', () => {
    assert.deepEqual(dateParts(D2), { date: '02/10/2026', weekday: 'T6' });   // Friday
    assert.deepEqual(dateParts(Date.UTC(2026, 9, 2, 0, 0, 0)), { date: '02/10/2026', weekday: 'T6' });   // UTC midnight lands on the same day
});

test('esc neutralises markup', () => {
    assert.equal(esc('<b>"x"</b>&\''), '&lt;b&gt;&quot;x&quot;&lt;/b&gt;&amp;&#39;');
    assert.equal(esc(null), '');
});

test('rowHtml shows close, signed change in dong and percent, colours by direction and has no currency badge', () => {
    const [latest] = buildRows(raw, 1000);
    const html = plain(rowHtml(latest, { decimals: 0, maxVolume: 4_000_000, avgVolume: 3_500_000 }));

    assert.match(html, /class="px-close is-down">62\.100</);
    assert.match(html, /class="px-change is-down">-600<small>-0,96%<\/small>/);
    assert.match(html, /<td>62\.700<\/td>/);                      // open
    assert.match(html, /<td class="is-up">63\.200<\/td>/);        // high
    assert.match(html, /<td class="is-down">62\.100<\/td>/);      // low
    assert.match(html, /02\/10\/2026<small>T6<\/small>/);
    assert.match(html, /width:80%/);                              // 3,199,600 / 4,000,000
    assert.doesNotMatch(html, /VND|badge/);
});

test('rowHtml for the oldest row shows a dash instead of a change and an upward row is signed with a plus', () => {
    const rows = buildRows([{ time: D1, open: 10, high: 11, low: 9, close: 10, volume: 1 }, { time: D2, open: 10, high: 12, low: 10, close: 11, volume: 1 }], 1000);

    assert.match(plain(rowHtml(rows[1], { decimals: 0 })), /px-change is-flat"><span class="is-flat">—<\/span>/);
    assert.match(plain(rowHtml(rows[0], { decimals: 0 })), /is-up">\+1\.000<small>\+10,00%<\/small>/);
});

test('rowHtml marks a session with 1.5x the average volume as busy', () => {
    const [row] = buildRows([{ time: D1, open: 1, high: 1, low: 1, close: 1, volume: 3000 }], 1000);

    assert.match(rowHtml(row, { avgVolume: 1000, maxVolume: 3000 }), /^<tr class="is-hot">/);
    assert.match(rowHtml(row, { avgVolume: 2500, maxVolume: 3000 }), /^<tr class="">/);
    assert.match(rowHtml(row, { avgVolume: 0, maxVolume: 3000 }), /^<tr class="">/);
});

test('index rows keep two decimals', () => {
    const rows = buildRows([{ time: D1, open: 1700, high: 1760, low: 1690, close: 1749.3, volume: 1 }, { time: D2, open: 1749, high: 1750, low: 1730, close: 1737.71, volume: 1 }], 1);

    assert.match(plain(rowHtml(rows[0], { decimals: 2 })), /px-close is-down">1\.737,71</);
    assert.match(plain(rowHtml(rows[0], { decimals: 2 })), /-11,59<small>-0,66%/);
});

test('pageHtml renders one page and takes the bar scale from that page', () => {
    const many = Array.from({ length: 45 }, (_, i) => ({ time: D1 + i * 86400000, open: 10, high: 11, low: 9, close: 10 + (i % 3), volume: 1000 + i }));
    const rows = buildRows(many, 1000);

    assert.equal((pageHtml(rows, 1).match(/<tr/g) || []).length, PAGE_SIZE);
    assert.equal((pageHtml(rows, 3).match(/<tr/g) || []).length, 5);          // 45 = 20 + 20 + 5
    assert.equal(pageHtml(rows, 4), '');
    assert.match(pageHtml(rows, 3), /width:100%/);                             // the busiest row of the page fills its bar
});

test('pageWindow shows the first and last page with gaps marked', () => {
    assert.deepEqual(pageWindow(1, 1), [1]);
    assert.deepEqual(pageWindow(1, 3), [1, 2, 3]);
    assert.deepEqual(pageWindow(1, 20), [1, 2, 3, '…', 20]);
    assert.deepEqual(pageWindow(10, 20), [1, '…', 8, 9, 10, 11, 12, '…', 20]);
    assert.deepEqual(pageWindow(20, 20), [1, '…', 18, 19, 20]);
    assert.deepEqual(pageWindow(4, 20), [1, 2, 3, 4, 5, 6, '…', 20]);
});

import assert from 'node:assert/strict';
import { test } from 'node:test';
import { cleanSeries, fmtInt, fmtPrice, rebase, sliceByDays, toDay } from '../../resources/frontend/js/shared/charts.js';

test('toDay maps both midnight-UTC and midnight-Vietnam timestamps to the intended calendar day', () => {
    assert.equal(toDay(Date.UTC(2026, 8, 18)), '2026-09-18');
    assert.equal(toDay(Date.UTC(2026, 8, 17, 17)), '2026-09-18');   // 00:00 at UTC+7
});

test('cleanSeries sorts ascending, keeps the last point per day and drops non-finite values', () => {
    const out = cleanSeries([
        { time: '2026-01-03', value: 3 },
        { time: '2026-01-01', value: 1 },
        { time: '2026-01-01', value: 9 },
        { time: '2026-01-02', value: NaN },
    ]);
    assert.deepEqual(out.map((p) => [p.time, p.value]), [['2026-01-01', 9], ['2026-01-03', 3]]);
});

test('sliceByDays keeps points inside the window ending at the last point', () => {
    const pts = [{ time: '2026-01-01', value: 1 }, { time: '2026-08-01', value: 2 }, { time: '2026-09-01', value: 3 }];
    assert.deepEqual(sliceByDays(pts, 60).map((p) => p.time), ['2026-08-01', '2026-09-01']);
    assert.equal(sliceByDays(pts, 0).length, 3);
});

test('rebase starts every series at the base and refuses a zero base', () => {
    assert.deepEqual(rebase([{ time: 'a', value: 2 }, { time: 'b', value: 4 }], 100).map((p) => p.value), [100, 200]);
    assert.deepEqual(rebase([{ time: 'a', value: 0 }]), []);
});

test('price formatting: decimals for thousand-VND stock prices, whole numbers for large VND amounts', () => {
    assert.equal(fmtPrice(73.1), '73,10');
    assert.equal(fmtPrice(95325.99), '95.326');
    assert.equal(fmtInt(71700), '71.700');
});

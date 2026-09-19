// Run with: npm test   (Node's built-in test runner — no extra dependency)
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { bollinger, ema, macd, rsi, sma } from '../../resources/frontend/js/shared/indicators.js';

test('sma leaves the warm-up window null and averages the rest', () => {
    assert.deepEqual(sma([1, 2, 3, 4, 5], 3), [null, null, 2, 3, 4]);
});

test('ema of a constant series is that constant', () => {
    assert.deepEqual(ema([1, 1, 1], 3), [1, 1, 1]);
});

test('rsi: null warm-up, ~100 for a strictly rising series, ~0 for a strictly falling one', () => {
    const up = Array.from({ length: 30 }, (_, i) => 100 + i);
    const down = up.slice().reverse();
    assert.ok(rsi(up, 14).slice(0, 14).every((v) => v === null));
    assert.ok(rsi(up, 14).at(-1) > 99);
    assert.ok(rsi(down, 14).at(-1) < 1);
});

test('macd of a flat series is zero everywhere it is defined', () => {
    const m = macd(new Array(60).fill(50));
    assert.ok(m.line.filter((v) => v !== null).every((v) => Math.abs(v) < 1e-9));
    assert.ok(m.hist.filter((v) => v !== null).every((v) => Math.abs(v) < 1e-9));
});

test('bollinger: flat series has zero-width bands; known values for [1,2,3]', () => {
    const flat = bollinger([10, 10, 10, 10, 10], 3, 2);
    assert.equal(flat.mid[1], null);
    assert.equal(flat.upper[4], 10);
    assert.equal(flat.lower[4], 10);

    const b = bollinger([1, 2, 3], 3, 2);
    assert.equal(b.mid[2], 2);
    assert.ok(Math.abs(b.upper[2] - b.mid[2] - 2 * Math.sqrt(2 / 3)) < 1e-9);
    assert.ok(Math.abs(b.mid[2] - b.lower[2] - 2 * Math.sqrt(2 / 3)) < 1e-9);
});

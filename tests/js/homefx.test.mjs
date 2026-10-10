import test from 'node:test';
import assert from 'node:assert/strict';
import { glowPosition, inkBox } from '../../resources/frontend/js/home/home-fx.js';

test('glowPosition is relative to the box and clamped inside it', () => {
    const rect = { left: 100, top: 50, width: 200, height: 80 };
    assert.deepEqual(glowPosition(150, 70, rect), { x: 50, y: 20 });
    assert.deepEqual(glowPosition(0, 0, rect), { x: 0, y: 0 });
    assert.deepEqual(glowPosition(999, 999, rect), { x: 200, y: 80 });
});

test('inkBox follows the active tab and is null for a hidden or missing tab', () => {
    assert.deepEqual(inkBox({ offsetLeft: 12, offsetTop: 3, offsetWidth: 64, offsetHeight: 28 }, { scrollLeft: 5 }), { x: 12, y: 3, w: 64, h: 28, scrollLeft: 5 });
    assert.equal(inkBox({ offsetWidth: 0 }, {}), null);
    assert.equal(inkBox(null, {}), null);
});

import { lerp, magnetOffset, parseVi, rollDirection } from '../../resources/frontend/js/home/home-fx.js';

test('lerp moves a fraction of the way', () => {
    assert.equal(lerp(0, 10, 0.5), 5);
    assert.equal(lerp(4, 4, 0.9), 4);
});

test('magnetOffset pulls towards the pointer, is capped and ignores a far pointer', () => {
    assert.deepEqual(magnetOffset(20, -10, 90, 0.5), { x: 10, y: -5 });
    assert.deepEqual(magnetOffset(80, 0, 90, 1, 12), { x: 12, y: 0 });
    assert.deepEqual(magnetOffset(200, 0, 90), { x: 0, y: 0 });
});

test('parseVi reads Vietnamese formatted numbers', () => {
    assert.equal(parseVi('1.735,09'), 1735.09);
    assert.equal(parseVi('-3,88'), -3.88);
    assert.ok(Number.isNaN(parseVi('abc')));
    assert.ok(Number.isNaN(parseVi(null)));
});

test('rollDirection says which way the figure moved, or null', () => {
    assert.equal(rollDirection('1.735,09', '1.740,00'), 'up');
    assert.equal(rollDirection('1.740,00', '1.735,09'), 'down');
    assert.equal(rollDirection('1.735,09', '1.735,09'), null);
    assert.equal(rollDirection('--', '12'), null);
});

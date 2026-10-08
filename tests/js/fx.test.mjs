import test from 'node:test';
import assert from 'node:assert/strict';
import { activeIndex, tiltAngles } from '../../resources/frontend/js/shared/fx.js';

test('the card leans towards the pointer and is flat in the middle', () => {
    assert.deepEqual(tiltAngles(50, 50, 100, 100), { rx: 0, ry: 0 });
    assert.deepEqual(tiltAngles(100, 50, 100, 100, 8), { rx: 0, ry: 8 });       // right edge: turns right
    assert.deepEqual(tiltAngles(0, 50, 100, 100, 8), { rx: 0, ry: -8 });        // left edge
    assert.deepEqual(tiltAngles(50, 0, 100, 100, 8), { rx: 8, ry: 0 });         // top edge: tips back
    assert.equal(tiltAngles(50, 100, 100, 100, 8).rx, -8);                      // bottom edge
});

test('a pointer outside the box is clamped and an empty box does not divide by zero', () => {
    assert.equal(tiltAngles(500, 50, 100, 100, 7).ry, 7);
    assert.equal(tiltAngles(-20, 50, 100, 100, 7).ry, -7);
    assert.deepEqual(tiltAngles(10, 10, 0, 0), { rx: 0, ry: 0 });
});

test('the section being read is the last one whose top has passed the reading line', () => {
    assert.equal(activeIndex([500, 1400, 2300], 300), 0);       // nothing has passed yet: the first one
    assert.equal(activeIndex([-200, 600, 1500], 300), 0);
    assert.equal(activeIndex([-1200, -100, 900], 300), 1);
    assert.equal(activeIndex([-3000, -2000, -10], 300), 2);
});

test('sections that are not on the page (Infinity) are never the active one', () => {
    assert.equal(activeIndex([-100, Infinity, Infinity], 300), 0);
});

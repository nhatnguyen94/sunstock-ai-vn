import assert from 'node:assert/strict';
import { test } from 'node:test';

import { nextVisible } from '../../resources/frontend/js/shared/mobile-nav.js';

const base = { prev: 500, y: 500, visible: true, maxY: 5000 };

test('near the top the bar is always visible, whatever the direction', () => {
    assert.equal(nextVisible({ ...base, prev: 0, y: 60, visible: false }), true);
    assert.equal(nextVisible({ ...base, prev: 200, y: 80, visible: false }), true);
});

test('scrolling down past the top zone hides it', () => {
    assert.equal(nextVisible({ ...base, prev: 400, y: 520 }), false);
});

test('scrolling up brings it back', () => {
    assert.equal(nextVisible({ ...base, prev: 900, y: 860, visible: false }), true);
});

test('a movement smaller than the threshold keeps the current state', () => {
    assert.equal(nextVisible({ ...base, prev: 500, y: 504, visible: true }), true);
    assert.equal(nextVisible({ ...base, prev: 500, y: 504, visible: false }), false);
    assert.equal(nextVisible({ ...base, prev: 500, y: 495, visible: false }), false);
});

test('at the very end of the page it shows, so the footer is not covered for good', () => {
    assert.equal(nextVisible({ ...base, prev: 4900, y: 4998, visible: false }), true);
});

test('an unknown page height does not break the end-of-page rule', () => {
    assert.equal(nextVisible({ prev: 400, y: 520, visible: true }), false);
});

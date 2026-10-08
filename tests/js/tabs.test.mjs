import assert from 'node:assert/strict';
import { test } from 'node:test';

import { nextIndex } from '../../resources/frontend/js/shared/tabs.js';

test('nextIndex: the arrow keys move one tab and wrap around at both ends', () => {
    assert.equal(nextIndex(4, 0, 'ArrowRight'), 1);
    assert.equal(nextIndex(4, 3, 'ArrowRight'), 0);
    assert.equal(nextIndex(4, 1, 'ArrowLeft'), 0);
    assert.equal(nextIndex(4, 0, 'ArrowLeft'), 3);
});

test('nextIndex: Home and End jump to the first and last tab', () => {
    assert.equal(nextIndex(5, 3, 'Home'), 0);
    assert.equal(nextIndex(5, 1, 'End'), 4);
});

test('nextIndex: any other key leaves the selection where it is (so Tab, Enter and letters are not swallowed)', () => {
    for (const key of ['Tab', 'Enter', ' ', 'a', 'ArrowUp', 'ArrowDown', 'Escape']) assert.equal(nextIndex(4, 2, key), 2, key);
});

test('nextIndex: a single tab stays put and an empty list answers -1', () => {
    assert.equal(nextIndex(1, 0, 'ArrowRight'), 0);
    assert.equal(nextIndex(1, 0, 'ArrowLeft'), 0);
    assert.equal(nextIndex(0, 0, 'ArrowRight'), -1);
});

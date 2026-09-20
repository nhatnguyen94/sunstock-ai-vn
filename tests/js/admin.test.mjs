import assert from 'node:assert/strict';
import { test } from 'node:test';
import { filterCommands, moveSelection, toastIcon } from '../../resources/frontend/js/admin/helpers.js';

const items = [
    { title: 'Dashboard', group: 'Tổng quan', keywords: '' },
    { title: 'Quản lý Users', group: 'Hệ thống', keywords: 'người dùng tài khoản email' },
    { title: 'Giám sát Queue', group: 'Hệ thống', keywords: 'job hàng đợi redis' },
    { title: 'Quản lý Stock', group: 'Nội dung & dữ liệu', keywords: 'cổ phiếu mã giá' },
    { title: 'Đổi mật khẩu', group: 'Tài khoản', keywords: 'password' },
];

test('an empty query keeps the original order and returns a copy', () => {
    const out = filterCommands(items, '   ');
    assert.deepEqual(out.map((i) => i.title), items.map((i) => i.title));
    assert.notEqual(out, items);
});

test('the palette search ignores accents and case ("quan ly" finds "Quản lý")', () => {
    assert.deepEqual(filterCommands(items, 'quan ly').map((i) => i.title), ['Quản lý Users', 'Quản lý Stock']);
    assert.deepEqual(filterCommands(items, 'DOI MAT KHAU').map((i) => i.title), ['Đổi mật khẩu']);
    assert.deepEqual(filterCommands(items, 'hàng đợi').map((i) => i.title), ['Giám sát Queue']);   // matches keywords, with accents
});

test('every word must match, in any order', () => {
    assert.deepEqual(filterCommands(items, 'stock quan').map((i) => i.title), ['Quản lý Stock']);
    assert.deepEqual(filterCommands(items, 'stock zzz'), []);
});

test('title matches outrank group/keyword matches', () => {
    const list = [
        { title: 'Timeline', group: 'Tổng quan', keywords: 'dashboard hoạt động' },
        { title: 'Dashboard', group: 'Tổng quan', keywords: '' },
    ];
    assert.equal(filterCommands(list, 'dash')[0].title, 'Dashboard');   // title prefix beats a keyword hit
});

test('the group name is searchable too', () => {
    assert.deepEqual(filterCommands(items, 'he thong').map((i) => i.title), ['Quản lý Users', 'Giám sát Queue']);
});

test('moveSelection wraps around both ways and copes with an empty list', () => {
    assert.equal(moveSelection(0, 1, 3), 1);
    assert.equal(moveSelection(2, 1, 3), 0);
    assert.equal(moveSelection(0, -1, 3), 2);
    assert.equal(moveSelection(0, 1, 0), -1);
});

test('toastIcon maps types to Tabler icons with a safe default', () => {
    assert.equal(toastIcon('success'), 'ti-circle-check');
    assert.equal(toastIcon('error'), 'ti-alert-circle');
    assert.equal(toastIcon('warning'), 'ti-alert-triangle');
    assert.equal(toastIcon('nonsense'), 'ti-info-circle');
});

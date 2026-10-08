import assert from 'node:assert/strict';
import { test } from 'node:test';

import { BANDS, arcPath, chip, foreignModel, needleAngle, renderForeign, renderSentiment } from '../../resources/frontend/js/market/pulse.js';

const foreign = {
    buy_value: 1_796_000_000_000, sell_value: 2_165_000_000_000, net_value: -369_000_000_000,
    by_exchange: { HOSE: { buy: 1, sell: 2, net: -367_000_000_000 }, HNX: { buy: 1, sell: 0, net: 9_800_000_000 }, UPCOM: { buy: 0, sell: 1, net: -11_600_000_000 } },
    top_buy: [{ symbol: 'PLX', net_value: 91_500_000_000 }, { symbol: 'VIC', net_value: 65_100_000_000 }],
    top_sell: [{ symbol: 'TCB', net_value: -151_800_000_000 }],
};

test('needleAngle: the half circle runs from -90 (score 0) through 0 (score 50) to 90 (score 100) and clamps', () => {
    assert.equal(needleAngle(0), -90);
    assert.equal(needleAngle(50), 0);
    assert.equal(needleAngle(100), 90);
    assert.equal(needleAngle(-20), -90);
    assert.equal(needleAngle(250), 90);
    assert.equal(needleAngle('abc'), -90);
});

test('arcPath: starts on the left for 0 and ends on the right for 100, bulging upwards', () => {
    assert.equal(arcPath(100, 100, 80, 0, 100), 'M 20 100 A 80 80 0 0 1 180 100');
    const top = arcPath(100, 100, 80, 50, 50);
    assert.ok(top.startsWith('M 100 20'), top);
});

test('BANDS cover 0 to 100 without a gap', () => {
    assert.equal(BANDS[0].from, 0);
    assert.equal(BANDS.at(-1).to, 100);
    for (let i = 1; i < BANDS.length; i++) assert.equal(BANDS[i].from, BANDS[i - 1].to);
});

test('foreignModel: direction, shares and the top lists', () => {
    const m = foreignModel(foreign);

    assert.equal(m.dir, 'down');
    assert.ok(m.buyShare > 40 && m.buyShare < 50);
    assert.equal(m.topBuy.length, 2);
    assert.deepEqual(m.byExchange.map((e) => e.code), ['HOSE', 'HNX', 'UPCOM']);
});

test('foreignModel: no data, or data without a net figure, gives null', () => {
    assert.equal(foreignModel(null), null);
    assert.equal(foreignModel({}), null);
    assert.equal(foreignModel({ net_value: 'x' }), null);
});

test('foreignModel: a balanced market is flat and an empty turnover splits 50/50', () => {
    const m = foreignModel({ buy_value: 0, sell_value: 0, net_value: 0 });

    assert.equal(m.dir, 'flat');
    assert.equal(m.buyShare, 50);
});

test('renderForeign: says buy or sell, shows the estimate caveat, the exchanges and both lists', () => {
    const html = renderForeign(foreign);

    assert.match(html, /Bán ròng toàn thị trường/);
    assert.match(html, /369 tỷ/);
    assert.match(html, /Ước tính/);
    assert.match(html, /HOSE -367 tỷ/);
    assert.match(html, /HNX \+10 tỷ/);
    assert.match(html, /href="\/stock\?symbol=PLX"/);
    assert.match(html, /Bán ròng nhiều nhất/);
    assert.match(html, /TCB/);
});

test('renderForeign: a market with net buying leads with "Mua ròng"', () => {
    assert.match(renderForeign({ buy_value: 5e11, sell_value: 1e11, net_value: 4e11 }), /Mua ròng toàn thị trường/);
});

test('renderForeign: no data gives an explanation, not a broken card', () => {
    assert.match(renderForeign(null), /Chưa có dữ liệu khối ngoại/);
});

test('renderForeign: a hostile symbol is escaped and encoded', () => {
    const html = renderForeign({ ...foreign, top_buy: [{ symbol: '<img src=x onerror=alert(1)>', net_value: 2e9 }] });

    assert.ok(!html.includes('<img src=x'), 'no raw tag');
    assert.match(html, /&lt;img src=x onerror=alert\(1\)&gt;/);
    assert.match(html, /symbol=%3Cimg/);
});

test('renderSentiment: a gauge with five arcs, a needle at the right angle and an accessible label', () => {
    const s = { score: 43, label: 'Trung lập', tone: 'flat', note: 'Chỉ báo tham khảo', components: [{ title: 'Độ rộng', score: 48, detail: '322 mã tăng / 352 mã giảm', weight: 35 }] };
    const html = renderSentiment(s);

    assert.equal((html.match(/<path /g) || []).length, 5);
    assert.match(html, new RegExp(`data-angle="${needleAngle(43)}"`));
    assert.match(html, /aria-label="Chỉ báo tâm lý thị trường: 43 trên 100, Trung lập"/);
    assert.match(html, /322 mã tăng \/ 352 mã giảm/);
    assert.match(html, /Chỉ báo tham khảo/);
});

test('renderSentiment: escapes every piece of data and survives no data', () => {
    const html = renderSentiment({ score: 10, label: '<b>x</b>', tone: 'down" onmouseover="x', note: '<i>n</i>', components: [{ title: '<u>t</u>', score: 999, detail: '"><script>', weight: 5 }] });

    assert.ok(!html.includes('<b>x</b>') && !html.includes('<script>') && !html.includes('<u>t</u>') && !html.includes('onmouseover="x'));
    assert.match(html, /--w:100%/, 'the bar is clamped');
    assert.match(renderSentiment(null), /Chưa đủ dữ liệu/);
});

test('chip formats a percentage with its sign and stays empty when unknown', () => {
    assert.equal(chip(0.35), '+0,35%');
    assert.equal(chip(-1), '-1,00%');
    assert.equal(chip(null), '');
});

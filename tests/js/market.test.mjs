import assert from 'node:assert/strict';
import { test } from 'node:test';
import { dirClass, esc, exchangeLabel, fmtInt, fmtPct, fmtValue, fmtVolume } from '../../resources/frontend/js/market/format.js';
import { BUY_FEE_RATE, SELL_FEE_RATE, estimateFee, tradePreview } from '../../resources/frontend/js/portfolio/ledger.js';

// Intl output uses a non-breaking space in some locales/units: normalise before comparing
const plain = (s) => s.replace(/ /g, ' ');

test('dirClass classifies signed numbers and treats missing values as flat', () => {
    assert.equal(dirClass(1.2), 'up');
    assert.equal(dirClass(-0.01), 'down');
    assert.equal(dirClass(0), 'flat');
    assert.equal(dirClass(null), 'flat');
    assert.equal(dirClass(NaN), 'flat');
});

test('fmtPct signs gains, keeps Vietnamese decimals and dashes out missing values', () => {
    assert.equal(fmtPct(2.5), '+2,50%');
    assert.equal(fmtPct(-1), '-1,00%');
    assert.equal(fmtPct(0), '0,00%');
    assert.equal(fmtPct(2.5, false), '2,50%');
    assert.equal(fmtPct(null), '—');
});

test('fmtInt and fmtVolume use Vietnamese grouping', () => {
    assert.equal(plain(fmtInt(1234567)), '1.234.567');
    assert.equal(fmtInt(null), '—');
    assert.equal(plain(fmtVolume(15500700)), '15,5 tr');
    assert.equal(plain(fmtVolume(45300)), '45,3K');
    assert.equal(fmtVolume(700), '700');
});

test('fmtValue picks nghìn tỷ / tỷ / triệu', () => {
    assert.equal(plain(fmtValue(22_904_000_000_000)), '22,9 nghìn tỷ');
    assert.equal(plain(fmtValue(3_197_000_000_000)), '3,2 nghìn tỷ');
    assert.equal(plain(fmtValue(575_000_000_000)), '575 tỷ');
    assert.equal(plain(fmtValue(27_000_000)), '27 triệu');
    assert.equal(fmtValue(undefined), '—');
});

test('esc neutralises every HTML metacharacter (symbols and names come from the server)', () => {
    assert.equal(esc('<img src=x onerror="a">&\''), '&lt;img src=x onerror=&quot;a&quot;&gt;&amp;&#39;');
    assert.equal(esc(null), '');
});

test('estimateFee: 0.15% commission on a buy, 0.15% + 0.1% tax on a sell', () => {
    assert.equal(BUY_FEE_RATE, 0.0015);
    assert.equal(SELL_FEE_RATE, 0.0025);
    assert.equal(estimateFee('buy', 100, 80000), 12000);
    assert.equal(estimateFee('sell', 100, 90000), 22500);
});

test('tradePreview (buy) shows the total and the new average cost, fee included', () => {
    const p = tradePreview({ type: 'buy', qty: 300, price: 100000, fee: 30000, holding: { qty: 100, avg: 80000 } });
    assert.match(plain(p.html), /30\.030\.000₫/);           // 300*100,000 + 30,000
    assert.match(plain(p.html), /80\.000₫ → <b>95\.075₫/);      // (100*80,000 + 30,030,000) / 400
    assert.match(plain(p.html), /giữ 400 cp/);
});

test('tradePreview (sell) shows proceeds and coloured realised P&L', () => {
    const win = tradePreview({ type: 'sell', qty: 100, price: 110000, fee: 27500, holding: { qty: 400, avg: 95000 } });
    assert.equal(win.tone, 'sell');
    assert.match(plain(win.html), /10\.972\.500₫/);
    assert.match(win.html, /class="up">\+1\.472\.500₫ \(\+15,50%\)/);

    const loss = tradePreview({ type: 'sell', qty: 100, price: 70000, fee: 0, holding: { qty: 100, avg: 80000 } });
    assert.match(loss.html, /class="down">-1\.000\.000₫ \(-12,50%\)/);
    assert.match(loss.html, /rời khỏi danh mục/);             // selling the whole position
});

test('tradePreview warns about impossible sells and stays quiet without numbers', () => {
    assert.equal(tradePreview({ type: 'sell', qty: 5, price: 1000, holding: null }).tone, 'warn');
    assert.equal(tradePreview({ type: 'sell', qty: 500, price: 90000, holding: { qty: 100, avg: 80000 } }).tone, 'warn');
    assert.equal(tradePreview({ type: 'buy', qty: 0, price: 0, holding: null }), null);
    assert.match(plain(tradePreview({ type: 'buy', qty: 0, price: 0, holding: { qty: 100, avg: 80000 } }).html), /Đang giữ <b>100<\/b> cp/);
});

test('exchangeLabel maps the stored codes to what people call the exchanges', () => {
    assert.equal(exchangeLabel('HSX'), 'HOSE');
    assert.equal(exchangeLabel('UPCOM'), 'UPCoM');
    assert.equal(exchangeLabel('HNX'), 'HNX');
    assert.equal(exchangeLabel(null), '');
});

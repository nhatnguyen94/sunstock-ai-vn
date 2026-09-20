import assert from 'node:assert/strict';
import { test } from 'node:test';
import { formatAiText } from '../../resources/frontend/js/shared/ai-text.js';

test('markup coming from the model is escaped, never rendered', () => {
    const html = formatAiText('<script>alert(1)</script> <img src=x onerror=alert(1)>');
    assert.ok(!html.includes('<script'));
    assert.ok(!html.includes('<img'));
    assert.ok(html.includes('&lt;script&gt;'));
});

test('bold, italics and inline code become tags', () => {
    assert.equal(formatAiText('**VN-Index** tăng `1%`'), '<p><strong>VN-Index</strong> tăng <code>1%</code></p>');
    assert.ok(formatAiText('giá *có thể* tăng').includes('<em>có thể</em>'));
});

test('bold text cannot break out through an attribute-looking payload', () => {
    const html = formatAiText('**" onmouseover="x**');
    assert.ok(html.includes('&quot; onmouseover=&quot;x'));
    assert.ok(!/<[^>]*onmouseover=/.test(html));
});

test('bullets and numbered items become lists and consecutive items share one list', () => {
    assert.equal(formatAiText('- a\n- b'), '<ul><li>a</li><li>b</li></ul>');
    assert.equal(formatAiText('1. a\n2. b'), '<ol><li>a</li><li>b</li></ol>');
    assert.equal(formatAiText('- a\n\nsau đó'), '<ul><li>a</li></ul><p>sau đó</p>');
});

test('headings turn into a styled paragraph without the # marks', () => {
    assert.equal(formatAiText('## Nhận định'), '<p class="ai-heading">Nhận định</p>');
});

test('line breaks inside a paragraph are kept, blank lines start a new paragraph', () => {
    assert.equal(formatAiText('một\nhai\n\nba'), '<p>một<br>hai</p><p>ba</p>');
});

test('markdown tables render as a table and the separator row is dropped', () => {
    const html = formatAiText('| Mã | Giá |\n|---|---|\n| FPT | 120 |\n| VNM | 60 |');
    assert.ok(html.includes('<th>Mã</th><th>Giá</th>'));
    assert.ok(html.includes('<td>FPT</td><td>120</td>'));
    assert.equal((html.match(/<tr>/g) || []).length, 3);
    assert.ok(!html.includes('---'));
});

test('a <br> written by the model inside a table cell becomes a line break, other tags stay escaped', () => {
    const html = formatAiText('| A | B |\n|---|---|\n| x<br>y | <b onclick="z">k</b> |');
    assert.ok(html.includes('<td>x<br>y</td>'));
    assert.ok(html.includes('&lt;b onclick=&quot;z&quot;&gt;k&lt;/b&gt;'));
});

test('empty and missing input give an empty string', () => {
    assert.equal(formatAiText(''), '');
    assert.equal(formatAiText(null), '');
    assert.equal(formatAiText(undefined), '');
});

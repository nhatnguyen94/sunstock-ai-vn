// Safe rendering of AI answers. The model replies in Markdown (bold, bullets, headings, tables); showing it escaped as
// plain text made answers unreadable. Everything is HTML-escaped FIRST, then only a fixed set of tags is produced, so
// model output can never inject markup.

const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

function inline(escaped) {
    return escaped
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/(^|[^*])\*(?!\s)([^*\n]+?)\*(?!\*)/g, '$1<em>$2</em>')
        .replace(/`([^`\n]+)`/g, '<code>$1</code>')
        // models write <br> inside table cells; it arrives escaped, so re-enable exactly that one fixed tag
        .replace(/&lt;br\s*\/?&gt;/gi, '<br>');
}

const isTableRow = (l) => /^\s*\|.*\|\s*$/.test(l);
const isTableSep = (l) => /^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/.test(l);
const cells = (l) => l.trim().replace(/^\|/, '').replace(/\|$/, '').split('|').map((c) => inline(escapeHtml(c.trim())));

/** Markdown-lite → safe HTML string. */
export function formatAiText(text) {
    const lines = String(text ?? '').replace(/\r\n?/g, '\n').split('\n');
    const out = [];
    let list = null; // 'ul' | 'ol'
    let para = [];

    const flushPara = () => {
        if (para.length) {
            out.push('<p>' + para.join('<br>') + '</p>');
            para = [];
        }
    };
    const closeList = () => {
        if (list) {
            out.push(`</${list}>`);
            list = null;
        }
    };

    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];

        if (isTableRow(line)) {
            flushPara();
            closeList();
            const rows = [];
            while (i < lines.length && isTableRow(lines[i])) {
                if (!isTableSep(lines[i])) rows.push(cells(lines[i]));
                i++;
            }
            i--;
            const [head, ...body] = rows;
            out.push('<div class="ai-table-wrap"><table><thead><tr>' + head.map((c) => `<th>${c}</th>`).join('') + '</tr></thead><tbody>'
                + body.map((r) => '<tr>' + r.map((c) => `<td>${c}</td>`).join('') + '</tr>').join('') + '</tbody></table></div>');
            continue;
        }

        const bullet = line.match(/^\s*[-*•]\s+(.*)$/);
        const numbered = line.match(/^\s*\d+[.)]\s+(.*)$/);
        const heading = line.match(/^\s{0,3}#{1,6}\s+(.*)$/);

        if (bullet || numbered) {
            flushPara();
            const kind = bullet ? 'ul' : 'ol';
            if (list !== kind) {
                closeList();
                out.push(`<${kind}>`);
                list = kind;
            }
            out.push('<li>' + inline(escapeHtml((bullet || numbered)[1])) + '</li>');
        } else if (heading) {
            flushPara();
            closeList();
            out.push('<p class="ai-heading">' + inline(escapeHtml(heading[1].replace(/\*\*/g, ''))) + '</p>');
        } else if (/^\s*(-{3,}|\*{3,})\s*$/.test(line)) {
            flushPara();
            closeList();
        } else if (line.trim() === '') {
            flushPara();
            closeList();
        } else {
            closeList();
            para.push(inline(escapeHtml(line.trim())));
        }
    }
    flushPara();
    closeList();

    return out.join('');
}

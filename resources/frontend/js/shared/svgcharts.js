// Dependency-free charts for the shapes Lightweight Charts does not draw (it is time-series only):
// donut (ownership / allocation) and diverging horizontal bars (returns by period, industry weights).
// Plain SVG/HTML + CSS from css/shared/charts.css — nothing to download at runtime.

const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const nf = (d) => new Intl.NumberFormat('vi-VN', { minimumFractionDigits: d, maximumFractionDigits: d });

export const PALETTE = ['#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16', '#f97316', '#64748b', '#94a3b8'];

/**
 * Donut with a legend. `labels[i]`/`series[i]` are name/percent pairs. Hovering a legend row or a slice
 * highlights it and shows its value in the centre.
 */
export function donut(el, { labels, series, colors = PALETTE, centerLabel = 'Tổng' }) {
    if (!el) return;
    const total = series.reduce((a, b) => a + b, 0);
    if (!series.length || total <= 0) {
        el.innerHTML = '<p class="svgc-empty">Chưa có dữ liệu.</p>';
        return;
    }

    const R = 15.9155, C = 2 * Math.PI * R;   // r chosen so the circumference is exactly 100 units
    let offset = 0;
    const slices = series.map((v, i) => {
        const len = (v / total) * 100;
        const s = `<circle class="svgc-slice" data-i="${i}" cx="21" cy="21" r="${R}" fill="none" stroke="${colors[i % colors.length]}"
            stroke-width="6" stroke-dasharray="${len} ${100 - len}" stroke-dashoffset="${-offset}" transform="rotate(-90 21 21)"><title>${esc(labels[i])}: ${nf(2).format(v)}%</title></circle>`;
        offset += len;
        return s;
    }).join('');

    el.innerHTML = `
        <div class="svgc-donut">
            <svg viewBox="0 0 42 42" role="img" aria-label="Biểu đồ tròn">
                <circle cx="21" cy="21" r="${R}" fill="none" stroke="#f1f5f9" stroke-width="6"></circle>
                ${slices}
            </svg>
            <div class="svgc-center"><strong>${nf(1).format(total)}%</strong><span>${esc(centerLabel)}</span></div>
        </div>
        <ul class="svgc-legend">${labels.map((l, i) => `
            <li data-i="${i}"><i style="background:${colors[i % colors.length]}"></i><span>${esc(l)}</span><b>${nf(2).format(series[i])}%</b></li>`).join('')}
        </ul>`;

    const center = el.querySelector('.svgc-center');
    const base = center.innerHTML;
    const focus = (i) => {
        el.querySelectorAll('.svgc-slice').forEach((c) => c.classList.toggle('dim', i !== null && +c.dataset.i !== i));
        el.querySelectorAll('.svgc-legend li').forEach((li) => li.classList.toggle('dim', i !== null && +li.dataset.i !== i));
        center.innerHTML = i === null ? base : `<strong>${nf(2).format(series[i])}%</strong><span>${esc(labels[i])}</span>`;
    };
    el.querySelectorAll('[data-i]').forEach((n) => {
        n.addEventListener('mouseenter', () => focus(+n.dataset.i));
        n.addEventListener('mouseleave', () => focus(null));
    });
}

/**
 * Diverging horizontal bars. `groups` = [{ label, bars: [{ name, value|null, color }] }].
 * value = null renders "—" (e.g. fund too young for that window). Scale is shared so bars are comparable.
 */
export function bars(el, { groups, format = (v) => nf(2).format(v) + '%', signed = true }) {
    if (!el) return;
    const values = groups.flatMap((g) => g.bars.map((b) => b.value)).filter((v) => v !== null && v !== undefined);
    if (!values.length) {
        el.innerHTML = '<p class="svgc-empty">Chưa có dữ liệu.</p>';
        return;
    }
    const max = Math.max(...values.map(Math.abs)) || 1;
    const hasNeg = values.some((v) => v < 0);

    el.innerHTML = '<div class="svgc-bars">' + groups.map((g) => `
        <div class="svgc-group">
            <div class="svgc-group-label">${esc(g.label)}</div>
            ${g.bars.map((b) => {
                if (b.value === null || b.value === undefined) {
                    return `<div class="svgc-row"><span class="svgc-name" style="color:${b.color}">${esc(b.name)}</span><span class="svgc-na">—</span></div>`;
                }
                const w = (Math.abs(b.value) / max) * (hasNeg ? 50 : 100);
                const pos = b.value >= 0;
                const style = hasNeg
                    ? `width:${w}%;${pos ? 'left:50%' : `left:${50 - w}%`}`
                    : `width:${w}%;left:0`;
                return `<div class="svgc-row">
                    <span class="svgc-name" style="color:${b.color}">${esc(b.name)}</span>
                    <span class="svgc-track">${hasNeg ? '<em class="svgc-zero"></em>' : ''}<span class="svgc-bar" style="${style};background:${b.color}"></span></span>
                    <span class="svgc-val ${signed ? (pos ? 'up' : 'down') : ''}">${signed && pos && b.value > 0 ? '+' : ''}${format(b.value)}</span>
                </div>`;
            }).join('')}
        </div>`).join('') + '</div>';
}

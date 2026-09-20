// Watchlist page: render the rows, add by autocomplete, remove via ★, sort, and refresh every minute while the
// market is open. Rows arrive as JSON (WatchlistService::rows).

import { stockAutocomplete } from '../shared/autocomplete.js';
import { toast } from '../shared/toast.js';
import { initWatchlistStars, paintStars, setWatched } from '../shared/watchlist.js';
import { dirClass, esc, exchangeLabel, fmtInt, fmtPct, fmtValue, fmtVolume } from '../market/format.js';

const cfg = window.__WL__;
const $ = (id) => document.getElementById(id);
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

if (cfg) {
    let rows = cfg.rows || [];
    let sort = 'added';
    initWatchlistStars({ auth: true, watched: rows.map((r) => r.symbol) });

    const SORTERS = {
        added: () => 0,   // server order = most recently added first
        gain: (a, b) => (b.percent ?? -Infinity) - (a.percent ?? -Infinity),
        loss: (a, b) => (a.percent ?? Infinity) - (b.percent ?? Infinity),
        value: (a, b) => (b.value ?? -1) - (a.value ?? -1),
        symbol: (a, b) => a.symbol.localeCompare(b.symbol),
    };

    function render() {
        const body = $('wlBody');
        const sorted = rows.slice().sort(SORTERS[sort]);

        $('wlEmpty').hidden = rows.length > 0;
        $('wlCount').textContent = rows.length;

        const up = rows.filter((r) => (r.percent ?? 0) > 0).length;
        const down = rows.filter((r) => (r.percent ?? 0) < 0).length;
        $('wlStats').innerHTML = rows.length
            ? `<span class="wlp-chip">${rows.length} mã</span><span class="wlp-chip up">▲ ${up} tăng</span><span class="wlp-chip down">▼ ${down} giảm</span>`
            : '';

        body.innerHTML = sorted.map((r) => {
            const d = dirClass(r.percent);
            const sign = r.change > 0 ? '+' : '';
            return `<tr class="${d}" data-symbol="${esc(r.symbol)}">
                <td><button type="button" class="wl-star" data-watch="${esc(r.symbol)}" aria-label="Bỏ theo dõi ${esc(r.symbol)}"><i class="bi bi-star-fill"></i></button></td>
                <td><a class="wlp-sym" href="/stock?symbol=${encodeURIComponent(r.symbol)}">${esc(r.symbol)}</a>
                    ${r.exchange ? `<span class="wlp-ex">${esc(exchangeLabel(r.exchange))}</span>` : ''}
                    ${r.source === 'eod' ? `<span class="wlp-eod" title="Chưa có trong bảng giá thị trường — đóng cửa ngày ${esc(r.as_of)}">đóng cửa</span>` : ''}
                    <span class="wlp-nm" title="${esc(r.name)}">${esc(r.name)}</span></td>
                <td class="num"><strong>${fmtInt(r.price)}</strong>${r.at_ceiling ? '<span class="wlp-flag ceil">TRẦN</span>' : ''}${r.at_floor ? '<span class="wlp-flag floor">SÀN</span>' : ''}</td>
                <td class="num ${d}">${r.change === null ? '—' : sign + fmtInt(r.change)}</td>
                <td class="num"><span class="wlp-pct ${d}">${fmtPct(r.percent)}</span></td>
                <td class="num d-none d-md-table-cell">${fmtVolume(r.volume)}</td>
                <td class="num d-none d-md-table-cell">${fmtValue(r.value)}</td>
                <td class="d-none d-lg-table-cell">${r.spark ? `<svg class="wlp-spark" viewBox="0 0 100 32" preserveAspectRatio="none" aria-hidden="true"><polyline points="${esc(r.spark)}"/></svg>` : ''}</td>
                <td class="num"><span class="wlp-acts">
                    <a class="wlp-act" href="/company/${encodeURIComponent(r.symbol)}" title="Hồ sơ công ty"><i class="bi bi-building"></i></a>
                    <a class="wlp-act" href="/portfolio/add?symbol=${encodeURIComponent(r.symbol)}" title="Thêm vào danh mục"><i class="bi bi-briefcase"></i></a>
                </span></td>
            </tr>`;
        }).join('');
        paintStars(body);
    }

    $('wlSort').addEventListener('change', (e) => { sort = e.target.value; render(); });

    // Unfollowing from the table drops the row without a reload
    document.addEventListener('watchlist:change', (e) => {
        if (!e.detail.watched) {
            rows = rows.filter((r) => r.symbol !== e.detail.symbol);
            render();
        }
    });

    async function reload() {
        try {
            const res = await fetch(cfg.dataUrl, { headers: { Accept: 'application/json' } });
            if (!res.ok) return null;
            const d = await res.json();
            if (d.success) {
                rows = d.rows;
                setWatched(rows.map((r) => r.symbol));
                render();
            }
            return d;
        } catch (e) {
            return null;
        }
    }

    // ── add by symbol ────────────────────────────────────────────────────────
    const input = $('wlSymbol');
    const form = $('wlAddForm');
    const btn = $('wlAddBtn');

    async function add(symbol) {
        symbol = (symbol || '').trim().toUpperCase().split(/[\s—-]/)[0];
        if (!symbol || btn.disabled) return;   // Enter fires both onPick and submit: only the first one goes through
        btn.disabled = true;
        try {
            const res = await fetch(cfg.storeUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ symbol }),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.success) {
                toast(data.message, 'success', 2500);
                input.value = '';
                await reload();
            } else {
                toast(data.message || 'Không thêm được mã này.', 'warning', 4500);
            }
        } catch (e) {
            toast('Mất kết nối, vui lòng thử lại.', 'error', 4000);
        }
        btn.disabled = false;
    }

    stockAutocomplete(input, { maxItems: 8, onPick: (symbol) => add(symbol) });
    form.addEventListener('submit', (e) => { e.preventDefault(); add(input.value); });

    render();

    if (cfg.open) {
        let timer = setInterval(async () => {
            if (document.hidden) return;
            const d = await reload();
            if (d && !d.market_open) clearInterval(timer);
        }, 60000);
    }
}

// Home page market section: VN-Index chart, top movers (tabs + exchange chips), watchlist card, and a
// 60-second poll while the market is open. First paint is server-rendered; this adds interactivity + live data.

import { AreaSeries, HistogramSeries, attachLegend, fmtCompact, fmtDec, makeChart } from '../shared/charts.js';
import { initWatchlistStars, paintStars } from '../shared/watchlist.js';
import { dirClass, esc, exchangeLabel, fmtInt, fmtPct, fmtValue, fmtVolume } from './format.js';

const cfg = window.__MARKET__;
const $ = (id) => document.getElementById(id);

if (cfg) {
    initWatchlistStars({ auth: cfg.auth, watched: cfg.watched });

    const state = { tab: 'gainers', ex: 'ALL', movers: cfg.movers || {}, open: cfg.open };

    // ── movers table ─────────────────────────────────────────────────────────
    const moversBody = $('mkMovers');

    function renderMovers() {
        if (!moversBody) return;
        const list = state.movers?.[state.ex]?.[state.tab] || [];
        if (!list.length) {
            moversBody.innerHTML = '<tr><td colspan="6" class="mk-loading">Chưa có dữ liệu cho bộ lọc này.</td></tr>';
            return;
        }
        moversBody.innerHTML = list.map((m) => {
            const d = dirClass(m.percent);
            return `<tr>
                <td><button type="button" class="wl-star" data-watch="${esc(m.symbol)}" aria-label="Theo dõi ${esc(m.symbol)}"><i class="bi bi-star"></i></button></td>
                <td><a class="mk-sym" href="/stock?symbol=${encodeURIComponent(m.symbol)}">${esc(m.symbol)}</a><span class="mk-sub">${esc(exchangeLabel(m.exchange))}</span></td>
                <td class="num"><strong>${fmtInt(m.price)}</strong></td>
                <td class="num"><span class="mk-pct ${d}">${fmtPct(m.percent)}</span></td>
                <td class="num">${fmtValue(m.value)}</td>
                <td class="num d-none d-md-table-cell">${fmtVolume(m.volume)}</td>
            </tr>`;
        }).join('');
        paintStars(moversBody);
    }

    $('mkTabs')?.addEventListener('click', (e) => {
        const b = e.target.closest('button[data-tab]');
        if (!b) return;
        state.tab = b.dataset.tab;
        $('mkTabs').querySelectorAll('button').forEach((x) => x.classList.toggle('active', x === b));
        renderMovers();
    });
    $('mkExchanges')?.addEventListener('click', (e) => {
        const b = e.target.closest('button[data-ex]');
        if (!b) return;
        state.ex = b.dataset.ex;
        $('mkExchanges').querySelectorAll('button').forEach((x) => x.classList.toggle('active', x === b));
        renderMovers();
    });

    // ── watchlist card ───────────────────────────────────────────────────────
    const watchBody = $('mkWatchBody');

    function renderWatch(rows) {
        if (!watchBody) return;
        if (!rows || !rows.length) {
            watchBody.innerHTML = `<div class="mk-cta"><i class="bi bi-star"></i><h4>Chưa theo dõi mã nào</h4>
                <p>Bấm ★ ở bảng bên cạnh hoặc thêm mã trong trang danh sách theo dõi.</p>
                <a href="${esc(cfg.watchlistUrl)}" class="mk-btn mk-btn-primary">Thêm mã theo dõi</a></div>`;
            return;
        }
        watchBody.innerHTML = '<ul class="mk-wl">' + rows.map((r) => {
            const d = dirClass(r.percent);
            return `<li class="${d}">
                <button type="button" class="wl-star" data-watch="${esc(r.symbol)}" aria-label="Bỏ theo dõi ${esc(r.symbol)}"><i class="bi bi-star-fill"></i></button>
                <div><a class="mk-sym" href="/stock?symbol=${encodeURIComponent(r.symbol)}">${esc(r.symbol)}</a><span class="mk-nm" title="${esc(r.name)}">${esc(r.name)}</span></div>
                ${r.spark ? `<svg viewBox="0 0 100 32" preserveAspectRatio="none" aria-hidden="true"><polyline points="${esc(r.spark)}"/></svg>` : '<span></span>'}
                <div class="mk-px">${fmtInt(r.price)}<small class="${d}">${fmtPct(r.percent)}</small></div>
            </li>`;
        }).join('') + '</ul>';
        paintStars(watchBody);
    }

    // ── VN-Index chart ───────────────────────────────────────────────────────
    const chartEl = $('mkChart');
    let priceSeries = null;
    let volSeries = null;

    if (chartEl && cfg.series?.length) {
        const chart = makeChart(chartEl, { timeScale: { borderVisible: false, rightOffset: 2, timeVisible: false } });
        const up = cfg.series.length > 1 && cfg.series[cfg.series.length - 1][1] >= cfg.series[0][1];
        const color = up ? '#10b981' : '#ef4444';

        priceSeries = chart.addSeries(AreaSeries, {
            lineColor: color, lineWidth: 2, topColor: up ? 'rgba(16,185,129,.28)' : 'rgba(239,68,68,.28)', bottomColor: 'rgba(255,255,255,0)',
            priceLineVisible: false, priceFormat: { type: 'custom', formatter: fmtDec, minMove: 0.01 },
        });
        volSeries = chart.addSeries(HistogramSeries, { priceFormat: { type: 'volume' }, priceScaleId: 'vol', lastValueVisible: false, priceLineVisible: false });
        chart.priceScale('vol').applyOptions({ scaleMargins: { top: 0.82, bottom: 0 } });
        chart.priceScale('right').applyOptions({ scaleMargins: { top: 0.08, bottom: 0.22 } });

        const load = (series) => {
            priceSeries.setData(series.map(([d, c]) => ({ time: d, value: c })));
            volSeries.setData(series.map(([d, c, v], i) => ({
                time: d, value: v, color: i > 0 && c < series[i - 1][1] ? 'rgba(239,68,68,.45)' : 'rgba(16,185,129,.45)',
            })));
            chart.timeScale().fitContent();
        };
        load(cfg.series);

        attachLegend(chart, chartEl, (param) => {
            const p = param.seriesData.get(priceSeries)?.value;
            const v = param.seriesData.get(volSeries)?.value;
            if (p === undefined) return null;
            const t = typeof param.time === 'string' ? param.time.split('-').reverse().join('/') : '';
            return `<span class="lwc-date">${t}</span><span>VN-Index <b>${fmtDec(p)}</b></span>` + (v !== undefined ? `<span>KL <b>${fmtCompact(v)}</b></span>` : '');
        });
    }

    // ── live updates ─────────────────────────────────────────────────────────
    function applyData(d) {
        state.movers = d.movers || state.movers;
        state.open = d.market_open;
        renderMovers();
        if (d.watchlist) renderWatch(d.watchlist);

        (d.indices || []).forEach((i) => {
            const card = document.querySelector(`[data-idx="${i.code}"]`);
            if (!card) return;
            const cls = dirClass(i.change);
            card.className = `mk-idx ${cls}`;
            card.querySelector('.mk-idx-close').textContent = new Intl.NumberFormat('vi-VN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(i.close);
            const chg = card.querySelector('.mk-idx-chg');
            chg.className = `mk-idx-chg ${cls}`;
            chg.querySelector('i').className = 'bi ' + (i.change > 0 ? 'bi-caret-up-fill' : i.change < 0 ? 'bi-caret-down-fill' : 'bi-dash');
            chg.querySelector('span').textContent = `${new Intl.NumberFormat('vi-VN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Math.abs(i.change))} (${fmtPct(Math.abs(i.percent), false)})`;

            if (i.code === 'VNINDEX' && priceSeries && i.series?.length) {
                const last = i.series[i.series.length - 1];
                priceSeries.update({ time: last[0], value: last[1] });
                volSeries.update({ time: last[0], value: last[2], color: i.change < 0 ? 'rgba(239,68,68,.45)' : 'rgba(16,185,129,.45)' });
            }
        });

        const b = d.breadth;
        if (b) {
            const tot = Math.max(b.total || 0, 1);
            $('mkAdv').textContent = fmtInt(b.advancers);
            $('mkUnch').textContent = fmtInt(b.unchanged);
            $('mkDec').textContent = fmtInt(b.decliners);
            $('mkCeil').textContent = b.ceiling;
            $('mkFloor').textContent = b.floor;
            const [a, u, dn] = $('mkBreadthBar').children;
            a.style.width = (b.advancers / tot * 100) + '%';
            u.style.width = (b.unchanged / tot * 100) + '%';
            dn.style.width = (b.decliners / tot * 100) + '%';
        }

        if (d.liquidity) {
            $('mkLiq').textContent = fmtValue(d.liquidity.value) + ' ₫';
            const c = $('mkLiqCmp');
            if (d.liquidity.change_percent !== null && d.liquidity.change_percent !== undefined) {
                c.textContent = `${fmtPct(d.liquidity.change_percent)} so với phiên trước`;
                c.className = `mk-liq-cmp ${dirClass(d.liquidity.change_percent)}`;
            }
        }
        Object.entries(d.exchanges || {}).forEach(([k, v]) => {
            const el = document.querySelector(`[data-ex-value="${k}"]`);
            if (el) el.textContent = fmtValue(v.value) + ' ₫';
        });

        if (d.synced_at) $('mkUpdated').textContent = new Date(d.synced_at).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Ho_Chi_Minh' });
        const status = $('mkStatus');
        status.classList.toggle('open', !!d.market_open);
        status.lastChild.textContent = d.market_open ? ' Đang giao dịch' : ' Ngoài giờ giao dịch';
    }

    renderMovers();
    if (cfg.auth) renderWatch(cfg.watchlist);

    // A star toggled elsewhere on the page changes the card: reload it from the server
    document.addEventListener('watchlist:change', async () => {
        try {
            const res = await fetch(cfg.dataUrl, { headers: { Accept: 'application/json' } });
            if (res.ok) renderWatch((await res.json()).watchlist);
        } catch (e) { /* the star already toasted; the card refreshes on the next poll */ }
    });

    if (cfg.open) {
        let timer = null;
        const poll = async () => {
            if (document.hidden) return;
            try {
                const res = await fetch(cfg.dataUrl, { headers: { Accept: 'application/json' } });
                if (res.ok) {
                    const d = await res.json();
                    if (d.success) applyData(d);
                    if (!d.market_open) clearInterval(timer);
                }
            } catch (e) { /* offline: try again next tick */ }
        };
        timer = setInterval(poll, 60000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
    }
}

// Company profile page: donut charts, event tabs, collapsible text, first-visit loader, manual refresh.

import { donut } from '../shared/svgcharts.js';
import { toast } from '../shared/toast.js';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/** POST /company/{symbol}/load — returns {ok, status, data}. */
async function loadProfile(url, force) {
    const res = await fetch(url + (force ? '?force=1' : ''), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
    });
    let data = {};
    try { data = await res.json(); } catch (e) { /* non-JSON error page */ }
    return { ok: res.ok && data.success, status: res.status, data };
}

// ── First visit: no cached profile yet ──────────────────────────────────────
const state = document.getElementById('cpState');
if (state && state.dataset.notFound !== '1') {
    const retry = document.getElementById('cpRetry');
    const run = async () => {
        retry?.classList.add('d-none');
        document.getElementById('cpSpinner')?.classList.remove('d-none');
        document.getElementById('cpStateTitle').textContent = `Đang tải hồ sơ ${state.dataset.symbol} lần đầu…`;
        try {
            const r = await loadProfile(state.dataset.loadUrl, false);
            if (r.ok) {
                window.location.reload();
                return;
            }
            if (r.status === 404) { // unknown symbol: a final answer, so show it instead of reloading in a loop
                document.getElementById('cpSpinner')?.classList.add('d-none');
                document.getElementById('cpStateTitle').textContent = `Không tìm thấy thông tin cho mã ${state.dataset.symbol}`;
                document.getElementById('cpStateText').textContent = r.data.error || 'Mã này không phải cổ phiếu niêm yết.';
                return;
            }
            throw new Error(r.data.error || 'Không tải được dữ liệu');
        } catch (e) {
            document.getElementById('cpSpinner')?.classList.add('d-none');
            document.getElementById('cpStateTitle').textContent = 'Chưa tải được hồ sơ công ty';
            document.getElementById('cpStateText').textContent = e.message + ' — nguồn dữ liệu có thể đang chậm, hãy thử lại sau ít giây.';
            retry?.classList.remove('d-none');
        }
    };
    retry?.addEventListener('click', run);
    run();
}

// ── Manual refresh ──────────────────────────────────────────────────────────
const fresh = document.getElementById('cpFresh');
document.getElementById('cpRefresh')?.addEventListener('click', async function () {
    this.disabled = true;
    this.innerHTML = '<i class="bi bi-hourglass-split"></i> Đang làm mới…';
    try {
        const r = await loadProfile(fresh.dataset.loadUrl, true);
        if (r.ok) {
            window.location.reload();
            return;
        }
        toast(r.data.error || 'Không làm mới được, thử lại sau.', 'warning');
    } catch (e) {
        toast('Lỗi kết nối, thử lại sau.', 'warning');
    }
    this.disabled = false;
    this.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Làm mới';
});

// ── Collapsible long text ───────────────────────────────────────────────────
document.querySelectorAll('.cp-collapsible').forEach((el) => {
    if (el.scrollHeight <= el.clientHeight + 8) {
        el.classList.add('is-short');
    } else {
        el.addEventListener('click', () => el.classList.add('open'));
    }
});

// ── Event tabs ──────────────────────────────────────────────────────────────
document.querySelectorAll('#cpEventTabs button').forEach((btn) => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('#cpEventTabs button').forEach((b) => b.classList.toggle('active', b === btn));
        document.querySelectorAll('.cp-tabpane').forEach((p) => p.classList.toggle('active', p.dataset.pane === btn.dataset.tab));
    });
});

// ── Section nav highlight ───────────────────────────────────────────────────
const links = [...document.querySelectorAll('#cpNav a')];
if (links.length && 'IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
        entries.forEach((en) => {
            if (en.isIntersecting) {
                links.forEach((a) => a.classList.toggle('active', a.getAttribute('href') === '#' + en.target.id));
            }
        });
    }, { rootMargin: '-30% 0px -60% 0px' });
    links.forEach((a) => {
        const sec = document.querySelector(a.getAttribute('href'));
        if (sec) io.observe(sec);
    });
}

// ── Donut charts (dependency-free SVG, see shared/svgcharts.js) ─────────────
if (window.__COMPANY__) {
    donut(document.querySelector('#cpOwnershipChart'), { ...window.__COMPANY__.ownership, centerLabel: 'Cơ cấu sở hữu' });
    donut(document.querySelector('#cpHoldersChart'), { ...window.__COMPANY__.holders, centerLabel: 'Cổ đông lớn' });
}

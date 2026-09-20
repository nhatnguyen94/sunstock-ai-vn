// ★ follow / unfollow buttons. Any element with `data-watch="FPT"` toggles that symbol through the watchlist API and
// keeps every button for the same symbol in sync (a symbol can appear in several places on one page).
//   <button class="wl-star" data-watch="FPT"><i class="bi bi-star"></i></button>
// Pages call initWatchlistStars({ auth, watched }) once; rows rendered later call paintStars().

import { toast } from './toast.js';

const state = { auth: false, watched: new Set() };
let bound = false;

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/** Sync every [data-watch] button under `root` with the watched set. */
export function paintStars(root = document) {
    root.querySelectorAll('[data-watch]').forEach((btn) => {
        const on = state.watched.has(btn.dataset.watch);
        btn.classList.toggle('is-on', on);
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        btn.title = on ? 'Bỏ theo dõi ' + btn.dataset.watch : 'Theo dõi ' + btn.dataset.watch;
        const icon = btn.querySelector('i.bi');
        if (icon) icon.className = 'bi ' + (on ? 'bi-star-fill' : 'bi-star');
        const label = btn.querySelector('[data-watch-label]');
        if (label) label.textContent = on ? 'Đang theo dõi' : 'Theo dõi';
    });
}

export function isWatched(symbol) {
    return state.watched.has(symbol);
}

export function setWatched(symbols) {
    state.watched = new Set(symbols);
    paintStars();
}

async function toggle(btn) {
    if (!state.auth) {
        window.location.href = '/login';
        return;
    }

    const symbol = btn.dataset.watch;
    const on = state.watched.has(symbol);
    btn.disabled = true;

    try {
        const res = await fetch(on ? `/watchlist/${encodeURIComponent(symbol)}` : '/watchlist', {
            method: on ? 'DELETE' : 'POST',
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'Content-Type': 'application/json' },
            body: on ? undefined : JSON.stringify({ symbol }),
        });

        if (res.status === 401 || res.status === 419) {
            window.location.href = '/login';
            return;
        }

        const data = await res.json().catch(() => ({}));
        if (res.ok && data.success) {
            if (on) state.watched.delete(symbol); else state.watched.add(symbol);
            paintStars();
            toast(data.message, 'success', 2500);
            document.dispatchEvent(new CustomEvent('watchlist:change', { detail: { symbol, watched: !on } }));
        } else {
            toast(data.message || 'Không thực hiện được, thử lại sau.', 'warning', 4000);
        }
    } catch (e) {
        toast('Mất kết nối, vui lòng thử lại.', 'error', 4000);
    } finally {
        btn.disabled = false;
    }
}

export function initWatchlistStars({ auth = false, watched = [] } = {}) {
    state.auth = !!auth;
    state.watched = new Set(watched);
    paintStars();

    if (!bound) {
        bound = true;
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-watch]');
            if (!btn) return;
            e.preventDefault();
            toggle(btn);
        });
    }
}

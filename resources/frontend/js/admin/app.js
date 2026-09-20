// Admin shell behaviour: Tabler/Bootstrap components, theme + sidebar preferences, command palette (Ctrl/⌘+K),
// toasts (server flash messages included) and a real confirm dialog instead of window.confirm().
// Pure helpers live in ./helpers.js (unit-tested in Node: tests/js/admin.test.mjs).

import { Alert, Collapse, Dropdown, Modal, Offcanvas, Popover, Tab, Toast, Tooltip } from '@tabler/core/dist/js/tabler.esm.min.js';
import { filterCommands, moveSelection, toastIcon } from './helpers.js';

// Inline page scripts (queue monitor, stocks…) use `bootstrap.Modal` etc.
if (typeof window !== 'undefined') {
    window.bootstrap = { Alert, Collapse, Dropdown, Modal, Offcanvas, Popover, Tab, Toast, Tooltip };
}

// ── browser wiring ───────────────────────────────────────────────────────────

if (typeof document !== 'undefined') {
    const root = document.documentElement;
    const store = {
        get: (k) => { try { return localStorage.getItem(k); } catch (e) { return null; } },
        set: (k, v) => { try { localStorage.setItem(k, v); } catch (e) { /* private mode: preference just isn't remembered */ } },
    };

    // Theme (light / dark / auto). The <head> already applied the stored value before first paint.
    const applyTheme = (choice) => {
        store.set('tabler-theme', choice);
        const dark = choice === 'dark' || (choice === 'auto' && matchMedia('(prefers-color-scheme: dark)').matches);
        root.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
        document.querySelectorAll('[data-theme-choice]').forEach((b) => b.classList.toggle('active', b.dataset.themeChoice === choice));
        const icon = document.querySelector('[data-theme-icon]');
        if (icon) icon.className = 'ti ' + (choice === 'auto' ? 'ti-device-desktop' : dark ? 'ti-moon' : 'ti-sun');
    };
    document.querySelectorAll('[data-theme-choice]').forEach((b) => b.addEventListener('click', () => applyTheme(b.dataset.themeChoice)));
    applyTheme(store.get('tabler-theme') || 'auto');
    matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => { if ((store.get('tabler-theme') || 'auto') === 'auto') applyTheme('auto'); });

    // Sidebar: collapse to icons (desktop), remembered
    document.querySelectorAll('[data-sidebar-toggle]').forEach((b) => b.addEventListener('click', () => {
        const collapsed = root.classList.toggle('sidebar-collapsed');
        store.set('ad-sidebar', collapsed ? 'collapsed' : 'open');
    }));

    // Tooltips (icon-only sidebar items carry their label in data-bs-title)
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new Tooltip(el));

    // ── Toasts ───────────────────────────────────────────────────────────────
    const toastBox = document.getElementById('adToasts');
    window.adToast = (message, type = 'info', ms = 5000) => {
        if (!toastBox) return;
        const el = document.createElement('div');
        el.className = `ad-toast ${type}`;
        el.setAttribute('role', type === 'error' ? 'alert' : 'status');
        const icon = document.createElement('i');
        icon.className = 'ti ' + toastIcon(type);
        const text = document.createElement('div');
        text.textContent = message;          // textContent: server strings are never parsed as HTML
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'ad-toast-close';
        close.setAttribute('aria-label', 'Đóng');
        close.innerHTML = '<i class="ti ti-x"></i>';
        el.append(icon, text, close);
        const dismiss = () => { el.classList.add('leaving'); setTimeout(() => el.remove(), 220); };
        close.addEventListener('click', dismiss);
        toastBox.appendChild(el);
        if (ms > 0) setTimeout(dismiss, ms);
    };
    // Server flash messages are rendered as hidden [data-flash] nodes (also readable without JS)
    document.querySelectorAll('[data-flash]').forEach((n) => window.adToast(n.textContent.trim(), n.dataset.flash, n.dataset.flash === 'error' ? 8000 : 5000));

    // ── Confirm dialog ───────────────────────────────────────────────────────
    const confirmEl = document.getElementById('adConfirm');
    let confirmModal = null;
    window.adConfirm = (message, { title = 'Xác nhận', ok = 'Đồng ý', tone = 'danger' } = {}) => new Promise((resolve) => {
        if (!confirmEl) { resolve(window.confirm(message)); return; }
        confirmModal ??= new Modal(confirmEl);
        confirmEl.querySelector('[data-confirm-title]').textContent = title;
        confirmEl.querySelector('[data-confirm-message]').textContent = message;
        const okBtn = confirmEl.querySelector('[data-confirm-ok]');
        okBtn.textContent = ok;
        okBtn.className = 'btn ' + (tone === 'danger' ? 'btn-danger' : 'btn-primary');
        confirmEl.querySelector('[data-confirm-icon]').className = 'ti ' + (tone === 'danger' ? 'ti-trash text-danger' : 'ti-help-circle text-primary') + ' ad-confirm-icon';
        let answered = false;
        const done = (v) => { if (answered) return; answered = true; okBtn.onclick = null; confirmEl.removeEventListener('hidden.bs.modal', onHidden); resolve(v); };
        const onHidden = () => done(false);
        confirmEl.addEventListener('hidden.bs.modal', onHidden);
        okBtn.onclick = () => { done(true); confirmModal.hide(); };
        confirmModal.show();
    });

    const confirmOpts = (el) => ({ title: el.dataset.confirmTitle || 'Xác nhận', ok: el.dataset.confirmOk || 'Đồng ý', tone: el.dataset.confirmTone || 'danger' });

    // <form data-confirm="…"> — asks before submitting
    document.addEventListener('submit', async (e) => {
        const form = e.target.closest('form[data-confirm]');
        if (!form || form.dataset.confirmed === '1') return;
        e.preventDefault();
        const submitter = e.submitter;
        if (await window.adConfirm(form.dataset.confirm, confirmOpts(form))) {
            form.dataset.confirmed = '1';
            submitter ? form.requestSubmit(submitter) : form.submit();
        }
    });
    // <button|a data-confirm="…"> outside a confirming form
    document.addEventListener('click', async (e) => {
        const el = e.target.closest('button[data-confirm], a[data-confirm]');
        if (!el || el.closest('form[data-confirm]') || el.dataset.confirmed === '1') return;
        e.preventDefault();
        if (await window.adConfirm(el.dataset.confirm, confirmOpts(el))) {
            el.dataset.confirmed = '1';
            el.click();
        }
    });

    // ── Command palette ──────────────────────────────────────────────────────
    const palette = document.getElementById('adPalette');
    if (palette) {
        const input = palette.querySelector('input');
        const list = palette.querySelector('.ad-palette-list');
        let items = [];
        try { items = JSON.parse(document.getElementById('adNavData')?.textContent || '[]'); } catch (e) { items = []; }
        let shown = [];
        let sel = 0;

        const render = () => {
            shown = filterCommands(items, input.value);
            sel = Math.min(sel, Math.max(shown.length - 1, 0));
            if (!shown.length) {
                list.innerHTML = '<div class="ad-palette-empty">Không tìm thấy trang nào khớp.</div>';
                return;
            }
            let html = '';
            let group = null;
            shown.forEach((it, i) => {
                if (input.value.trim() === '' && it.group !== group) { group = it.group; html += `<div class="ad-palette-group"></div>`; }
                html += `<a class="ad-palette-item${i === sel ? ' sel' : ''}" href="${it.href.replace(/"/g, '&quot;')}" data-i="${i}"><i class="ti ${it.icon || 'ti-arrow-right'}"></i><span></span><small></small></a>`;
            });
            list.innerHTML = html;
            const groups = list.querySelectorAll('.ad-palette-group');
            let g = 0;
            let last = null;
            list.querySelectorAll('.ad-palette-item').forEach((a) => {
                const it = shown[+a.dataset.i];
                a.querySelector('span').textContent = it.title;        // textContent: titles are never parsed as HTML
                a.querySelector('small').textContent = input.value.trim() ? it.group || '' : '';
                if (input.value.trim() === '' && it.group !== last && groups[g]) { groups[g++].textContent = it.group; last = it.group; }
            });
            list.querySelector('.sel')?.scrollIntoView({ block: 'nearest' });
        };
        const open = () => { palette.classList.add('open'); input.value = ''; sel = 0; render(); setTimeout(() => input.focus(), 30); };
        const close = () => palette.classList.remove('open');

        document.querySelectorAll('[data-palette-open]').forEach((b) => b.addEventListener('click', open));
        palette.addEventListener('mousedown', (e) => { if (e.target === palette) close(); });
        input.addEventListener('input', () => { sel = 0; render(); });
        list.addEventListener('mousemove', (e) => {
            const a = e.target.closest('.ad-palette-item');
            if (a && +a.dataset.i !== sel) { sel = +a.dataset.i; list.querySelectorAll('.ad-palette-item').forEach((x) => x.classList.toggle('sel', x === a)); }
        });
        document.addEventListener('keydown', (e) => {
            const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement?.tagName) || document.activeElement?.isContentEditable;
            if ((e.key === 'k' && (e.ctrlKey || e.metaKey)) || (e.key === '/' && !typing && !palette.classList.contains('open'))) { e.preventDefault(); palette.classList.contains('open') ? close() : open(); return; }
            if (!palette.classList.contains('open')) return;
            if (e.key === 'Escape') { close(); return; }
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                sel = moveSelection(sel, e.key === 'ArrowDown' ? 1 : -1, shown.length);
                list.querySelectorAll('.ad-palette-item').forEach((x, i) => x.classList.toggle('sel', i === sel));
                list.querySelector('.sel')?.scrollIntoView({ block: 'nearest' });
            }
            if (e.key === 'Enter' && shown[sel]) { e.preventDefault(); window.location.href = shown[sel].href; }
        });
    }
}

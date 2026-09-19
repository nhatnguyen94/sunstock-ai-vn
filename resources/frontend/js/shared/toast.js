// Tiny toast helper for page scripts. layouts/app.js has its own showToast, but it is
// module-scoped (not on window), so pages that need a toast import this one instead.
// It renders into the layout's #toastContainer and reuses its .toast-item styles.

export function toast(message, type = 'info', duration = 3500) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const icons = { success: 'check-circle-fill', error: 'x-circle-fill', warning: 'exclamation-triangle-fill', info: 'info-circle-fill' };
    const item = document.createElement('div');
    item.className = `toast-item ${type}`;

    const icon = document.createElement('i');
    icon.className = `bi bi-${icons[type] || icons.info}`;
    icon.style.fontSize = '1.2rem';
    const text = document.createElement('span');
    text.style.flex = '1';
    text.textContent = message; // textContent: server error strings are never parsed as HTML

    item.append(icon, text);
    item.addEventListener('click', () => item.remove());
    container.appendChild(item);
    setTimeout(() => item.remove(), duration);
}

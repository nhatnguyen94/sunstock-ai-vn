// Portfolio list: per-card overflow menus and the delete-confirmation modal.

const menus = [...document.querySelectorAll('.pf-menu')];

const closeAll = (except = null) => menus.forEach((m) => {
    if (m !== except) {
        m.classList.remove('open');
        m.querySelector('.pf-menu-btn')?.setAttribute('aria-expanded', 'false');
    }
});

menus.forEach((m) => {
    const btn = m.querySelector('.pf-menu-btn');
    btn?.addEventListener('click', (e) => {
        e.stopPropagation();
        closeAll(m);
        const open = m.classList.toggle('open');
        btn.setAttribute('aria-expanded', String(open));
    });
});
document.addEventListener('click', () => closeAll());
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeAll(); });

document.querySelectorAll('.pf-del-portfolio').forEach((b) => b.addEventListener('click', () => {
    closeAll();
    document.getElementById('pfDeletePortfolioForm').action = b.dataset.action;
    document.getElementById('pfDelName').textContent = b.dataset.name;
    window.$('#pfDeletePortfolio').modal('show');
}));

document.getElementById('pfDeletePortfolioForm')?.addEventListener('submit', (e) => {
    e.target.querySelectorAll('button[type="submit"]').forEach((s) => { s.disabled = true; });
});

import { initAiChat } from '../shared/ai-chat.js';

// ── AOS Init ──────────────────────────────────────
        AOS.init({ duration: 650, once: true, offset: 60, easing: 'ease-out-cubic' });

        // ── NProgress ─────────────────────────────────────
        NProgress.configure({ showSpinner: false, trickleSpeed: 200 });
        document.addEventListener('click', function(e) {
            const a = e.target.closest('a[href]');
            if (a && !a.getAttribute('href').startsWith('#') && !a.getAttribute('target') && !a.getAttribute('onclick')) {
                NProgress.start();
            }
        });
        window.addEventListener('pageshow', function() { NProgress.done(); });
        window.addEventListener('load', function() { NProgress.done(); });

        // ── Back to top ───────────────────────────────────
        const btt = document.getElementById('backToTop');
        window.addEventListener('scroll', function() {
            btt.classList.toggle('visible', window.scrollY > 400);
        });
        btt.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // ── Toast system ──────────────────────────────────
        function showToast(message, type = 'info', duration = 3500) {
            const icons = { success: 'check-circle-fill', error: 'x-circle-fill', warning: 'exclamation-triangle-fill', info: 'info-circle-fill' };
            const colors = { success: 'var(--success-green)', error: 'var(--danger-red)', warning: 'var(--warning-orange)', info: 'var(--primary-blue)' };
            const container = document.getElementById('toastContainer');
            const t = document.createElement('div');
            t.className = `toast-item ${type}`;
            t.innerHTML = `<i class="bi bi-${icons[type]||icons.info}" style="color:${colors[type]};font-size:1.2rem;flex-shrink:0;"></i><span style="flex:1;">${message}</span><i class="bi bi-x" style="color:var(--text-secondary);flex-shrink:0;"></i>`;
            t.addEventListener('click', () => t.remove());
            container.appendChild(t);
            setTimeout(() => { t.style.opacity='0'; t.style.transform='translateX(100px)'; t.style.transition='all 0.3s ease'; setTimeout(() => t.remove(), 300); }, duration);
        }

        // ── AI Chat (see shared/ai-chat.js) ────────────────
        initAiChat();


        // ── Animate numbers (counter-up) ─────────────────
        function animateCounter(el) {
            const target = parseFloat(el.dataset.target || el.textContent.replace(/[^0-9.]/g,''));
            const isFloat = String(el.dataset.target||'').includes('.');
            const decimals = isFloat ? 1 : 0;
            const duration = 1500, step = 16;
            let current = 0, steps = duration / step;
            const inc = target / steps;
            const timer = setInterval(() => {
                current += inc;
                if (current >= target) { current = target; clearInterval(timer); }
                el.textContent = (isFloat ? current.toFixed(decimals) : Math.floor(current)).toLocaleString('vi-VN');
            }, step);
        }
        const counterObserver = new IntersectionObserver((entries) => {
            entries.forEach(e => { if (e.isIntersecting && !e.target.dataset.animated) { e.target.dataset.animated = '1'; animateCounter(e.target); } });
        }, { threshold: 0.5 });
        document.querySelectorAll('[data-counter]').forEach(el => counterObserver.observe(el));

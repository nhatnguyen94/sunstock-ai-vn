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

        // ── AI Chat ───────────────────────────────────────
        document.getElementById('aiChatOpenBtn').onclick = function () {
            const popup = document.getElementById('aiChatPopup');
            popup.style.display = popup.style.display === 'none' ? 'block' : 'none';
            this.style.transform = 'scale(0.9)';
            setTimeout(() => this.style.transform = 'scale(1)', 150);
        }
        
        function closeAiChat() {
            document.getElementById('aiChatPopup').style.display = 'none';
        }
        
        function setAiQuestion(q) {
            document.getElementById('aiChatInput').value = q;
            document.getElementById('aiChatInput').focus();
        }
        
        function sendAiChat() {
            let msg = document.getElementById('aiChatInput').value.trim();
            let lang = document.getElementById('aiLangSelect').value;
            if (!msg) return;
            
            let box = document.getElementById('aiChatMessages');
            box.innerHTML += `<div style="margin-bottom:10px;text-align:right;">
                <span style="background:linear-gradient(135deg,var(--primary-blue),var(--secondary-blue));color:white;border-radius:18px 18px 4px 18px;padding:10px 15px;display:inline-block;max-width:85%;font-weight:500;font-size:0.9rem;">${msg}</span>
            </div>`;
            document.getElementById('aiChatInput').value = '';
            
            box.innerHTML += `<div id="aiLoading" style="margin-bottom:10px;">
                <span style="background:white;border:1px solid var(--border-color);border-radius:18px 18px 18px 4px;padding:10px 15px;display:inline-flex;align-items:center;gap:8px;font-size:0.9rem;">
                    <span class="loading" style="border-top-color:var(--primary-blue);border-color:rgba(37,99,235,0.2);border-top-color:var(--primary-blue);"></span> AI đang phân tích...
                </span>
            </div>`;
            box.scrollTop = box.scrollHeight;
            
            fetch('/ai-chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
                body: JSON.stringify({ message: msg, lang: lang })
            }).then(res => res.json()).then(data => {
                const loading = document.getElementById('aiLoading');
                if (loading) loading.remove();
                box.innerHTML += `<div style="margin-bottom:10px;">
                    <div style="display:flex;align-items:flex-start;gap:8px;">
                        <div style="width:30px;height:30px;background:linear-gradient(135deg,var(--primary-blue),var(--secondary-blue));border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:4px;">
                            <i class="bi bi-robot" style="color:white;font-size:0.85rem;"></i>
                        </div>
                        <span style="background:white;border:1px solid var(--border-color);border-radius:4px 18px 18px 18px;padding:10px 15px;display:inline-block;max-width:85%;font-size:0.9rem;line-height:1.5;">${data.answer}</span>
                    </div>
                </div>`;
                box.scrollTop = box.scrollHeight;
            }).catch(() => {
                const loading = document.getElementById('aiLoading');
                if (loading) loading.remove();
                box.innerHTML += `<div style="margin-bottom:10px;"><span style="background:#fee2e2;color:#dc2626;border-radius:4px 18px 18px 18px;padding:10px 15px;display:inline-block;font-size:0.9rem;"><i class="bi bi-exclamation-triangle"></i> Có lỗi, vui lòng thử lại!</span></div>`;
                box.scrollTop = box.scrollHeight;
            });
        }
        
        function clearAiChat() {
            document.getElementById('aiChatMessages').innerHTML = `<div style="text-align:center;color:var(--text-secondary);font-size:0.85rem;padding:1rem;">
                <i class="bi bi-robot" style="font-size:2rem;color:var(--primary-blue);display:block;margin-bottom:8px;"></i>
                Xin chào! Tôi là Sun Stock AI.<br>Hỏi tôi bất cứ điều gì về thị trường chứng khoán!
            </div>`;
        }
        
        document.getElementById('aiLangSelect').onchange = function() {
            document.getElementById('aiFlagIcon').innerHTML = `<img src="https://flagcdn.com/24x18/${this.value === 'vi' ? 'vn' : 'us'}.png" style="width:26px;height:20px;border-radius:4px;">`;
        };
        document.getElementById('aiChatInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') sendAiChat();
        });
        document.addEventListener('click', function(e) {
            const bubble = document.getElementById('aiChatBubble');
            const popup = document.getElementById('aiChatPopup');
            if (!bubble.contains(e.target)) popup.style.display = 'none';
        });

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

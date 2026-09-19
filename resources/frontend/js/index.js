import { stockAutocomplete } from './shared/autocomplete.js';

document.addEventListener('DOMContentLoaded', function() {
    const symbolInput = document.getElementById('symbol');
    const notFoundMsg = document.getElementById('notFoundMsg');

    stockAutocomplete(symbolInput, {
        maxItems: 8,
        onResults: (data) => notFoundMsg.classList.toggle('show', Array.isArray(data) && data.length === 0),
        // Picking a suggestion searches straight away
        onPick: () => {
            const form = document.querySelector('.search-form-wrapper');
            const btn = form.querySelector('.search-btn');
            btn.disabled = true;
            btn.querySelector('i').className = 'loading';
            btn.querySelector('.btn-text').textContent = 'Đang tìm...';
            form.submit();
        },
    });

    // Form submission with loading state
    const searchForm = document.querySelector('.search-form-wrapper');
    const searchBtn = searchForm.querySelector('.search-btn');
    const btnText = searchBtn.querySelector('.btn-text');
    const btnIcon = searchBtn.querySelector('i');

    searchForm.addEventListener('submit', function(e) {
        const symbolValue = symbolInput.value.trim();
        
        if (!symbolValue) {
            e.preventDefault();
            symbolInput.focus();
            return;
        }

        // Show loading state
        searchBtn.disabled = true;
        btnIcon.className = 'loading';
        btnText.textContent = 'Đang tìm...';
        
        // Fallback to re-enable button
        setTimeout(() => {
            searchBtn.disabled = false;
            btnIcon.className = 'bi bi-search';
            btnText.textContent = 'Tra cứu';
        }, 5000);
    });

    // Hide error message when user starts typing again
    symbolInput.addEventListener('focus', function() {
        document.getElementById('notFoundMsg').classList.remove('show');
    });

    // Smooth scroll for internal links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Add keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Focus search input with Ctrl/Cmd + K
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            symbolInput.focus();
        }
    });

    // Scroll to hot industries section when paginate is clicked
    document.addEventListener('click', function(e) {
        const paginateLink = e.target.closest('#hot-industries-table .pagination a');
        if (paginateLink) {
            // Thêm fragment identifier để scroll về section
            const url = new URL(paginateLink.href);
            url.hash = '#hot-industries-section';
            paginateLink.href = url.toString();
        }
    });

    // Add loading effect
    document.addEventListener('click', function(e) {
        const paginateLink = e.target.closest('#hot-industries-table .pagination a');
        if (paginateLink && !paginateLink.closest('.disabled')) {
            const tableContainer = document.getElementById('hot-industries-table');
            tableContainer.style.opacity = '0.7';
            tableContainer.style.pointerEvents = 'none';
        }
    });

    // Auto scroll to section if hash exists in URL
    document.addEventListener('DOMContentLoaded', function() {
        // Check for hash in URL and scroll to section
        if (window.location.hash === '#hot-industries-section') {
            setTimeout(function() {
                document.getElementById('hot-industries-section').scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }, 100);
        }
    });
});

let aiPredictClicked = false;

function _escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

document.getElementById('aiPredictBtn').onclick = function() {
    if (aiPredictClicked) return;
    aiPredictClicked = true;

    const btn = document.getElementById('aiPredictBtn');
    btn.disabled = true;

    document.getElementById('aiPredictResult').style.display = 'block';
    document.getElementById('aiPredictLoading').style.display = 'block';
    document.getElementById('aiPredictContent').innerHTML = '';

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 40000);

    fetch('/ai-predict', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({}),
        signal: controller.signal,
    })
    .then(res => {
        clearTimeout(timeoutId);
        if (!res.ok) throw new Error('http_' + res.status);
        return res.json();
    })
    .then(data => {
        document.getElementById('aiPredictLoading').style.display = 'none';
        document.getElementById('aiPredictContent').innerHTML = `<div style="font-size:1.1rem; color:var(--primary-blue); font-weight:500;">
            <i class="bi bi-stars" style="color:#fbbf24;"></i> ${_escapeHtml(data.result)}
        </div>`;
    })
    .catch(err => {
        clearTimeout(timeoutId);
        document.getElementById('aiPredictLoading').style.display = 'none';
        const errMsg = err.name === 'AbortError'
            ? 'Yêu cầu quá thời gian, vui lòng thử lại!'
            : 'Lỗi lấy dự đoán AI, vui lòng thử lại!';
        document.getElementById('aiPredictContent').innerHTML = `<div style="color:var(--danger-red); font-weight:500;">
            <i class="bi bi-exclamation-triangle"></i> ${_escapeHtml(errMsg)}
        </div>`;
        aiPredictClicked = false;
        btn.disabled = false;
    });
};

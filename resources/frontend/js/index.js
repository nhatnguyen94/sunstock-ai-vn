document.addEventListener('DOMContentLoaded', function() {
    // Initialize Awesomplete
    const symbolInput = document.getElementById('symbol');
    const awesomplete = new Awesomplete(symbolInput, {
        minChars: 1,
        maxItems: 15,
        autoFirst: true,
        list: [],
        replace: function(suggestion) {
            this.input.value = suggestion.value;
        }
    });

    // Auto submit form when an item is selected from dropdown
    symbolInput.addEventListener('awesomplete-selectcomplete', function(e) {
        const searchForm = document.querySelector('.search-form-wrapper');
        const searchBtn = searchForm.querySelector('.search-btn');
        const btnText = searchBtn.querySelector('.btn-text');
        const btnIcon = searchBtn.querySelector('i');
        
        searchBtn.disabled = true;
        btnIcon.className = 'loading';
        btnText.textContent = 'Đang tìm...';
        
        searchForm.submit();
    });

    let searchTimeout;

    // Search suggestions with debounce
    symbolInput.addEventListener('input', function(e) {
        // Don't trigger search if the input was updated by selection
        if (e.isTrusted === false) return;
        
        const val = this.value.trim();
        const notFoundMsg = document.getElementById('notFoundMsg');
        
        if (val.length < 1) {
            notFoundMsg.classList.remove('show');
            return;
        }
        
        // Clear previous timeout
        clearTimeout(searchTimeout);
        
        // Set new timeout
        searchTimeout = setTimeout(() => {
            fetch('/stocks-list?q=' + encodeURIComponent(val))
                .then(res => res.json())
                .then(data => {
                    if (data.length === 0) {
                        notFoundMsg.classList.add('show');
                    } else {
                        notFoundMsg.classList.remove('show');
                    }
                    
                    const list = data.map(item => ({
                        label: `<b>${item.symbol}</b><span>${item.name}</span>`,
                        value: item.symbol
                    }));
                    
                    awesomplete.list = list;
                })
                .catch(err => {
                    console.error('Lỗi lấy danh sách mã:', err);
                    notFoundMsg.classList.add('show');
                });
        }, 300); // 300ms debounce
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
document.getElementById('aiPredictBtn').onclick = function() {
    if (aiPredictClicked && !window._isAuth) return; // Chỉ cho nhấn 1 lần nếu chưa đăng nhập
    aiPredictClicked = true;

    document.getElementById('aiPredictResult').style.display = 'block';
    document.getElementById('aiPredictLoading').style.display = 'block';
    document.getElementById('aiPredictContent').innerHTML = '';

    fetch('/ai-predict', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({})
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById('aiPredictLoading').style.display = 'none';
        document.getElementById('aiPredictContent').innerHTML = `<div style="font-size:1.1rem; color:var(--primary-blue); font-weight:500;">
            <i class="bi bi-stars" style="color:#fbbf24;"></i> ${data.result}
        </div>`;
    })
    .catch(() => {
        document.getElementById('aiPredictLoading').style.display = 'none';
        document.getElementById('aiPredictContent').innerHTML = `<div style="color:var(--danger-red); font-weight:500;">
            <i class="bi bi-exclamation-triangle"></i> Lỗi lấy dự đoán AI!
        </div>`;
    });
};

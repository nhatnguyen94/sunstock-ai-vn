@extends('layouts.app')

@php
    function parseRate($value) {
        $value = trim($value);
        if ($value === '-' || $value === '' || $value === null) return null;
        $value = str_replace(',', '', $value);
        return is_numeric($value) ? (float)$value : null;
    }
@endphp

@section('head')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/awesomplete/1.1.5/awesomplete.min.css" />
<style>
    /* Hero Search Section */
    .hero-search {
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        padding: 4rem 0 6rem;
        position: relative;
        overflow: hidden;
    }

    .hero-search::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><polygon fill="rgba(255,255,255,0.05)" points="0,1000 1000,0 1000,1000"/></svg>');
        pointer-events: none;
    }

    .hero-content {
        position: relative;
        z-index: 2;
        text-align: center;
        color: white;
    }

    .hero-title {
        font-size: 3.2rem;
        font-weight: 700;
        margin-bottom: 1rem;
        text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .hero-subtitle {
        font-size: 1.3rem;
        margin-bottom: 3rem;
        opacity: 0.9;
    }

    .search-card {
        background: white;
        border-radius: 20px;
        padding: 2.5rem;
        box-shadow: var(--shadow-xl);
        margin: -3rem auto 0;
        max-width: 800px;
        position: relative;
        z-index: 3;
        border: 1px solid var(--border-color);
    }

    .search-form-wrapper {
        position: relative;
        margin-bottom: 2rem;
    }

    .search-input-group {
        display: flex;
        gap: 15px;
        align-items: stretch;
    }

    .search-input-container {
        flex: 1;
        position: relative;
        display: flex;
        align-items: center;
    }

    /* Fix Awesomplete wrapper styling */
    .search-input-container .awesomplete {
        flex: 1;
        position: relative;
        display: flex;
        align-items: center;
    }

    .search-input {
        width: 100%;
        padding: 1.25rem 1.5rem 1.25rem 3.5rem;
        border: 2px solid var(--border-color);
        border-radius: 15px;
        font-size: 1.1rem;
        font-weight: 500;
        transition: all 0.3s ease;
        background: white;
        outline: none;
        box-sizing: border-box;
    }

    .search-input:focus {
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .search-icon {
        position: absolute;
        left: 1.25rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-secondary);
        font-size: 1.3rem;
        z-index: 5;
        pointer-events: none;
    }

    .search-btn {
        padding: 1.25rem 2.5rem;
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        color: white;
        border: none;
        border-radius: 15px;
        font-weight: 600;
        font-size: 1.1rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        min-width: 160px;
        justify-content: center;
        white-space: nowrap;
    }

    .search-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
    }

    .search-btn:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        transform: none;
    }

    .popular-tags {
        text-align: center;
        margin-top: 1.5rem;
    }

    .popular-tags .tag {
        display: inline-block;
        background: var(--light-blue);
        color: var(--primary-blue);
        padding: 0.5rem 1rem;
        border-radius: 20px;
        text-decoration: none;
        margin: 0 0.5rem 0.5rem 0;
        font-weight: 500;
        border: 1px solid rgba(37, 99, 235, 0.2);
        transition: all 0.3s ease;
    }

    .popular-tags .tag:hover {
        background: var(--primary-blue);
        color: white;
        text-decoration: none;
        transform: translateY(-1px);
    }

    /* Error Message */
    .error-message {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        color: var(--danger-red);
        padding: 1rem 1.5rem;
        border-radius: 12px;
        margin-top: 1rem;
        font-weight: 500;
        display: none;
        align-items: center;
        gap: 0.5rem;
        border: 1px solid rgba(239, 68, 68, 0.3);
        animation: fadeInUp 0.3s ease;
    }

    .error-message.show {
        display: flex;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Awesomplete Customization - Enhanced */
    .awesomplete {
        position: relative;
        width: 100% !important;
    }

    .awesomplete > ul {
        border-radius: 12px;
        border: 1px solid var(--border-color);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        background: white;
        margin-top: 5px;
        max-height: 300px;
        overflow-y: auto;
        z-index: 1000;
    }

    .awesomplete li {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--border-color);
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .awesomplete li:last-child {
        border-bottom: none;
    }

    .awesomplete li:hover,
    .awesomplete li[aria-selected="true"] {
        background: linear-gradient(135deg, var(--light-blue), rgba(37, 99, 235, 0.1));
        color: var(--primary-blue);
    }

    .awesomplete li b { 
        color: var(--primary-blue); 
        font-weight: 700;
        font-size: 1.1rem;
    }
    
    .awesomplete li span { 
        font-size: 0.9rem; 
        color: var(--text-secondary);
        display: block;
        margin-top: 0.25rem;
    }

    /* Loading state */
    .loading {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        border-top-color: white;
        animation: spin 1s ease-in-out infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Featured Stocks Grid */
    .featured-section {
        margin: 4rem 0;
    }

    .section-title {
        text-align: center;
        font-size: 2.2rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 3rem;
        position: relative;
    }

    .section-title::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 50%;
        transform: translateX(-50%);
        width: 80px;
        height: 4px;
        background: linear-gradient(90deg, var(--primary-blue), var(--secondary-blue));
        border-radius: 2px;
    }

    .featured-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 2rem;
        margin-bottom: 3rem;
    }

    .stock-card {
        background: white;
        border-radius: 16px;
        padding: 2rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .stock-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, var(--success-green), var(--primary-blue));
    }

    .stock-card:hover {
        transform: translateY(-6px);
        box-shadow: var(--shadow-xl);
    }

    .stock-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }

    .stock-symbol {
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        color: white;
        padding: 0.75rem 1.25rem;
        border-radius: 10px;
        font-weight: 700;
        font-size: 1.1rem;
    }

    .stock-exchange {
        background: var(--light-blue);
        color: var(--primary-blue);
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: 500;
    }

    .stock-name {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 1rem;
        line-height: 1.4;
    }

    .stock-price {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--success-green);
        margin-bottom: 0.5rem;
    }

    .stock-change {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 1rem;
    }

    .stock-change.positive { color: var(--success-green); }
    .stock-change.negative { color: var(--danger-red); }

    .stock-industry {
        background: var(--bg-light);
        padding: 0.75rem;
        border-radius: 8px;
        font-size: 0.9rem;
        color: var(--text-secondary);
        margin-bottom: 1.5rem;
        text-align: center;
    }

    .view-detail-btn {
        width: 100%;
        padding: 0.875rem;
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        text-decoration: none;
        text-align: center;
        transition: all 0.3s ease;
        display: block;
    }

    .view-detail-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
        color: white;
        text-decoration: none;
    }

    /* Info Sections */
    .info-section {
        background: white;
        border-radius: 16px;
        padding: 2.5rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--border-color);
        margin-bottom: 3rem;
    }

    .info-section h3 {
        color: var(--primary-blue);
        font-weight: 700;
        font-size: 1.5rem;
        margin-bottom: 2rem;
        text-align: center;
        position: relative;
    }

    .info-section h3::after {
        content: '';
        position: absolute;
        bottom: -8px;
        left: 50%;
        transform: translateX(-50%);
        width: 50px;
        height: 3px;
        background: var(--primary-blue);
        border-radius: 2px;
    }

    /* Exchange Rate Table */
    .exchange-table {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        margin-bottom: 2rem;
    }

    .exchange-table table {
        margin: 0;
        width: 100%;
    }

    .exchange-table thead th {
        background: linear-gradient(135deg, var(--light-blue), #f1f5f9);
        border: none;
        font-weight: 600;
        color: var(--text-primary);
        padding: 1rem;
        text-align: center;
        font-size: 0.95rem;
    }

    .exchange-table tbody td {
        border: none;
        padding: 0.875rem 1rem;
        text-align: center;
        vertical-align: middle;
    }

    .exchange-table tbody tr:hover {
        background: var(--bg-light);
    }

    .currency-code {
        background: var(--primary-blue);
        color: white;
        padding: 0.375rem 0.75rem;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.9rem;
    }

    /* Hot Industries Table */
    .hot-table {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }

    .hot-table table {
        margin: 0;
        width: 100%;
    }

    .hot-table thead th {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        border: none;
        font-weight: 600;
        color: var(--danger-red);
        padding: 1rem;
        text-align: center;
    }

    .hot-table tbody td {
        border: none;
        padding: 0.875rem 1rem;
        text-align: center;
        vertical-align: middle;
    }

    .hot-table tbody tr:hover {
        background: var(--bg-light);
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .hero-title {
            font-size: 2.2rem;
        }

        .hero-subtitle {
            font-size: 1.1rem;
        }

        .search-card {
            margin: -2rem 1rem 0;
            padding: 2rem 1.5rem;
        }

        .search-input-group {
            flex-direction: column;
            gap: 12px;
        }

        .search-btn {
            min-width: auto;
            justify-content: center;
        }

        .featured-grid {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }

        .info-section {
            padding: 2rem 1.5rem;
        }

        .section-title {
            font-size: 1.8rem;
        }

        .search-input {
            padding: 1rem 1rem 1rem 3rem;
            font-size: 1rem;
        }

        .search-icon {
            left: 1rem;
            font-size: 1.2rem;
        }
    }
</style>
@endsection

@section('content')
<!-- Hero Search Section -->
<section class="hero-search">
    <div class="container">
        <div class="hero-content">
            <h1 class="hero-title">
                <i class="bi bi-graph-up-arrow" style="color: #fbbf24;"></i>
                Sun Stock AI
            </h1>
            <p class="hero-subtitle">
                Tra cứu thông tin cổ phiếu Việt Nam thông minh với AI
            </p>
        </div>
    </div>
</section>

<!-- Search Card -->
<div class="container">
    <div class="search-card">
        <form method="GET" action="{{ url('/stock') }}" class="search-form-wrapper" autocomplete="off">
            <div class="search-input-group">
                <div class="search-input-container">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" id="symbol" name="symbol" class="search-input" 
                           placeholder="Nhập mã cổ phiếu: FPT, VNM, VCB, VNINDEX..." required autocomplete="off">
                </div>
                <button type="submit" class="search-btn">
                    <i class="bi bi-search"></i>
                    <span class="btn-text">Tra cứu</span>
                </button>
            </div>
        </form>
        
        <div id="notFoundMsg" class="error-message">
            <i class="bi bi-exclamation-triangle"></i>
            Không tìm thấy mã cổ phiếu phù hợp!
        </div>
        
        <div class="popular-tags">
            <span style="color: var(--text-secondary); font-weight: 500; margin-right: 1rem;">Mã phổ biến:</span>
            @foreach($featured as $stock)
                <a href="{{ url('/stock?symbol='.$stock['symbol']) }}" class="tag" title="{{ $stock['name'] }}">
                    {{ $stock['symbol'] }}
                </a>
            @endforeach
        </div>
    </div>
</div>

<!-- Featured Stocks Section -->
<div class="container">
    <section class="featured-section">
        <h2 class="section-title">
            <i class="bi bi-star-fill" style="color: var(--warning-orange); margin-right: 10px;"></i>
            Cổ phiếu nổi bật
        </h2>
        
        <div class="featured-grid">
            @foreach($featured as $stock)
            <div class="stock-card">
                <div class="stock-header">
                    <span class="stock-symbol">{{ $stock['symbol'] }}</span>
                    <span class="stock-exchange">{{ $stock['exchange'] }}</span>
                </div>
                
                <h3 class="stock-name">{{ $stock['name'] }}</h3>
                
                <div class="stock-price">
                    {{ $stock['price'] ? number_format($stock['price']) . ' VNĐ' : 'N/A' }}
                </div>
                
                @if($stock['change'] !== null)
                <div class="stock-change {{ $stock['change'] >= 0 ? 'positive' : 'negative' }}">
                    <i class="bi {{ $stock['change'] >= 0 ? 'bi-arrow-up' : 'bi-arrow-down' }}"></i>
                    {{ $stock['change'] >= 0 ? '+' : '' }}{{ number_format($stock['change'], 2) }}%
                </div>
                @endif
                
                <div class="stock-industry">
                    <i class="bi bi-building"></i>
                    {{ $stock['industry'] }}
                </div>
                
                <a href="{{ url('/stock?symbol='.$stock['symbol']) }}" class="view-detail-btn">
                    <i class="bi bi-eye"></i>
                    Xem chi tiết
                </a>
            </div>
            @endforeach
        </div>
    </section>

    <!-- Exchange Rate Section -->
    @if(count($exchangeRates) > 0)
    <section class="info-section">
        <h3>
            <i class="bi bi-currency-exchange" style="color: var(--success-green); margin-right: 10px;"></i>
            Tỷ giá ngoại tệ Vietcombank
        </h3>
        
        @foreach($exchangeRates as $date => $items)
        <div class="mb-4">
            <h5 style="color: var(--primary-blue); font-weight: 600; margin-bottom: 1rem;">
                <i class="bi bi-calendar-date"></i>
                {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}
                @if(\Carbon\Carbon::parse($date)->isToday())
                    <span class="badge badge-primary ml-2">Hôm nay</span>
                @endif
            </h5>
            
            <div class="exchange-table">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Ngoại tệ</th>
                            <th>Tên</th>
                            <th>Mua tiền mặt</th>
                            <th>Mua chuyển khoản</th>
                            <th>Bán</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                        <tr>
                            <td>
                                <span class="currency-code">{{ $item['currency_code'] }}</span>
                            </td>
                            <td style="font-weight: 500;">{{ $item['currency_name'] }}</td>
                            <td style="color: var(--success-green); font-weight: 600;">
                                {{ parseRate($item['buy_cash'] ?? $item['buy _cash'] ?? null) !== null 
                                    ? number_format(parseRate($item['buy_cash'] ?? $item['buy _cash'] ?? null), 2) 
                                    : '-' }}
                            </td>
                            <td style="color: var(--primary-blue); font-weight: 600;">
                                {{ parseRate($item['buy_transfer'] ?? $item['buy _transfer'] ?? null) !== null 
                                    ? number_format(parseRate($item['buy_transfer'] ?? $item['buy _transfer'] ?? null), 2) 
                                    : '-' }}
                            </td>
                            <td style="color: var(--danger-red); font-weight: 600;">
                                {{ parseRate($item['sell'] ?? null) !== null 
                                    ? number_format(parseRate($item['sell'] ?? null), 2) 
                                    : '-' }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endforeach
    </section>
    @endif

    <!-- Hot Industries Section -->
    @if(count($hotIndustries) > 0)
    <section class="info-section">
        <h3>
            <i class="bi bi-fire" style="color: var(--danger-red); margin-right: 10px;"></i>
            Top {{ count($hotIndustries) }} công ty nổi bật theo ngành
        </h3>
        
        <div class="hot-table">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Mã CK</th>
                        <th>Tên công ty</th>
                        <th>Ngành</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($hotIndustries as $item)
                    <tr>
                        <td>
                            <span class="currency-code" style="background: var(--danger-red);">{{ $item['symbol'] }}</span>
                        </td>
                        <td style="font-weight: 500; text-align: left;">{{ $item['organ_name'] }}</td>
                        <td style="color: var(--text-secondary);">{{ $item['icb_name3'] }}</td>
                        <td>
                            <a href="{{ url('/stock?symbol='.$item['symbol']) }}" class="btn btn-primary-custom btn-sm">
                                <i class="bi bi-eye"></i>
                                Xem
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    @endif
</div>
@endsection

@section('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/awesomplete/1.1.5/awesomplete.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Awesomplete
    const symbolInput = document.getElementById('symbol');
    const awesomplete = new Awesomplete(symbolInput, {
        minChars: 1,
        maxItems: 15,
        autoFirst: true,
        list: []
    });

    let searchTimeout;

    // Search suggestions with debounce
    symbolInput.addEventListener('input', function() {
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
});
</script>
@endsection
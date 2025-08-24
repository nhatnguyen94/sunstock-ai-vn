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

    /* News Section Styles */
    .news-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 2rem;
        margin-top: 2rem;
    }

    .news-card {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        position: relative;
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .news-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-xl);
    }

    .news-image {
        width: 100%;
        height: 200px;
        overflow: hidden;
        position: relative;
        background: var(--bg-light);
    }

    .news-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .news-card:hover .news-image img {
        transform: scale(1.05);
    }

    .news-date-badge {
        position: absolute;
        top: 12px;
        right: 12px;
        background: rgba(37, 99, 235, 0.9);
        color: white;
        padding: 0.5rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
        backdrop-filter: blur(10px);
    }

    .news-content {
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        flex-grow: 1;
    }

    .news-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-primary);
        line-height: 1.4;
        margin-bottom: 0.75rem;
        text-decoration: none;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        transition: color 0.3s ease;
    }

    .news-title:hover {
        color: var(--primary-blue);
        text-decoration: none;
    }

    .news-description {
        color: var(--text-secondary);
        font-size: 0.9rem;
        line-height: 1.5;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        flex-grow: 1;
        margin-bottom: 1rem;
    }

    .news-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: auto;
        padding-top: 1rem;
        border-top: 1px solid var(--border-color);
    }

    .news-date {
        color: var(--text-secondary);
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .news-read-more {
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 500;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .news-read-more:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        color: white;
        text-decoration: none;
    }

    /* Featured News (First 2 items) */
    .news-grid .news-card:nth-child(-n+2) {
        background: linear-gradient(135deg, #fefefe, #f8fafc);
        border: 2px solid var(--primary-blue);
        position: relative;
    }

    .news-grid .news-card:nth-child(-n+2)::before {
        content: '🔥 Nổi bật';
        position: absolute;
        top: 0;
        left: 0;
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 0 0 12px 0;
        font-size: 0.75rem;
        font-weight: 600;
        z-index: 2;
    }

    /* Hot Industries Pagination */
.hot-industries-pagination {
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
}

.pagination-info {
    color: var(--text-secondary);
    font-size: 0.9rem;
    text-align: center;
}

.pagination-links .pagination {
    margin: 0;
    display: flex;
    justify-content: center;
    gap: 0.5rem;
}

.pagination-links .page-link {
    color: var(--primary-blue);
    background: white;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 0.5rem 0.75rem;
    font-weight: 500;
    transition: all 0.3s ease;
    text-decoration: none;
}

.pagination-links .page-link:hover {
    background: var(--light-blue);
    border-color: var(--primary-blue);
    color: var(--primary-blue);
    transform: translateY(-1px);
}

.pagination-links .page-item.active .page-link {
    background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
    border-color: var(--primary-blue);
    color: white;
    font-weight: 600;
}

.pagination-links .page-item.disabled .page-link {
    color: #6c757d;
    background-color: #f8f9fa;
    border-color: var(--border-color);
    cursor: not-allowed;
}

/* Responsive pagination */
@media (max-width: 768px) {
    .hot-industries-pagination {
        gap: 1.5rem;
    }
    
    .pagination-links .pagination {
        flex-wrap: wrap;
        gap: 0.25rem;
    }
    
    .pagination-links .page-link {
        padding: 0.375rem 0.5rem;
        font-size: 0.85rem;
    }
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

        /* Responsive Design for News */
        .news-grid {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }

        .news-image {
            height: 180px;
        }

        .news-content {
            padding: 1.25rem;
        }

        .news-meta {
            flex-direction: column;
            gap: 1rem;
            align-items: stretch;
        }

        .news-read-more {
            text-align: center;
            justify-content: center;
        }
    }

    @media (min-width: 1200px) {
        .news-grid {
            grid-template-columns: repeat(2, 1fr);
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

<div class="container">
    <!-- 1. FEATURED STOCKS - Giữ nguyên vị trí đầu -->
    <section class="featured-section">
        <h2 class="section-title">
            <i class="bi bi-star-fill" style="color: var(--warning-orange); margin-right: 10px;"></i>
            Cổ phiếu nổi bật
            <span style="display:block; font-size:0.95rem; color:#6b7280; font-weight:400; margin-top:0.5rem;">
                Giá hiển thị theo đơn vị <b>K = 1.000 VNĐ</b>
            </span>
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
                    @if($stock['price'])
                        {{ number_format($stock['price'], 0, ',', '.') }}K
                    @else
                        N/A
                    @endif
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

    <!-- AI Prediction Button - New Section (Moved up) -->
    <div class="container text-center my-4">
        <button id="aiPredictBtn" class="btn btn-primary-custom" style="font-size:1.1rem; padding:1rem 2.5rem; border-radius:20px; box-shadow:var(--shadow-lg); display:inline-flex; align-items:center; gap:10px;">
            <i class="bi bi-robot" style="font-size:1.5rem;"></i>
            AI Dự đoán thị trường tuần này
        </button>
        <div id="aiPredictResult" style="margin-top:2rem; display:none;">
            <div class="custom-card" style="padding:2rem;">
                <div id="aiPredictLoading" style="display:none;">
                    <span class="loading"></span> Đang lấy dự đoán từ AI...
                </div>
                <div id="aiPredictContent"></div>
            </div>
        </div>
    </div>

    <!-- 2. EXCHANGE RATES - Di chuyển lên vị trí thứ 2 -->
    @if(count($exchangeRates) > 0)
    <section class="info-section">
        <!-- Tỷ giá ngoại tệ hôm nay -->
        <h3>
            <i class="bi bi-currency-exchange" style="color: var(--success-green); margin-right: 10px;"></i>
            Tỷ giá ngoại tệ hôm nay
            <span style="font-size: 0.7em; color: var(--text-secondary); font-weight: 400;">
                (Vietcombank)
            </span>
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

    <!-- 3. HOT INDUSTRIES - Di chuyển lên vị trí thứ 3 -->
    @if($hotIndustries->count() > 0)
    <section class="info-section" id="hot-industries-section">
        <h3>
            <i class="bi bi-fire" style="color: var(--danger-red); margin-right: 10px;"></i>
            Ngành nghề đang hot
            <span style="font-size: 0.7em; color: var(--text-secondary); font-weight: 400;">
                ({{ $hotIndustries->total() }} công ty nổi bật, 10/c trang)
            </span>
        </h3>
        <div class="hot-table" id="hot-industries-table">
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
            
            <!-- Improved Pagination -->
            @if($hotIndustries->hasPages())
            <div class="hot-industries-pagination">
                <div class="pagination-info">
                    <span>
                        Hiển thị {{ $hotIndustries->firstItem() }}-{{ $hotIndustries->lastItem() }} 
                        trong tổng số {{ $hotIndustries->total() }} công ty
                    </span>
                </div>
                <div class="pagination-links">
                    {{ $hotIndustries->appends(['#' => 'hot-industries-section'])->links('pagination::bootstrap-4') }}
                </div>
            </div>
            @endif
        </div>
    </section>
    @endif

    <!-- 4. NEWS - Di chuyển xuống cuối, bổ sung thông tin -->
    @if(isset($news) && count($news) > 0)
    <section class="info-section">
        <!-- Tin tức thị trường mới nhất -->
        <h3>
            <i class="bi bi-newspaper" style="color: var(--primary-blue); margin-right: 10px;"></i>
            Tin tức thị trường mới nhất
            <span style="font-size: 0.7em; color: var(--text-secondary); font-weight: 400;">
                (Cập nhật từ VnExpress)
            </span>
        </h3>
        
        <div class="news-grid">
            @foreach($news as $item)
            <article class="news-card">
                @if($item['image'])
                <div class="news-image">
                    <img src="{{ $item['image'] }}" alt="{{ $item['title'] }}" loading="lazy">
                    <div class="news-date-badge">
                        <i class="bi bi-clock"></i>
                        {{ $item['pubDate'] }}
                    </div>
                </div>
                @endif
                
                <div class="news-content">
                    <a href="{{ $item['link'] }}" target="_blank" class="news-title">
                        {{ $item['title'] }}
                    </a>
                    
                    <p class="news-description">
                        {{ strip_tags($item['description']) }}
                    </p>
                    
                    <div class="news-meta">
                        <div class="news-date">
                            <i class="bi bi-calendar3"></i>
                            {{ $item['pubDate'] }}
                        </div>
                        
                        <a href="{{ $item['link'] }}" target="_blank" class="news-read-more">
                            Đọc thêm
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </article>
            @endforeach
        </div>
        
        <div style="text-align: center; margin-top: 2rem;">
            <a href="https://vnexpress.net/kinh-doanh" target="_blank" 
               style="display: inline-flex; align-items: center; gap: 0.5rem; 
                      background: var(--light-blue); color: var(--primary-blue); 
                      padding: 0.75rem 1.5rem; border-radius: 25px; text-decoration: none; 
                      font-weight: 500; transition: all 0.3s ease;">
                <i class="bi bi-newspaper"></i>
                Xem tất cả tin tức
                <i class="bi bi-arrow-right"></i>
            </a>
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
</script>
@section('scripts')
@parent
<script>
let aiPredictClicked = false;
document.getElementById('aiPredictBtn').onclick = function() {
    if (aiPredictClicked && !@json(Auth::check())) return; // Chỉ cho nhấn 1 lần nếu chưa đăng nhập
    aiPredictClicked = true;

    document.getElementById('aiPredictResult').style.display = 'block';
    document.getElementById('aiPredictLoading').style.display = 'block';
    document.getElementById('aiPredictContent').innerHTML = '';

    fetch('/ai-predict', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
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
</script>
@endsection
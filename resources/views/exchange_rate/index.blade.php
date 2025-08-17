@extends('layouts.app')

@section('head')
<style>
    /* Exchange Rate Page Styles - Matching main theme */
    .exchange-header {
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        color: white;
        padding: 3rem 0;
        margin-bottom: 0;
        position: relative;
        overflow: hidden;
    }

    .exchange-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
        opacity: 0.3;
    }

    .exchange-title {
        font-size: 2.8rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        position: relative;
        z-index: 2;
    }

    .exchange-subtitle {
        font-size: 1.3rem;
        opacity: 0.9;
        margin-bottom: 0;
        position: relative;
        z-index: 2;
    }

    .back-button {
        background: rgba(255,255,255,0.2);
        color: white;
        border: 1px solid rgba(255,255,255,0.3);
        padding: 0.75rem 1.5rem;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        position: relative;
        z-index: 2;
    }

    .back-button:hover {
        background: rgba(255,255,255,0.3);
        color: white;
        text-decoration: none;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }

    /* Main Content Area */
    .main-content {
        background: #f8fafc;
        min-height: calc(100vh - 200px);
        padding: 3rem 0;
    }

    /* Search Section */
    .search-section {
        background: white;
        border-radius: 20px;
        padding: 2.5rem;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        border: 1px solid #e5e7eb;
        margin-bottom: 3rem;
        position: relative;
    }

    .search-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        border-radius: 20px 20px 0 0;
    }

    .search-title {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--primary-blue);
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .search-form {
        display: flex;
        gap: 1.5rem;
        align-items: flex-start;
        flex-wrap: wrap;
    }

    .search-group {
        flex: 1;
        min-width: 280px;
        position: relative;
    }

    .search-label {
        display: block;
        font-weight: 600;
        color: #374151;
        margin-bottom: 0.75rem;
        font-size: 1rem;
    }

    /* Enhanced Search Input for Date */
    .search-input[type="date"] {
        width: 100%;
        padding: 1rem 3.5rem 1rem 1.25rem;
        border: 2px solid #e5e7eb;
        border-radius: 15px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: #f8fafc;
        color: #374151;
        font-family: inherit;
        position: relative;
        
        /* Custom calendar icon */
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23374151' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1H2a1 1 0 0 0-1 1zM5 2v2M11 2v2M1 7h14'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 3.5rem center;
        background-size: 18px;
    }

    .search-input[type="date"]::-webkit-calendar-picker-indicator {
        opacity: 0;
        position: absolute;
        right: 3.5rem;
        width: 20px;
        height: 20px;
        cursor: pointer;
    }

    .search-input[type="date"]:focus {
        outline: none;
        border-color: var(--primary-blue);
        background: white;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        transform: translateY(-2px);
    }

    /* Date input when it has value */
    .search-input[type="date"]:valid {
        color: #1f2937;
        font-weight: 600;
    }

    .search-input[type="date"].has-value {
        border-color: #10b981;
        background: #f0fdf4;
        color: #1f2937;
        font-weight: 600;
    }

    /* Clear Date Button - Fixed positioning */
    .clear-date-btn {
        position: absolute;
        right: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        background: #ef4444;
        color: white;
        border: none;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        font-size: 0.7rem;
        cursor: pointer;
        display: none;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        z-index: 15;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .clear-date-btn:hover {
        background: #dc2626;
        transform: translateY(-50%) scale(1.1);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    .clear-date-btn.show {
        display: flex;
    }

    /* Date input container for proper positioning */
    .date-input-container {
        position: relative;
        display: flex;
        flex-direction: column;
    }

    /* Date Display Enhancement */
    .date-display {
        display: none;
        margin-top: 0.75rem;
        padding: 0.875rem 1.25rem;
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(34, 197, 94, 0.1));
        border: 1px solid rgba(16, 185, 129, 0.2);
        border-radius: 12px;
        color: #10b981;
        font-weight: 600;
        font-size: 0.9rem;
        animation: fadeInUp 0.3s ease;
        position: relative;
    }

    .date-display.show {
        display: block;
    }

    .date-display i {
        margin-right: 0.5rem;
        color: #059669;
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

    /* Button Container for proper alignment */
    .button-container {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        align-self: flex-start;
        margin-top: 2rem;
    }

    /* Search Button Styling */
    .search-btn {
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        color: white;
        border: none;
        padding: 1rem 2.5rem;
        border-radius: 15px;
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        min-width: 160px;
        justify-content: center;
        box-shadow: 0 4px 15px rgba(37, 99, 235, 0.2);
        text-decoration: none;
    }

    .search-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
        color: white;
        text-decoration: none;
    }

    .search-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    /* Clear Button Styling - Fixed */
    .clear-btn {
        background: linear-gradient(135deg, #6b7280, #4b5563);
        color: white;
        border: none;
        padding: 1rem 2rem;
        border-radius: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        justify-content: center;
        min-width: 160px;
        box-shadow: 0 4px 15px rgba(107, 114, 128, 0.2);
    }

    .clear-btn:hover {
        background: linear-gradient(135deg, #4b5563, #374151);
        transform: translateY(-2px);
        color: white;
        text-decoration: none;
        box-shadow: 0 6px 20px rgba(107, 114, 128, 0.3);
    }

    /* Help Text Styling */
    .date-help {
        color: #6b7280;
        font-size: 0.85rem;
        margin-top: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-style: italic;
    }

    /* Statistics Section */
    .stats-section {
        margin-bottom: 3rem;
    }

    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 2rem;
    }

    .stat-card {
        background: white;
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        border: 1px solid #e5e7eb;
        text-align: center;
        transition: all 0.4s ease;
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    }

    .stat-icon {
        font-size: 3rem;
        color: var(--primary-blue);
        margin-bottom: 1rem;
        display: block;
    }

    .stat-number {
        font-size: 2.2rem;
        font-weight: 800;
        color: #1f2937;
        margin-bottom: 0.5rem;
        display: block;
    }

    .stat-label {
        color: #6b7280;
        font-weight: 600;
        font-size: 1rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    /* Results Section */
    .results-section {
        margin-bottom: 3rem;
    }

    .section-title {
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary-blue);
        margin-bottom: 2rem;
        text-align: center;
        position: relative;
    }

    .section-title::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 50%;
        transform: translateX(-50%);
        width: 60px;
        height: 4px;
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        border-radius: 2px;
    }

    /* Date Header */
    .date-header {
        background: white;
        border-radius: 20px 20px 0 0;
        padding: 1.5rem 2rem;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        border: 1px solid #e5e7eb;
        border-bottom: none;
        position: relative;
    }

    .date-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        border-radius: 20px 20px 0 0;
    }

    .date-title {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--primary-blue);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .date-badge {
        background: linear-gradient(135deg, rgba(37, 99, 235, 0.1), rgba(59, 130, 246, 0.1));
        color: var(--primary-blue);
        padding: 0.5rem 1.25rem;
        border-radius: 25px;
        font-weight: 600;
        font-size: 0.9rem;
        border: 1px solid rgba(37, 99, 235, 0.2);
    }

    /* Exchange Table Container */
    .exchange-container {
        background: white;
        border-radius: 0 0 20px 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        border: 1px solid #e5e7eb;
        border-top: none;
        overflow: hidden;
        margin-bottom: 3rem;
        transition: all 0.3s ease;
    }

    .exchange-container:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
    }

    .exchange-table {
        margin: 0;
        width: 100%;
        border-collapse: collapse;
    }

    .exchange-table th {
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        border: none;
        font-weight: 700;
        color: var(--primary-blue);
        padding: 1.5rem 1rem;
        text-align: center;
        font-size: 1rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        position: relative;
    }

    .exchange-table th:not(:last-child)::after {
        content: '';
        position: absolute;
        right: 0;
        top: 25%;
        bottom: 25%;
        width: 1px;
        background: #e2e8f0;
    }

    .exchange-table td {
        border: none;
        padding: 1.25rem 1rem;
        text-align: center;
        vertical-align: middle;
        font-weight: 500;
        transition: all 0.3s ease;
        border-bottom: 1px solid #f1f5f9;
    }

    .exchange-table tbody tr {
        transition: all 0.3s ease;
    }

    .exchange-table tbody tr:hover {
        background: linear-gradient(135deg, rgba(37, 99, 235, 0.05), rgba(59, 130, 246, 0.05));
        transform: scale(1.01);
    }

    .exchange-table tbody tr:last-child td {
        border-bottom: none;
    }

    /* Currency Styling */
    .currency-code {
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 12px;
        font-weight: 700;
        font-size: 0.9rem;
        letter-spacing: 0.5px;
        display: inline-block;
        min-width: 60px;
        box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
    }

    .currency-name {
        color: #374151;
        font-weight: 600;
        font-size: 1rem;
    }

    .currency-unit {
        color: #6b7280;
        font-size: 0.9rem;
        font-style: italic;
        margin-top: 0.25rem;
    }

    /* Rate Values */
    .rate-value {
        font-family: 'SF Mono', 'Consolas', 'Monaco', monospace;
        font-weight: 700;
        font-size: 1rem;
        padding: 0.5rem 1rem;
        border-radius: 10px;
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
    }

    .rate-value:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .buy-cash {
        color: #dc2626;
        background: linear-gradient(135deg, rgba(220, 38, 38, 0.1), rgba(239, 68, 68, 0.1));
        border: 1px solid rgba(220, 38, 38, 0.2);
    }

    .buy-transfer {
        color: #ea580c;
        background: linear-gradient(135deg, rgba(234, 88, 12, 0.1), rgba(249, 115, 22, 0.1));
        border: 1px solid rgba(234, 88, 12, 0.2);
    }

    .sell-rate {
        color: #10b981;
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(34, 197, 94, 0.1));
        border: 1px solid rgba(16, 185, 129, 0.2);
    }

    /* No Data Message */
    .no-data {
        text-align: center;
        padding: 4rem 2rem;
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        border: 1px solid #e5e7eb;
    }

    .no-data-icon {
        font-size: 4rem;
        color: #d1d5db;
        margin-bottom: 1.5rem;
    }

    .no-data-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #6b7280;
        margin-bottom: 0.75rem;
    }

    .no-data-text {
        color: #9ca3af;
        font-size: 1.1rem;
    }

    /* Floating Action Button */
    .fab-container {
        position: fixed;
        bottom: 30px;
        right: 30px;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .fab {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        border: none;
        color: white;
        font-size: 1.5rem;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .fab-primary {
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
    }

    .fab-secondary {
        background: linear-gradient(135deg, #10b981, #059669);
    }

    .fab:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 25px rgba(0, 0, 0, 0.25);
    }

    .fab-primary:hover {
        box-shadow: 0 6px 25px rgba(37, 99, 235, 0.4);
    }

    /* Loading States */
    .loading-spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-top: 2px solid white;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .exchange-title {
            font-size: 2.2rem;
        }

        .search-form {
            flex-direction: column;
            align-items: stretch;
        }

        .search-group {
            min-width: auto;
            margin-bottom: 1rem;
        }

        .button-container {
            flex-direction: column;
            margin-top: 1rem;
        }

        .search-btn,
        .clear-btn {
            width: 100%;
            justify-content: center;
        }

        .stats-container {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }

        .exchange-table th,
        .exchange-table td {
            padding: 1rem 0.5rem;
            font-size: 0.9rem;
        }

        .currency-code {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
        }

        .rate-value {
            font-size: 0.9rem;
            padding: 0.4rem 0.8rem;
        }

        .main-content {
            padding: 2rem 0;
        }

        .search-section {
            padding: 2rem 1.5rem;
            margin-bottom: 2rem;
        }

        .fab-container {
            bottom: 20px;
            right: 20px;
        }

        .fab {
            width: 50px;
            height: 50px;
            font-size: 1.3rem;
        }
    }

    /* Animation Classes */
    .fade-in {
        animation: fadeIn 0.6s ease-out forwards;
    }

    .slide-up {
        animation: slideUp 0.6s ease-out forwards;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Print Styles */
    @media print {
        .fab-container,
        .back-button,
        .search-section {
            display: none !important;
        }

        .exchange-container {
            box-shadow: none;
            border: 1px solid #ccc;
            break-inside: avoid;
        }

        .main-content {
            background: white;
        }
    }
</style>
@endsection

@section('content')
<!-- Header Section -->
<section class="exchange-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="exchange-title fade-in">
                    <i class="bi bi-currency-exchange"></i>
                    Tỷ Giá Ngoại Tệ
                </h1>
                <p class="exchange-subtitle fade-in">Vietcombank - Cập nhật theo thời gian thực</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="{{ url('/') }}" class="back-button">
                    <i class="bi bi-arrow-left"></i>
                    Về trang chủ
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Main Content -->
<div class="main-content">
    <div class="container">
        <!-- Search Section -->
        <div class="search-section slide-up">
            <h3 class="search-title">
                <i class="bi bi-search"></i>
                Tìm kiếm tỷ giá theo ngày
            </h3>
            
            <form method="GET" action="{{ route('exchange-rate.search') }}" class="search-form">
                <div class="search-group">
                    <label class="search-label" for="search_date">
                        <i class="bi bi-calendar3"></i>
                        Chọn ngày tra cứu
                    </label>
                    
                    <div class="date-input-container">
                        <input 
                            type="date" 
                            name="date" 
                            id="search_date" 
                            class="search-input {{ isset($date) ? 'has-value' : '' }}" 
                            value="{{ $date ?? '' }}"
                            max="{{ date('Y-m-d') }}"
                            min="2020-01-01"
                            data-placeholder="dd/mm/yyyy"
                        >
                        
                        <button type="button" class="clear-date-btn {{ isset($date) ? 'show' : '' }}" id="clear-date" title="Xóa ngày đã chọn">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                    
                    <div class="date-display {{ isset($date) ? 'show' : '' }}" id="date-display">
                        @if(isset($date))
                            <i class="bi bi-check-circle"></i>
                            Ngày đã chọn: {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}
                            ({{ \Carbon\Carbon::parse($date)->diffForHumans() }})
                        @endif
                    </div>
                    
                    @if(!isset($date))
                        <div class="date-help" id="date-help">
                            <i class="bi bi-info-circle"></i>
                            Chọn ngày để tra cứu tỷ giá hối đoái
                        </div>
                    @endif
                </div>
                
                <div class="button-container">
                    <button type="submit" class="search-btn" id="search-button">
                        <i class="bi bi-search"></i>
                        {{ isset($date) ? 'Tìm lại' : 'Tìm kiếm' }}
                    </button>
                    
                    @if(isset($date))
                        <a href="{{ route('exchange-rate.index') }}" class="clear-btn">
                            <i class="bi bi-x-circle"></i>
                            Xóa bộ lọc
                        </a>
                    @else
                        <a href="{{ route('exchange-rate.index') }}" class="clear-btn">
                            <i class="bi bi-arrow-clockwise"></i>
                            Làm mới
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Statistics Section -->
        <div class="stats-section">
            <div class="stats-container">
                <div class="stat-card slide-up" style="animation-delay: 0.1s;">
                    <i class="stat-icon bi bi-calendar3"></i>
                    <span class="stat-number">{{ count($rates ?? []) }}</span>
                    <span class="stat-label">Ngày có dữ liệu</span>
                </div>
                
                <div class="stat-card slide-up" style="animation-delay: 0.2s;">
                    <i class="stat-icon bi bi-cash-coin"></i>
                    <span class="stat-number">{{ count($rates ?? []) > 0 ? count(reset($rates)) : (count($searchRates ?? [])) }}</span>
                    <span class="stat-label">Loại tiền tệ</span>
                </div>
                
                <div class="stat-card slide-up" style="animation-delay: 0.3s;">
                    <i class="stat-icon bi bi-bank"></i>
                    <span class="stat-number">VCB</span>
                    <span class="stat-label">Vietcombank</span>
                </div>
                
                <div class="stat-card slide-up" style="animation-delay: 0.4s;">
                    <i class="stat-icon bi bi-clock"></i>
                    <span class="stat-number" id="current-time">--:--:--</span>
                    <span class="stat-label">Thời gian hiện tại</span>
                </div>
            </div>
        </div>

        <!-- Results Section -->
        <div class="results-section">
            @if(isset($searchRates) && count($searchRates) > 0)
                <!-- Search Results -->
                <h2 class="section-title">Kết quả tìm kiếm</h2>
                
                <div class="date-header slide-up">
                    <h4 class="date-title">
                        <i class="bi bi-calendar-check"></i>
                        {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}
                        <span class="date-badge">
                            {{ \Carbon\Carbon::parse($date)->diffForHumans() }}
                        </span>
                    </h4>
                </div>

                <div class="exchange-container slide-up">
                    <div class="table-responsive">
                        <table class="exchange-table">
                            <thead>
                                <tr>
                                    <th><i class="bi bi-tag"></i> Mã</th>
                                    <th><i class="bi bi-globe"></i> Tên tiền tệ</th>
                                    <th><i class="bi bi-calculator"></i> Đơn vị</th>
                                    <th><i class="bi bi-cash-stack text-danger"></i> Mua tiền mặt</th>
                                    <th><i class="bi bi-credit-card text-warning"></i> Mua chuyển khoản</th>
                                    <th><i class="bi bi-arrow-up-circle text-success"></i> Bán ra</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($searchRates as $item)
                                <tr>
                                    <td>
                                        <span class="currency-code">{{ $item['currency_code'] }}</span>
                                    </td>
                                    <td>
                                        <div class="currency-name">{{ $item['currency_name'] }}</div>
                                    </td>
                                    <td>
                                        <div class="currency-unit">1 {{ $item['currency_code'] }} = VNĐ</div>
                                    </td>
                                    <td>
                                        <span class="rate-value buy-cash" title="Click để copy">
                                            {{ $item['buy _cash'] ?? $item['buy_cash'] ?? '-' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="rate-value buy-transfer" title="Click để copy">
                                            {{ $item['buy _transfer'] ?? $item['buy_transfer'] ?? '-' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="rate-value sell-rate" title="Click để copy">
                                            {{ $item['sell'] ?? '-' }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

            @elseif(isset($searchRates))
                <!-- No Search Results -->
                <div class="no-data slide-up">
                    <i class="no-data-icon bi bi-calendar-x"></i>
                    <h4 class="no-data-title">Không tìm thấy dữ liệu</h4>
                    <p class="no-data-text">Không có dữ liệu tỷ giá cho ngày {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</p>
                </div>
            @endif

            @if(isset($rates) && count($rates) > 0)
                <!-- Latest Rates -->
                @if(!isset($searchRates))
                    <h2 class="section-title">Tỷ giá 3 ngày gần nhất</h2>
                @else
                    <h2 class="section-title" style="margin-top: 4rem;">Tỷ giá 3 ngày gần nhất</h2>
                @endif

                @foreach($rates as $rateDate => $items)
                    <div class="date-header slide-up" style="animation-delay: {{ $loop->index * 0.1 }}s;">
                        <h4 class="date-title">
                            <i class="bi bi-calendar-check"></i>
                            {{ \Carbon\Carbon::parse($rateDate)->format('d/m/Y') }}
                            <span class="date-badge">
                                {{ \Carbon\Carbon::parse($rateDate)->diffForHumans() }}
                            </span>
                        </h4>
                    </div>

                    <div class="exchange-container slide-up" style="animation-delay: {{ $loop->index * 0.1 + 0.1 }}s;">
                        <div class="table-responsive">
                            <table class="exchange-table">
                                <thead>
                                    <tr>
                                        <th><i class="bi bi-tag"></i> Mã</th>
                                        <th><i class="bi bi-globe"></i> Tên tiền tệ</th>
                                        <th><i class="bi bi-calculator"></i> Đơn vị</th>
                                        <th><i class="bi bi-cash-stack text-danger"></i> Mua tiền mặt</th>
                                        <th><i class="bi bi-credit-card text-warning"></i> Mua chuyển khoản</th>
                                        <th><i class="bi bi-arrow-up-circle text-success"></i> Bán ra</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($items as $item)
                                    <tr>
                                        <td>
                                            <span class="currency-code">{{ $item['currency_code'] ?? '' }}</span>
                                        </td>
                                        <td>
                                            <div class="currency-name">{{ $item['currency_name'] ?? '' }}</div>
                                        </td>
                                        <td>
                                            <div class="currency-unit">1 {{ $item['currency_code'] ?? '' }} = VNĐ</div>
                                        </td>
                                        <td>
                                            <span class="rate-value buy-cash" title="Click để copy">
                                                {{ $item['buy _cash'] ?? $item['buy_cash'] ?? '-' }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="rate-value buy-transfer" title="Click để copy">
                                                {{ $item['buy _transfer'] ?? $item['buy_transfer'] ?? '-' }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="rate-value sell-rate" title="Click để copy">
                                                {{ $item['sell'] ?? '-' }}
                                            </span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            @endif

            @if((!isset($rates) || count($rates) == 0) && !isset($searchRates))
                <!-- No Data At All -->
                <div class="no-data slide-up">
                    <i class="no-data-icon bi bi-info-circle"></i>
                    <h4 class="no-data-title">Chưa có dữ liệu</h4>
                    <p class="no-data-text">Hiện tại chưa có thông tin tỷ giá. Vui lòng thử lại sau.</p>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Floating Action Buttons -->
<div class="fab-container">
    <button class="fab fab-secondary" onclick="window.print()" title="In trang">
        <i class="bi bi-printer"></i>
    </button>
    <button class="fab fab-primary" onclick="refreshPage()" title="Làm mới trang">
        <i class="bi bi-arrow-clockwise" id="refresh-icon"></i>
    </button>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Update current time
    function updateTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('vi-VN', { 
            hour: '2-digit', 
            minute: '2-digit',
            second: '2-digit'
        });
        const timeElement = document.getElementById('current-time');
        if (timeElement) {
            timeElement.textContent = timeString;
        }
    }

    // Initialize time and update every second
    updateTime();
    setInterval(updateTime, 1000);

    // Refresh page function
    window.refreshPage = function() {
        const refreshIcon = document.getElementById('refresh-icon');
        refreshIcon.style.animation = 'spin 1s linear infinite';
        
        setTimeout(() => {
            location.reload();
        }, 1000);
    };

    // Copy to clipboard functionality
    const rateValues = document.querySelectorAll('.rate-value');
    rateValues.forEach(rate => {
        if (rate.textContent.trim() !== '-' && rate.textContent.trim() !== '') {
            rate.addEventListener('click', function() {
                const text = this.textContent.trim();
                
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text).then(() => {
                        showCopyFeedback(this);
                    }).catch(err => {
                        console.error('Copy failed:', err);
                    });
                } else {
                    // Fallback for older browsers
                    const textArea = document.createElement('textarea');
                    textArea.value = text;
                    document.body.appendChild(textArea);
                    textArea.select();
                    try {
                        document.execCommand('copy');
                        showCopyFeedback(this);
                    } catch (err) {
                        console.error('Copy failed:', err);
                    }
                    document.body.removeChild(textArea);
                }
            });
        }
    });

    function showCopyFeedback(element) {
        const originalText = element.textContent;
        const originalBg = element.style.background;
        
        element.textContent = 'Đã sao chép!';
        element.style.background = 'linear-gradient(135deg, #10b981, #059669)';
        element.style.color = 'white';
        element.style.transform = 'scale(1.05)';
        
        setTimeout(() => {
            element.textContent = originalText;
            element.style.background = originalBg;
            element.style.color = '';
            element.style.transform = '';
        }, 1500);
    }

    // Enhanced date input functionality
    const dateInput = document.getElementById('search_date');
    const searchBtn = document.getElementById('search-button');
    const dateDisplay = document.getElementById('date-display');
    const clearDateBtn = document.getElementById('clear-date');
    const dateHelp = document.getElementById('date-help');
    
    if (dateInput) {
        // Function to update date display
        function updateDateDisplay(dateValue) {
            if (dateValue) {
                const date = new Date(dateValue);
                const formattedDate = date.toLocaleDateString('vi-VN');
                const diffText = getDateDiff(date);
                
                dateDisplay.innerHTML = `
                    <i class="bi bi-check-circle"></i>
                    Ngày đã chọn: ${formattedDate} (${diffText})
                `;
                dateDisplay.classList.add('show');
                clearDateBtn.classList.add('show');
                
                // Update input styling
                dateInput.classList.add('has-value');
                
                // Update button
                searchBtn.innerHTML = '<i class="bi bi-search"></i> Tìm kiếm';
                searchBtn.style.opacity = '1';
                
                // Hide help text
                if (dateHelp) {
                    dateHelp.style.display = 'none';
                }
            } else {
                dateDisplay.classList.remove('show');
                clearDateBtn.classList.remove('show');
                dateInput.classList.remove('has-value');
                
                // Reset input styling
                dateInput.style.borderColor = '#e5e7eb';
                dateInput.style.background = '#f8fafc';
                
                // Update button
                searchBtn.innerHTML = '<i class="bi bi-search"></i> Tìm kiếm';
                searchBtn.style.opacity = '0.7';
                
                // Show help text
                if (dateHelp) {
                    dateHelp.style.display = 'flex';
                }
            }
        }

        // Function to calculate date difference
        function getDateDiff(date) {
            const now = new Date();
            const diffTime = now - date;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            if (diffDays === 0) return 'hôm nay';
            if (diffDays === 1) return 'hôm qua';
            if (diffDays === -1) return 'ngày mai';
            if (diffDays > 0) return `${diffDays} ngày trước`;
            return `${Math.abs(diffDays)} ngày nữa`;
        }

        // Date input change event
        dateInput.addEventListener('change', function() {
            updateDateDisplay(this.value);
        });

        // Date input input event (for real-time updates)
        dateInput.addEventListener('input', function() {
            updateDateDisplay(this.value);
        });

        // Clear date button
        clearDateBtn.addEventListener('click', function() {
            dateInput.value = '';
            updateDateDisplay('');
            dateInput.focus();
        });

        // Initialize on page load
        if (dateInput.value) {
            updateDateDisplay(dateInput.value);
        }

        // Fix for browsers that don't show date value
        dateInput.addEventListener('focus', function() {
            this.style.borderColor = 'var(--primary-blue)';
            this.style.background = 'white';
        });
        
        dateInput.addEventListener('blur', function() {
            if (!this.value) {
                this.style.borderColor = '#e5e7eb';
                this.style.background = '#f8fafc';
            }
        });
    }

    // Enhanced form validation
    const searchForm = document.querySelector('.search-form');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            const dateValue = dateInput.value;
            if (!dateValue) {
                e.preventDefault();
                
                // Show error feedback
                dateInput.style.borderColor = '#ef4444';
                dateInput.style.background = '#fef2f2';
                
                // Add shake animation
                dateInput.style.animation = 'shake 0.5s';
                setTimeout(() => {
                    dateInput.style.animation = '';
                    dateInput.style.borderColor = '#e5e7eb';
                    dateInput.style.background = '#f8fafc';
                }, 2000);
                
                // Show error message
                const errorMsg = document.createElement('div');
                errorMsg.className = 'error-message';
                errorMsg.style.cssText = `
                    color: #ef4444;
                    font-size: 0.875rem;
                    margin-top: 0.5rem;
                    font-weight: 600;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                `;
                errorMsg.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Vui lòng chọn ngày trước khi tìm kiếm';
                
                const existingError = dateInput.parentNode.parentNode.querySelector('.error-message');
                if (existingError) {
                    existingError.remove();
                }
                
                dateInput.parentNode.parentNode.appendChild(errorMsg);
                
                setTimeout(() => {
                    if (errorMsg.parentNode) {
                        errorMsg.remove();
                    }
                }, 3000);
                
                dateInput.focus();
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('.search-btn');
            const originalContent = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="loading-spinner"></span> Đang tìm...';
            
            // Reset after 10 seconds if no response
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalContent;
            }, 10000);
        });
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + R for refresh
        if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
            e.preventDefault();
            refreshPage();
        }
        
        // Ctrl/Cmd + P for print
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            e.preventDefault();
            window.print();
        }
        
        // Escape to clear search
        if (e.key === 'Escape') {
            const searchInput = document.getElementById('search_date');
            if (searchInput && searchInput.value) {
                window.location.href = '{{ route("exchange-rate.index") }}';
            }
        }
    });
});

// Add shake animation to CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }
`;
document.head.appendChild(style);
</script>
@endsection
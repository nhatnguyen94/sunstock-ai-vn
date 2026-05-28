@extends('layouts.app')

@section('head')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/awesomplete/1.1.5/awesomplete.min.css" />
<style>
    /* Stock Page Specific Styles - FIXED */
    .stock-header {
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        color: white;
        padding: 2.5rem 0;
        margin-bottom: 3rem;
        position: relative;
        overflow: hidden;
    }

    .stock-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
        opacity: 0.3;
    }

    .stock-title-container {
        position: relative;
        z-index: 2;
    }

    .stock-title {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .stock-symbol-badge {
        background: rgba(255,255,255,0.2);
        border: 1px solid rgba(255,255,255,0.3);
        padding: 0.5rem 1rem;
        border-radius: 12px;
        font-weight: 700;
        font-size: 1.2rem;
    }

    .stock-name {
        font-size: 1.1rem;
        font-weight: 500;
        opacity: 0.9;
        margin-bottom: 0.5rem;
        font-style: italic;
    }

    .stock-subtitle {
        font-size: 1.2rem;
        opacity: 0.9;
        margin-bottom: 1.5rem;
    }

    .back-button {
        background: rgba(255,255,255,0.2);
        color: white;
        border: 1px solid rgba(255,255,255,0.3);
        padding: 0.75rem 1.5rem;
        border-radius: 10px;
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
        transform: translateY(-1px);
    }

    /* Search Section - Fixed */
    .search-section {
        background: white;
        border-radius: 16px;
        padding: 2rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        border: 1px solid #e5e7eb;
        margin-bottom: 3rem;
        position: relative;
    }

    .search-form {
        display: flex;
        gap: 15px;
        align-items: stretch;
        margin-bottom: 1.5rem;
    }

    .search-input-wrapper {
        flex: 1;
        position: relative;
        display: flex;
        align-items: center;
    }

    /* Fix Awesomplete wrapper styling */
    .search-input-wrapper .awesomplete {
        flex: 1;
        position: relative;
        display: flex;
        align-items: center;
        width: 100%;
    }

    .search-input {
        width: 100%;
        padding: 1rem 1.25rem 1rem 3rem;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        font-size: 1rem;
        font-weight: 500;
        transition: all 0.3s ease;
        background: white;
        color: #1f2937;
        box-sizing: border-box;
    }

    .search-input:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        outline: none;
    }

    .search-input::placeholder {
        color: #6b7280;
        opacity: 1;
    }

    .search-icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #6b7280;
        font-size: 1.1rem;
        z-index: 5;
        pointer-events: none;
    }

    .search-btn {
        padding: 1rem 2rem;
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: white;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        min-width: 120px;
        display: flex;
        align-items: center;
        gap: 8px;
        justify-content: center;
    }

    .search-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(37, 99, 235, 0.3);
    }

    .search-btn:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        transform: none;
    }

    /* Quick Suggestions */
    .quick-suggestions {
        text-align: center;
        margin-top: 1rem;
    }

    .quick-suggestions .label {
        display: block;
        color: #6b7280;
        font-weight: 500;
        margin-bottom: 0.75rem;
    }

    .suggestion-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        justify-content: center;
    }

    .suggestion-tag {
        background: linear-gradient(135deg, #dbeafe, #e0f2fe);
        color: #2563eb;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        text-decoration: none;
        font-weight: 500;
        font-size: 0.9rem;
        border: 1px solid rgba(37, 99, 235, 0.2);
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .suggestion-tag:hover {
        background: #2563eb;
        color: white;
        text-decoration: none;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }

    /* Chart Controls */
    .chart-controls {
        background: white;
        border-radius: 16px;
        padding: 1.5rem 2rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        border: 1px solid #e5e7eb;
        margin-bottom: 2rem;
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
    }

    .chart-toggle-btn {
        padding: 0.75rem 1.5rem;
        border: 2px solid #e5e7eb;
        background: white;
        color: #1f2937;
        border-radius: 10px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .chart-toggle-btn:hover {
        border-color: #2563eb;
        color: #2563eb;
        transform: translateY(-1px);
    }

    .chart-toggle-btn.active {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: white;
        border-color: transparent;
        box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
    }

    /* Chart Container */
    .chart-container {
        background: white;
        border-radius: 20px;
        padding: 1.5rem 1.5rem 0.5rem;
        box-shadow: 0 8px 32px rgba(37,99,235,0.10);
        border: 1px solid #e5e7eb;
        margin-bottom: 3rem;
        position: relative;
        overflow: hidden;
    }

    .chart-container::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; height: 4px;
        background: linear-gradient(90deg, #2563eb, #7c3aed, #10b981);
        border-radius: 20px 20px 0 0;
    }

    .chart-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
        flex-wrap: wrap;
        gap: 10px;
    }

    .chart-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: #1f2937;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .chart-period-btns {
        display: flex;
        gap: 6px;
    }

    .period-btn {
        padding: 4px 12px;
        border: 1px solid #e5e7eb;
        background: white;
        color: #6b7280;
        border-radius: 8px;
        font-size: 0.78rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .period-btn:hover, .period-btn.active {
        background: #2563eb;
        color: white;
        border-color: #2563eb;
    }

    #apexCandleChart, #apexLineChart {
        display: none;
    }

    #apexCandleChart.active, #apexLineChart.active {
        display: block;
    }

    /* Data Table */
    .data-section {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        border: 1px solid #e5e7eb;
        overflow: hidden;
    }

    .data-header {
        background: linear-gradient(135deg, #dbeafe, #f1f5f9);
        padding: 1.5rem 2rem;
        border-bottom: 1px solid #e5e7eb;
    }

    .data-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: #2563eb;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .data-table {
        margin: 0;
        width: 100%;
        border-collapse: collapse;
    }

    .data-table th {
        background: linear-gradient(135deg, #dbeafe, #f8fafc);
        border: none;
        font-weight: 600;
        color: #1f2937;
        padding: 1.25rem 1rem;
        text-align: center;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .data-table td {
        border: none;
        padding: 1rem;
        text-align: center;
        vertical-align: middle;
        font-weight: 500;
        transition: all 0.3s ease;
        border-bottom: 1px solid #f1f5f9;
    }

    .data-table tbody tr {
        transition: all 0.3s ease;
    }

    .data-table tbody tr:hover {
        background: linear-gradient(135deg, rgba(37, 99, 235, 0.05), rgba(59, 130, 246, 0.05));
    }

    .data-table tbody tr:nth-child(even) {
        background: rgba(37, 99, 235, 0.02);
    }

    .data-table tbody tr:last-child td {
        border-bottom: none;
    }

    /* Price styling */
    .price-positive {
        color: #10b981;
        font-weight: 600;
    }

    .price-negative {
        color: #ef4444;
        font-weight: 600;
    }

    .volume-cell {
        color: #2563eb;
        font-weight: 600;
        font-family: monospace;
    }

    /* Pagination */
    .pagination-container {
        padding: 2rem;
        background: #f9fafb;
    }

    .pagination {
        justify-content: center;
        margin: 0;
    }

    .pagination .page-item .page-link {
        border: 1px solid #e5e7eb;
        color: #1f2937;
        padding: 0.75rem 1rem;
        font-weight: 500;
        transition: all 0.3s ease;
        border-radius: 8px;
        margin: 0 2px;
    }

    .pagination .page-item.active .page-link {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        border-color: transparent;
        color: white;
        box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
    }

    .pagination .page-item:hover .page-link {
        background: #dbeafe;
        border-color: #2563eb;
        color: #2563eb;
    }

    /* Error Messages */
    .alert {
        border: none;
        border-radius: 12px;
        padding: 1.5rem;
        font-weight: 500;
        margin-bottom: 2rem;
    }

    .alert-danger {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        color: #dc2626;
        border-left: 4px solid #dc2626;
    }

    .alert-warning {
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        color: #d97706;
        border-left: 4px solid #d97706;
    }

    .error-message {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        color: #dc2626;
        padding: 0.75rem 1rem;
        border-radius: 8px;
        margin-top: 1rem;
        font-weight: 500;
        display: none;
        border-left: 4px solid #dc2626;
        align-items: center;
        gap: 8px;
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

    /* Enhanced Awesomplete - Fixed */
    .awesomplete {
        position: relative;
        width: 100% !important;
    }

    .awesomplete > ul {
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 10px 15px rgba(0, 0, 0, 0.15);
        background: white;
        z-index: 1000;
        max-height: 300px;
        overflow-y: auto;
        margin-top: 5px;
    }

    .awesomplete li {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid #f3f4f6;
        transition: all 0.3s ease;
        cursor: pointer;
        background: white;
        color: #1f2937;
    }

    .awesomplete li:hover,
    .awesomplete li[aria-selected="true"] {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: white;
        transform: translateX(4px);
    }

    .awesomplete li b {
        color: #2563eb;
        font-weight: 700;
        font-size: 1rem;
    }

    .awesomplete li:hover b,
    .awesomplete li[aria-selected="true"] b {
        color: #fbbf24;
        font-weight: 800;
    }

    .awesomplete li span {
        color: #6b7280;
        font-size: 0.9rem;
        font-weight: 500;
        display: block;
        margin-top: 0.25rem;
    }

    .awesomplete li:hover span,
    .awesomplete li[aria-selected="true"] span {
        color: #e5e7eb;
    }

    .awesomplete li:last-child {
        border-bottom: none;
        border-radius: 0 0 12px 12px;
    }

    .awesomplete li:first-child {
        border-radius: 12px 12px 0 0;
    }

    /* Loading spinner */
    .loading-spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        border-top-color: white;
        animation: spin 1s ease-in-out infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Responsive */
    @media (max-width: 768px) {
        .stock-title {
            font-size: 2rem;
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }

        .stock-symbol-badge {
            font-size: 1rem;
            padding: 0.4rem 0.8rem;
        }

        .search-form {
            flex-direction: column;
            gap: 12px;
        }

        .suggestion-tags {
            justify-content: flex-start;
        }

        .chart-wrapper {
            height: 400px;
        }

        .chart-controls {
            justify-content: center;
        }

        .data-table {
            font-size: 0.85rem;
        }

        .data-table th,
        .data-table td {
            padding: 0.75rem 0.5rem;
        }

        .search-input {
            padding: 1rem 1rem 1rem 3rem;
        }

        .search-icon {
            left: 1rem;
        }
    }
</style>
@endsection

@section('content')
<!-- Stock Header -->
<section class="stock-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="stock-title-container">
                    <h1 class="stock-title">
                        <i class="bi bi-graph-up-arrow"></i>
                        <span class="stock-symbol-badge">{{ $symbol }}</span>
                    </h1>
                    @if(isset($overview['name']) && $overview['name'])
                        <p class="stock-name">{{ $overview['name'] }}</p>
                    @endif
                    @php
                        $latestData = !empty($data) ? $data[count($data)-1] : null;
                        $prevData = count($data) >= 2 ? $data[count($data)-2] : null;
                        $latestClose = $latestData['close'] ?? null;
                        $prevClose = $prevData['close'] ?? null;
                        $dailyChange = ($latestClose && $prevClose && $prevClose > 0) ? (($latestClose - $prevClose) / $prevClose * 100) : null;
                        $latestDateStr = $latestData ? \Carbon\Carbon::createFromTimestampMs($latestData['time'])->format('d/m/Y') : null;
                    @endphp
                    @if($latestClose)
                    <div style="display:flex;align-items:center;gap:1.5rem;margin-top:0.75rem;flex-wrap:wrap;">
                        <div>
                            <span style="font-size:2rem;font-weight:800;color:white;">{{ number_format($latestClose, 0, ',', '.') }}</span>
                            <span style="font-size:0.85rem;opacity:0.75;margin-left:4px;">VNĐ</span>
                        </div>
                        @if($dailyChange !== null)
                        <div style="background:{{ $dailyChange >= 0 ? 'rgba(52,211,153,0.2)' : 'rgba(248,113,113,0.2)' }};border:1px solid {{ $dailyChange >= 0 ? 'rgba(52,211,153,0.4)' : 'rgba(248,113,113,0.4)' }};border-radius:8px;padding:4px 12px;font-weight:700;font-size:0.95rem;color:{{ $dailyChange >= 0 ? '#34d399' : '#f87171' }};">
                            <i class="bi bi-{{ $dailyChange >= 0 ? 'arrow-up' : 'arrow-down' }}"></i>
                            {{ $dailyChange >= 0 ? '+' : '' }}{{ number_format($dailyChange, 2) }}%
                        </div>
                        @endif
                        @if(isset($latestDateStr))
                        <div style="font-size:0.78rem;opacity:0.6;">
                            <i class="bi bi-calendar3"></i> {{ $latestDateStr }}
                        </div>
                        @endif
                    </div>
                    @else
                    <p class="stock-subtitle">Thông tin chi tiết cổ phiếu và biểu đồ giá</p>
                    @endif
                </div>
            </div>
            <div class="col-md-4 text-md-right">
                <div style="display:flex;flex-direction:column;align-items:flex-end;gap:10px;position:relative;z-index:2;">
                    <a href="{{ url('/') }}" class="back-button">
                        <i class="bi bi-house"></i>
                        Trang chủ
                    </a>
                    <a href="{{ url('/stock/compare?symbols='.$symbol) }}" class="back-button">
                        <i class="bi bi-bar-chart-steps"></i>
                        So sánh
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="container">
    <!-- Enhanced Search Section -->
    <div class="search-section">
        <form method="GET" action="{{ url('/stock') }}" autocomplete="off">
            <div class="search-form">
                <div class="search-input-wrapper">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" id="symbol" name="symbol" value="{{ $symbol }}" 
                           class="search-input" placeholder="Nhập mã cổ phiếu: VCB, FPT, VNM, E1VFVN30..." required autocomplete="off">
                </div>
                <button type="submit" class="search-btn">
                    <i class="bi bi-search"></i>
                    <span class="btn-text">Tra cứu</span>
                </button>
            </div>
            <div id="notFoundMsg" class="error-message">
                <i class="bi bi-exclamation-triangle"></i>
                Không tìm thấy mã cổ phiếu phù hợp!
            </div>
        </form>
        
        <!-- Quick Suggestions -->
        <div class="quick-suggestions">
            <span class="label">
                <i class="bi bi-lightbulb"></i>
                Gợi ý tìm kiếm phổ biến:
            </span>
            <div class="suggestion-tags">
                <span class="suggestion-tag" onclick="searchSymbol('VCB')" title="Ngân hàng Vietcombank">VCB - Vietcombank</span>
                <span class="suggestion-tag" onclick="searchSymbol('FPT')" title="Tập đoàn FPT">FPT - FPT Corporation</span>
                <span class="suggestion-tag" onclick="searchSymbol('VNM')" title="Vinamilk">VNM - Vinamilk</span>
                <span class="suggestion-tag" onclick="searchSymbol('VIC')" title="Vingroup">VIC - Vingroup</span>
                <span class="suggestion-tag" onclick="searchSymbol('E1VFVN30')" title="ETF FTSE Vietnam 30">E1VFVN30 - ETF VN30</span>
                <span class="suggestion-tag" onclick="searchSymbol('VNINDEX')" title="Chỉ số VN-Index">VNINDEX - VN-Index</span>
            </div>
        </div>
    </div>

    <!-- Error Messages -->
    @if(isset($error))
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i>
            {{ $error }}
        </div>
    @endif

    @if (count($data) > 0)
        <!-- Chart Controls -->
        <div class="chart-controls" data-aos="fade-up">
            <span style="color:#6b7280;font-weight:600;margin-right:0.5rem;font-size:0.9rem;">
                <i class="bi bi-bar-chart-line"></i> Loại biểu đồ:
            </span>
            <button id="btnCandle" class="chart-toggle-btn active">
                <i class="bi bi-bar-chart"></i> Biểu đồ nến
            </button>
            <button id="btnLine" class="chart-toggle-btn">
                <i class="bi bi-graph-up"></i> Biểu đồ đường
            </button>
            <div style="margin-left:auto;display:flex;align-items:center;gap:8px;font-size:0.8rem;color:#9ca3af;">
                <span style="width:10px;height:10px;border-radius:2px;background:#10b981;display:inline-block;"></span> Tăng
                <span style="width:10px;height:10px;border-radius:2px;background:#ef4444;display:inline-block;margin-left:4px;"></span> Giảm
            </div>
        </div>

        <!-- Chart Container -->
        <div class="chart-container" data-aos="fade-up" data-aos-delay="100">
            <div class="chart-header">
                <h3 class="chart-title">
                    <i class="bi bi-graph-up" style="color:#2563eb;"></i>
                    Biểu đồ giá {{ $symbol }}
                    @if(isset($overview['name']) && $overview['name'])
                        <small style="font-size:0.75em;color:#6b7280;font-weight:500;">({{ $overview['name'] }})</small>
                    @endif
                </h3>
                <div class="chart-period-btns" id="periodBtns">
                    <button class="period-btn" data-months="1">1T</button>
                    <button class="period-btn" data-months="3">3T</button>
                    <button class="period-btn" data-months="6">6T</button>
                    <button class="period-btn active" data-months="0">Tất cả</button>
                </div>
            </div>
            <div id="apexCandleChart" class="active"></div>
            <div id="apexLineChart"></div>
        </div>

        <!-- Data Table -->
        <div class="data-section" data-aos="fade-up" data-aos-delay="200">
            <div class="data-header">
                <h3 class="data-title">
                    <i class="bi bi-table"></i>
                    Dữ liệu lịch sử giá {{ $symbol }}
                    @if(isset($overview['name']) && $overview['name'])
                        <small style="font-size: 0.8em; opacity: 0.8;">({{ $overview['name'] }})</small>
                    @endif
                </h3>
            </div>
            
            <div class="table-responsive">
                <table class="table data-table">
                    <thead>
                        <tr>
                            <th><i class="bi bi-calendar-date"></i> Ngày</th>
                            <th><i class="bi bi-arrow-up-circle"></i> Mở cửa</th>
                            <th><i class="bi bi-arrow-up"></i> Cao nhất</th>
                            <th><i class="bi bi-arrow-down"></i> Thấp nhất</th>
                            <th><i class="bi bi-arrow-down-circle"></i> Đóng cửa</th>
                            <th><i class="bi bi-bar-chart"></i> Khối lượng</th>
                            <th><i class="bi bi-currency-exchange"></i> Tiền tệ</th>
                        </tr>
                    </thead>
                    <tbody id="priceTableBody">
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="pagination-container">
                <nav>
                    <ul class="pagination" id="tablePagination">
                        <!-- Pagination will be generated by JavaScript -->
                    </ul>
                </nav>
            </div>
        </div>
    @else
        <div class="alert alert-warning">
            <i class="bi bi-info-circle-fill"></i>
            Chưa có dữ liệu để hiển thị biểu đồ hoặc bảng. Vui lòng thử lại sau hoặc chọn mã cổ phiếu khác.
        </div>
    @endif
</div>
@endsection

@section('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/awesomplete/1.1.5/awesomplete.min.js"></script>
@if (count($data) > 0)
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.0/dist/apexcharts.min.js"></script>
@endif

<script>
function searchSymbol(symbol) {
    document.getElementById('symbol').value = symbol;
    document.querySelector('.search-section form').submit();
}

@if (count($data) > 0)
const rawData = @json($data);

// Prepare full datasets
const candleSeriesFull = rawData.map(d => ({
    x: new Date(d.time),
    y: [parseFloat(d.open), parseFloat(d.high), parseFloat(d.low), parseFloat(d.close)]
}));
const lineSeries = rawData.map(d => [new Date(d.time).getTime(), parseFloat(d.close)]);
const volumeSeries = rawData.map(d => ({
    x: new Date(d.time),
    y: parseFloat(d.volume)
}));

let activeMonths = 0; // 0 = all

function filterByMonths(months) {
    if (!months) return { candle: candleSeriesFull, line: lineSeries, vol: volumeSeries };
    const cutoff = new Date();
    cutoff.setMonth(cutoff.getMonth() - months);
    return {
        candle: candleSeriesFull.filter(d => d.x >= cutoff),
        line: lineSeries.filter(d => d[0] >= cutoff.getTime()),
        vol: volumeSeries.filter(d => d.x >= cutoff)
    };
}

// ── ApexCharts: Candlestick ──
const candleOptions = {
    series: [{ name: 'Giá', data: candleSeriesFull }],
    chart: {
        type: 'candlestick',
        height: 480,
        toolbar: { show: true, tools: { download: true, selection: true, zoom: true, zoomin: true, zoomout: true, pan: true, reset: true } },
        animations: { enabled: true, easing: 'easeinout', speed: 600 },
        background: 'transparent',
        fontFamily: 'Inter, sans-serif',
    },
    plotOptions: {
        candlestick: {
            colors: { upward: '#10b981', downward: '#ef4444' },
            wick: { useFillColor: true }
        }
    },
    xaxis: {
        type: 'datetime',
        labels: {
            datetimeFormatter: { year: 'yyyy', month: "MM/yyyy", day: 'dd/MM' },
            style: { fontSize: '11px', colors: '#6b7280' }
        },
        axisBorder: { show: false },
        axisTicks: { show: false }
    },
    yaxis: {
        tooltip: { enabled: true },
        labels: {
            formatter: v => (v/1000).toFixed(0) + 'K',
            style: { fontSize: '11px', colors: '#6b7280' }
        }
    },
    tooltip: {
        theme: 'light',
        x: { format: 'dd/MM/yyyy' },
        y: {
            formatter: v => v ? Number(v).toLocaleString('vi-VN') + ' VNĐ' : ''
        }
    },
    grid: {
        borderColor: '#f3f4f6',
        strokeDashArray: 4
    }
};

// ── ApexCharts: Area Line ──
const lineOptions = {
    series: [{ name: 'Giá đóng cửa', data: lineSeries }],
    chart: {
        type: 'area',
        height: 480,
        toolbar: { show: true, tools: { download: true, selection: true, zoom: true, zoomin: true, zoomout: true, pan: true, reset: true } },
        animations: { enabled: true, easing: 'easeinout', speed: 800, animateGradually: { enabled: true, delay: 100 } },
        background: 'transparent',
        fontFamily: 'Inter, sans-serif',
    },
    stroke: { curve: 'smooth', width: 2.5, colors: ['#2563eb'] },
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.45,
            opacityTo: 0.0,
            stops: [0, 100],
            colorStops: [
                { offset: 0, color: '#2563eb', opacity: 0.4 },
                { offset: 100, color: '#2563eb', opacity: 0 }
            ]
        }
    },
    colors: ['#2563eb'],
    xaxis: {
        type: 'datetime',
        labels: {
            datetimeFormatter: { year: 'yyyy', month: "MM/yyyy", day: 'dd/MM' },
            style: { fontSize: '11px', colors: '#6b7280' }
        },
        axisBorder: { show: false },
        axisTicks: { show: false }
    },
    yaxis: {
        labels: {
            formatter: v => (v/1000).toFixed(0) + 'K',
            style: { fontSize: '11px', colors: '#6b7280' }
        }
    },
    tooltip: {
        theme: 'light',
        x: { format: 'dd/MM/yyyy' },
        y: { formatter: v => Number(v).toLocaleString('vi-VN') + ' VNĐ' }
    },
    grid: {
        borderColor: '#f3f4f6',
        strokeDashArray: 4
    },
    markers: { size: 0, hover: { size: 5 } },
    dataLabels: { enabled: false }
};

let candleChart = new ApexCharts(document.getElementById('apexCandleChart'), candleOptions);
let lineChart = new ApexCharts(document.getElementById('apexLineChart'), lineOptions);
candleChart.render();
lineChart.render();

// Toggle charts
document.getElementById('btnCandle').addEventListener('click', function() {
    document.getElementById('apexCandleChart').classList.add('active');
    document.getElementById('apexLineChart').classList.remove('active');
    this.classList.add('active');
    document.getElementById('btnLine').classList.remove('active');
    candleChart.updateOptions({}, false, true);
});
document.getElementById('btnLine').addEventListener('click', function() {
    document.getElementById('apexLineChart').classList.add('active');
    document.getElementById('apexCandleChart').classList.remove('active');
    this.classList.add('active');
    document.getElementById('btnCandle').classList.remove('active');
    lineChart.updateOptions({}, false, true);
});

// Period filter
document.querySelectorAll('.period-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const months = parseInt(this.dataset.months) || 0;
        const filtered = filterByMonths(months);
        candleChart.updateSeries([{ name: 'Giá', data: filtered.candle }]);
        lineChart.updateSeries([{ name: 'Giá đóng cửa', data: filtered.line }]);
    });
});

// Table
const pageSize = 20;
let currentPage = 1;

function renderTable(page) {
    currentPage = page;
    const start = (page - 1) * pageSize;
    const pageData = rawData.slice().reverse().slice(start, start + pageSize);
    const tbody = document.getElementById('priceTableBody');
    tbody.innerHTML = pageData.map(item => {
        const date = new Date(item.time).toLocaleDateString('vi-VN');
        const close = parseFloat(item.close);
        const open = parseFloat(item.open);
        const isUp = close >= open;
        return `<tr>
            <td style="font-weight:600;">${date}</td>
            <td>${Number(item.open).toLocaleString()}</td>
            <td class="price-positive">${Number(item.high).toLocaleString()}</td>
            <td class="price-negative">${Number(item.low).toLocaleString()}</td>
            <td style="font-weight:700;color:${isUp ? '#10b981' : '#ef4444'};">
                <i class="bi bi-${isUp ? 'arrow-up' : 'arrow-down'}"></i>
                ${Number(item.close).toLocaleString()}
            </td>
            <td class="volume-cell">${Number(item.volume).toLocaleString()}</td>
            <td><span class="badge badge-primary">${item.currency || 'VND'}</span></td>
        </tr>`;
    }).join('');
    renderPagination();
}

function renderPagination() {
    const totalPages = Math.ceil(rawData.length / pageSize);
    const p = document.getElementById('tablePagination');
    let h = '';
    if (currentPage > 1) h += `<li class="page-item"><a class="page-link" href="#" onclick="renderTable(${currentPage-1});return false;"><i class="bi bi-chevron-left"></i></a></li>`;
    const sp = Math.max(1, currentPage-2), ep = Math.min(totalPages, currentPage+2);
    if (sp > 1) { h += `<li class="page-item"><a class="page-link" href="#" onclick="renderTable(1);return false;">1</a></li>`; if (sp>2) h += `<li class="page-item disabled"><span class="page-link">...</span></li>`; }
    for (let i=sp;i<=ep;i++) h += `<li class="page-item${i===currentPage?' active':''}"><a class="page-link" href="#" onclick="renderTable(${i});return false;">${i}</a></li>`;
    if (ep < totalPages) { if (ep<totalPages-1) h += `<li class="page-item disabled"><span class="page-link">...</span></li>`; h += `<li class="page-item"><a class="page-link" href="#" onclick="renderTable(${totalPages});return false;">${totalPages}</a></li>`; }
    if (currentPage < totalPages) h += `<li class="page-item"><a class="page-link" href="#" onclick="renderTable(${currentPage+1});return false;"><i class="bi bi-chevron-right"></i></a></li>`;
    p.innerHTML = h;
}
renderTable(1);
@endif

// Awesomplete autocomplete
let awesomplete = new Awesomplete(document.getElementById('symbol'), { minChars: 1, maxItems: 15, autoFirst: true, list: [] });
let searchTimeout;
document.getElementById('symbol').addEventListener('input', function() {
    const val = this.value.trim();
    const notFoundMsg = document.getElementById('notFoundMsg');
    if (val.length < 1) { notFoundMsg.classList.remove('show'); return; }
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        fetch('/stocks-list?q=' + encodeURIComponent(val))
            .then(r => r.json())
            .then(data => {
                notFoundMsg.classList[data.length === 0 ? 'add' : 'remove']('show');
                awesomplete.list = data.map(item => ({ label: `<b>${item.symbol}</b><span>${item.name ? ' - '+item.name : ''}</span>`, value: item.symbol }));
            })
            .catch(() => notFoundMsg.classList.add('show'));
    }, 300);
});
document.getElementById('symbol').addEventListener('focus', () => document.getElementById('notFoundMsg').classList.remove('show'));
document.querySelector('.search-section form').addEventListener('submit', function() {
    const btn = this.querySelector('.search-btn');
    const btnText = btn.querySelector('.btn-text');
    const btnIcon = btn.querySelector('i');
    btn.disabled = true;
    if (btnIcon) btnIcon.className = 'loading-spinner';
    if (btnText) btnText.textContent = 'Đang tìm...';
    setTimeout(() => { btn.disabled = false; if (btnIcon) btnIcon.className = 'bi bi-search'; if (btnText) btnText.textContent = 'Tra cứu'; }, 5000);
});
document.addEventListener('keydown', e => { if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); document.getElementById('symbol').focus(); } });
</script>
@endsection


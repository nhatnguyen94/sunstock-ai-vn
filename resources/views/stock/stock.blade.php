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
        border-radius: 16px;
        padding: 2rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        border: 1px solid #e5e7eb;
        margin-bottom: 3rem;
    }

    .chart-header {
        margin-bottom: 2rem;
        text-align: center;
    }

    .chart-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: #2563eb;
        margin: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .chart-wrapper {
        position: relative;
        height: 500px;
        width: 100%;
    }

    .chart-item {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .chart-item.active {
        opacity: 1;
    }

    #priceChart, #candlestickChart {
        width: 100% !important;
        height: 100% !important;
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
                    <p class="stock-subtitle">Thông tin chi tiết cổ phiếu và biểu đồ giá</p>
                </div>
            </div>
            <div class="col-md-4 text-md-right">
                <a href="{{ url('/') }}" class="back-button">
                    <i class="bi bi-arrow-left"></i>
                    Quay lại trang chủ
                </a>
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
        <div class="chart-controls">
            <span style="color: #6b7280; font-weight: 500; margin-right: 1rem;">
                <i class="bi bi-bar-chart-line"></i>
                Loại biểu đồ:
            </span>
            <button id="btnCandle" class="chart-toggle-btn active" title="Xem biểu đồ nến (chi tiết giá từng phiên)">
                <i class="bi bi-bar-chart"></i>
                Biểu đồ nến
            </button>
            <button id="btnLine" class="chart-toggle-btn" title="Xem biểu đồ đường (giá đóng cửa)">
                <i class="bi bi-graph-up"></i>
                Biểu đồ đường
            </button>
        </div>

        <!-- Chart Container -->
        <div class="chart-container">
            <div class="chart-header">
                <h3 class="chart-title">
                    <i class="bi bi-graph-up"></i>
                    Biểu đồ giá {{ $symbol }}
                    @if(isset($overview['name']) && $overview['name'])
                        <small style="font-size: 0.8em; opacity: 0.8;">({{ $overview['name'] }})</small>
                    @endif
                </h3>
            </div>
            
            <div class="chart-wrapper">
                <!-- Candlestick Chart -->
                <div id="candleChartWrap" class="chart-item active">
                    <canvas id="candlestickChart"></canvas>
                </div>

                <!-- Line Chart -->
                <div id="lineChartWrap" class="chart-item">
                    <canvas id="priceChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="data-section">
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
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-chart-financial@0.2.1/dist/chartjs-chart-financial.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/luxon@2.5.2/build/global/luxon.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-luxon@1.2.0/dist/chartjs-adapter-luxon.min.js"></script>
@endif

<script>
// Global variables for charts
let candleChart = null;
let lineChart = null;

// Quick search function
function searchSymbol(symbol) {
    document.getElementById('symbol').value = symbol;
    document.querySelector('.search-section form').submit();
}

@if (count($data) > 0)
    // Prepare data
    const rawData = @json($data);
    console.log('Raw data:', rawData);

    // Chart data preparation
    const labels = rawData.map(item => {
        const date = new Date(item.time);
        return date.toLocaleDateString('vi-VN');
    });
    
    const prices = rawData.map(item => parseFloat(item.close));
    
    const candlestickData = rawData.map(item => ({
        x: item.time,
        o: parseFloat(item.open),
        h: parseFloat(item.high),
        l: parseFloat(item.low),
        c: parseFloat(item.close)
    }));

    console.log('Labels:', labels);
    console.log('Prices:', prices);
    console.log('Candlestick data:', candlestickData);

    // Create Candlestick Chart
    const ctxCandle = document.getElementById('candlestickChart').getContext('2d');
    candleChart = new Chart(ctxCandle, {
        type: 'candlestick',
        data: {
            datasets: [{
                label: 'Biểu đồ nến {{ $symbol }}',
                data: candlestickData,
                color: {
                    up: '#10b981',
                    down: '#ef4444',
                    unchanged: '#6b7280'
                }
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 1000,
                easing: 'easeInOutQuart'
            },
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        color: '#1f2937',
                        font: { weight: 600, size: 14 }
                    }
                }
            },
            scales: {
                x: {
                    type: 'time',
                    adapters: { date: { zone: 'Asia/Ho_Chi_Minh' } },
                    time: { unit: 'day', tooltipFormat: 'dd/MM/yyyy' },
                    title: {
                        display: true,
                        text: 'Ngày',
                        color: '#1f2937',
                        font: { weight: 600, size: 13 }
                    },
                    grid: { color: 'rgba(229, 231, 235, 0.3)' }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Giá (VNĐ)',
                        color: '#1f2937',
                        font: { weight: 600, size: 13 }
                    },
                    grid: { color: 'rgba(229, 231, 235, 0.3)' }
                }
            }
        }
    });

    // Create Line Chart
    const ctx = document.getElementById('priceChart').getContext('2d');
    lineChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Giá đóng cửa {{ $symbol }}',
                data: prices,
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                pointHoverRadius: 6,
                pointBackgroundColor: '#2563eb',
                pointBorderColor: 'white',
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 1000,
                easing: 'easeInOutQuart'
            },
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        color: '#1f2937',
                        font: { weight: 600, size: 14 }
                    }
                }
            },
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Ngày',
                        color: '#1f2937',
                        font: { weight: 600, size: 13 }
                    },
                    grid: { color: 'rgba(229, 231, 235, 0.3)' }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Giá (VNĐ)',
                        color: '#1f2937',
                        font: { weight: 600, size: 13 }
                    },
                    grid: { color: 'rgba(229, 231, 235, 0.3)' }
                }
            }
        }
    });

    // Simple chart switching
    function showChart(showId, hideId) {
        const hideElement = document.getElementById(hideId);
        const showElement = document.getElementById(showId);
        
        hideElement.classList.remove('active');
        showElement.classList.add('active');
        
        // Resize chart after switching
        setTimeout(() => {
            if (showId === 'candleChartWrap' && candleChart) {
                candleChart.resize();
            } else if (showId === 'lineChartWrap' && lineChart) {
                lineChart.resize();
            }
        }, 300);
    }

    // Chart button events
    document.getElementById('btnCandle').addEventListener('click', function() {
        showChart('candleChartWrap', 'lineChartWrap');
        
        // Update button states
        document.getElementById('btnCandle').classList.add('active');
        document.getElementById('btnLine').classList.remove('active');
    });

    document.getElementById('btnLine').addEventListener('click', function() {
        showChart('lineChartWrap', 'candleChartWrap');
        
        // Update button states
        document.getElementById('btnLine').classList.add('active');
        document.getElementById('btnCandle').classList.remove('active');
    });

    // Table pagination
    const pageSize = 20;
    let currentPage = 1;
    
    function renderTable(page) {
        currentPage = page;
        const start = (page - 1) * pageSize;
        const end = start + pageSize;
        const pageData = rawData.slice().reverse().slice(start, end);
        const tbody = document.getElementById('priceTableBody');
        
        tbody.innerHTML = pageData.map(item => {
            const date = new Date(item.time).toLocaleDateString('vi-VN');
            const volume = Number(item.volume).toLocaleString();
            
            return `
                <tr>
                    <td style="font-weight: 600;">${date}</td>
                    <td>${Number(item.open).toLocaleString()}</td>
                    <td class="price-positive">${Number(item.high).toLocaleString()}</td>
                    <td class="price-negative">${Number(item.low).toLocaleString()}</td>
                    <td style="font-weight: 600;">${Number(item.close).toLocaleString()}</td>
                    <td class="volume-cell">${volume}</td>
                    <td><span class="badge badge-primary">${item.currency || 'VND'}</span></td>
                </tr>
            `;
        }).join('');
        
        renderPagination();
    }
    
    function renderPagination() {
        const totalPages = Math.ceil(rawData.length / pageSize);
        const pagination = document.getElementById('tablePagination');
        let html = '';
        
        // Previous button
        if (currentPage > 1) {
            html += `<li class="page-item">
                        <a class="page-link" href="#" onclick="renderTable(${currentPage - 1}); return false;">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>`;
        }
        
        // Page numbers
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);
        
        if (startPage > 1) {
            html += `<li class="page-item">
                        <a class="page-link" href="#" onclick="renderTable(1); return false;">1</a>
                    </li>`;
            if (startPage > 2) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            html += `<li class="page-item${i === currentPage ? ' active' : ''}">
                        <a class="page-link" href="#" onclick="renderTable(${i}); return false;">${i}</a>
                    </li>`;
        }
        
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            html += `<li class="page-item">
                        <a class="page-link" href="#" onclick="renderTable(${totalPages}); return false;">${totalPages}</a>
                    </li>`;
        }
        
        // Next button
        if (currentPage < totalPages) {
            html += `<li class="page-item">
                        <a class="page-link" href="#" onclick="renderTable(${currentPage + 1}); return false;">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>`;
        }
        
        pagination.innerHTML = html;
    }
    
    // Initialize table
    renderTable(1);

@endif

// Enhanced Awesomplete autocomplete
let awesomplete = new Awesomplete(document.getElementById('symbol'), {
    minChars: 1,
    maxItems: 15,
    autoFirst: true,
    list: []
});

let searchTimeout;

document.getElementById('symbol').addEventListener('input', function() {
    let val = this.value.trim();
    const notFoundMsg = document.getElementById('notFoundMsg');
    
    if (val.length < 1) {
        notFoundMsg.classList.remove('show');
        return;
    }
    
    // Clear previous timeout
    clearTimeout(searchTimeout);
    
    // Set new timeout for debounce
    searchTimeout = setTimeout(() => {
        fetch('/stocks-list?q=' + encodeURIComponent(val))
            .then(res => res.json())
            .then(data => {
                if (data.length === 0) {
                    notFoundMsg.classList.add('show');
                } else {
                    notFoundMsg.classList.remove('show');
                }
                
                // Enhanced autocomplete with stock names
                let list = data.map(item => ({
                    label: `<b>${item.symbol}</b><span>${item.name ? ' - ' + item.name : ''}</span>`,
                    value: item.symbol
                }));
                
                awesomplete.list = list;
            })
            .catch(err => {
                notFoundMsg.classList.add('show');
                console.error('Lỗi lấy danh sách mã:', err);
            });
    }, 300); // 300ms debounce
});

// Hide error message when user focuses input
document.getElementById('symbol').addEventListener('focus', function() {
    document.getElementById('notFoundMsg').classList.remove('show');
});

// Loading state for search form
document.querySelector('.search-section form').addEventListener('submit', function() {
    const btn = this.querySelector('.search-btn');
    const btnText = btn.querySelector('.btn-text');
    const btnIcon = btn.querySelector('i');
    
    if (!btnText || !btnIcon) return;
    
    btn.disabled = true;
    btnIcon.className = 'loading-spinner';
    btnText.textContent = 'Đang tìm...';
    
    // Re-enable after 5 seconds (fallback)
    setTimeout(() => {
        btn.disabled = false;
        btnIcon.className = 'bi bi-search';
        btnText.textContent = 'Tra cứu';
    }, 5000);
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Focus search input with Ctrl/Cmd + K
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        document.getElementById('symbol').focus();
    }
});
</script>
@endsection
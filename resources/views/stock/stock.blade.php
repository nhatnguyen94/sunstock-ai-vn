@extends('layouts.app')

@section('head')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/awesomplete/1.1.5/awesomplete.min.css" />
@vite('resources/frontend/css/stock/stock.css')
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
        <!-- Indicator Toolbar -->
        <div class="indicator-toolbar" data-aos="fade-up" id="indicatorToolbar">
            <span class="indicator-label"><i class="bi bi-sliders"></i> Chỉ báo:</span>
            <label class="indicator-check" title="Simple Moving Average 20 phiên">
                <input type="checkbox" id="indMA20"> <span style="color:#f59e0b;">MA20</span>
            </label>
            <label class="indicator-check" title="Simple Moving Average 50 phiên">
                <input type="checkbox" id="indMA50"> <span style="color:#3b82f6;">MA50</span>
            </label>
            <label class="indicator-check" title="Simple Moving Average 200 phiên">
                <input type="checkbox" id="indMA200"> <span style="color:#ec4899;">MA200</span>
            </label>
            <label class="indicator-check" title="Bollinger Bands (20, 2)">
                <input type="checkbox" id="indBB"> <span style="color:#8b5cf6;">Bollinger</span>
            </label>
            <span class="indicator-sep">|</span>
            <label class="indicator-check" title="Relative Strength Index 14 phiên — biểu đồ phụ bên dưới">
                <input type="checkbox" id="indRSI"> <span style="color:#06b6d4;">RSI(14)</span>
            </label>
            <label class="indicator-check" title="MACD (12,26,9) — biểu đồ phụ bên dưới">
                <input type="checkbox" id="indMACD"> <span style="color:#10b981;">MACD</span>
            </label>
        </div>

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

        <!-- RSI Sub-chart -->
        <div id="rsiChartWrapper" style="display:none;margin-top:12px;" data-aos="fade-up">
            <div class="sub-chart-header">
                <span class="sub-chart-label"><i class="bi bi-activity" style="color:#06b6d4;"></i> RSI (14)</span>
                <span class="sub-chart-hint">Vùng quá mua: &gt;70 &nbsp;|&nbsp; Vùng quá bán: &lt;30</span>
            </div>
            <div id="rsiChart"></div>
        </div>

        <!-- MACD Sub-chart -->
        <div id="macdChartWrapper" style="display:none;margin-top:12px;" data-aos="fade-up">
            <div class="sub-chart-header">
                <span class="sub-chart-label"><i class="bi bi-bar-chart-line" style="color:#10b981;"></i> MACD (12, 26, 9)</span>
                <span class="sub-chart-hint">Đường MACD vượt Signal → xu hướng tăng</span>
            </div>
            <div id="macdChart"></div>
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

    <!-- Finance Section -->
    <div class="finance-section" data-aos="fade-up" data-aos-delay="100">
        <div class="data-header">
            <h3 class="data-title">
                <i class="bi bi-building"></i>
                Tài chính doanh nghiệp <span>{{ $symbol }}</span>
            </h3>
            <div id="financeTabBar" style="display:none;">
                <div class="finance-type-tabs">
                    <button class="fin-type-btn active" data-type="income">KQKD</button>
                    <button class="fin-type-btn" data-type="balance">Bảng cân đối</button>
                    <button class="fin-type-btn" data-type="cashflow">Lưu chuyển tiền</button>
                    <button class="fin-type-btn" data-type="ratio">Chỉ số tài chính</button>
                </div>
                <div class="finance-period-tabs">
                    <button class="fin-period-btn active" data-period="quarter">Quý</button>
                    <button class="fin-period-btn" data-period="year">Năm</button>
                </div>
            </div>
        </div>
        <div id="financeBody">
            <div style="text-align:center;padding:2rem 0;">
                <button id="btnLoadFinance" class="btn-load-finance">
                    <i class="bi bi-table"></i> Tải dữ liệu tài chính
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/awesomplete/1.1.5/awesomplete.min.js"></script>
@if (count($data) > 0)
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.0/dist/apexcharts.min.js"></script>
@endif

<script>
const rawData = @json($data);
const stockSymbol = '{{ $symbol }}';
</script>
@vite('resources/frontend/js/stock/stock.js')
@endsection
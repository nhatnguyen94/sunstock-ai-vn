@extends('layouts.app')
@use('App\Support\VnFormat')
@use('App\Frontend\Services\WatchlistService')

@section('head')
@vite(['resources/frontend/css/stock/stock.css', 'resources/frontend/css/shared/charts.css', 'resources/frontend/css/shared/watchlist.css', 'resources/frontend/css/shared/skeleton.css'])
@endsection

@section('content')
@php
    $q = $summary;
    $dir = $q['direction'] ?? 'flat';
    $companyName = $overview['name'] ?? null;
    $exchange = VnFormat::exchange($overview['exchange'] ?? null);
    $industry = $overview['industry'] ?? null;
    $d = $q['decimals'] ?? 0;
    $liveDate = isset($q['date']) ? VnFormat::date($q['date']) : null;
    $caret = ['up' => 'bi-caret-up-fill', 'down' => 'bi-caret-down-fill', 'flat' => 'bi-dash'][$dir];
@endphp
<!-- Stock Header -->
<section class="sq-hero">
    <div class="container">
        <div class="sq-card" data-dir="{{ $dir }}">
            <div class="sq-top">
                <div class="sq-id">
                    <div class="sq-id-row">
                        <h1 class="sq-symbol">{{ $symbol }}</h1>
                        @if($exchange !== '')
                            <span class="sq-badge">{{ $exchange }}</span>
                        @endif
                    </div>
                    <p class="sq-name">
                        {{ $companyName ?: 'Thông tin chi tiết cổ phiếu và biểu đồ giá' }}@if($industry) <span class="sq-dot">·</span> {{ $industry }}@endif
                    </p>
                </div>
                <div class="sq-actions">
                    <button type="button" class="sq-btn wl-toggle" data-watch="{{ $symbol }}">
                        <i class="bi bi-star"></i>
                        <span data-watch-label>Theo dõi</span>
                    </button>
                    <a href="{{ route('portfolio.quick-add', ['symbol' => $symbol]) }}" class="sq-btn sq-btn-dark" title="Thêm {{ $symbol }} vào danh mục đầu tư của bạn">
                        <i class="bi bi-briefcase"></i>
                        Danh mục
                    </a>
                    <a href="{{ route('company.show', $symbol) }}" class="sq-btn" title="Hồ sơ công ty {{ $symbol }}">
                        <i class="bi bi-building"></i>
                        Hồ sơ
                    </a>
                    <a href="{{ url('/stock/compare?symbols='.$symbol) }}" class="sq-btn" title="So sánh {{ $symbol }} với mã khác">
                        <i class="bi bi-bar-chart-steps"></i>
                        So sánh
                    </a>
                </div>
            </div>

            @if($q)
                <div class="sq-quote">
                    <div class="sq-price-wrap">
                        <span class="sq-price is-{{ $dir }}" id="sqPrice"
                              data-from="{{ $q['prev_close'] ?? $q['close'] }}" data-to="{{ $q['close'] }}" data-decimals="{{ $d }}">{{ VnFormat::number($q['close'], $d) }}</span>
                        <span class="sq-unit">{{ $q['unit'] }}</span>
                    </div>
                    @if($q['change'] !== null)
                        <span class="sq-chip is-{{ $dir }}" id="sqChip" title="So với phiên trước ({{ VnFormat::number($q['prev_close'], $d) }})">
                            <i class="bi {{ $caret }}"></i>
                            {{ VnFormat::signed($q['change'], $d) }}
                            <span class="sq-chip-pct">({{ VnFormat::percent($q['change_pct'], 2, true) }})</span>
                        </span>
                    @endif
                    <span class="sq-date">
                        <i class="bi bi-calendar3"></i> {{ $liveDate }}
                        @if(! empty($liveBar))
                            <span class="sq-live{{ $liveBar['running'] ? ' is-running' : '' }}"
                                  title="Nến này lấy từ bảng giá thị trường{{ $liveBar['running'] ? ' và đang cập nhật theo phiên giao dịch' : '' }}">
                                {{ $liveBar['running'] ? 'đang giao dịch' : 'từ bảng giá' }}
                            </span>
                        @endif
                    </span>
                </div>

                @if($q['day_position'] !== null || $q['year_position'] !== null)
                    <div class="sq-ranges">
                        @if($q['day_position'] !== null)
                            <div class="sq-range" title="Giá đóng cửa nằm ở {{ VnFormat::number($q['day_position'], 0) }}% biên độ của phiên">
                                <div class="sq-range-top"><span>{{ VnFormat::number($q['low'], $d) }}</span><b>Biên độ phiên</b><span>{{ VnFormat::number($q['high'], $d) }}</span></div>
                                <div class="sq-range-bar is-{{ $dir }}" style="--pos: {{ round($q['day_position'], 1) }}%"><i></i></div>
                            </div>
                        @endif
                        @if($q['year_position'] !== null)
                            <div class="sq-range" title="Giá nằm ở {{ VnFormat::number($q['year_position'], 0) }}% biên độ 52 tuần">
                                <div class="sq-range-top"><span>{{ VnFormat::number($q['year_low'], $d) }}</span><b>Biên độ 52 tuần</b><span>{{ VnFormat::number($q['year_high'], $d) }}</span></div>
                                <div class="sq-range-bar is-{{ $dir }}" style="--pos: {{ round($q['year_position'], 1) }}%"><i></i></div>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="sq-tiles">
                    <div class="sq-tile">
                        <span class="sq-tile-label">Khối lượng</span>
                        <b class="sq-tile-value">{{ VnFormat::volume($q['volume']) }}</b>
                        @if($q['volume_ratio'] !== null)
                            <span class="sq-tile-sub {{ $q['volume_ratio'] >= 1.5 ? 'is-hot' : '' }}">{{ VnFormat::number($q['volume_ratio'], 1) }}× trung bình 20 phiên</span>
                        @endif
                    </div>
                    <div class="sq-tile">
                        <span class="sq-tile-label">Mở cửa</span>
                        <b class="sq-tile-value">{{ VnFormat::number($q['open'], $d) }}</b>
                        @if($q['prev_close'] !== null)
                            <span class="sq-tile-sub">Hôm trước {{ VnFormat::number($q['prev_close'], $d) }}</span>
                        @endif
                    </div>
                    @foreach(['1T' => '1 tháng', '3T' => '3 tháng', '6T' => '6 tháng', '1N' => '1 năm'] as $key => $label)
                        <div class="sq-tile">
                            <span class="sq-tile-label">{{ $label }}</span>
                            <b class="sq-tile-value {{ VnFormat::trendClass($q['returns'][$key]) }}">{{ VnFormat::percent($q['returns'][$key], 1, true) }}</b>
                        </div>
                    @endforeach
                </div>
            @endif
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
                    <button class="period-btn" id="btnShot" title="Lưu biểu đồ thành ảnh PNG"><i class="bi bi-camera"></i></button>
                </div>
            </div>
            <div id="priceChart">
                <div class="sk-chart" aria-hidden="true">
                    @foreach([34, 52, 41, 63, 48, 70, 58, 76, 62, 82, 68, 55, 72, 60, 78, 66, 84, 71, 59, 74, 64, 80, 69, 57] as $h)
                        <i class="sk sk-bar" style="height: {{ $h }}%"></i>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="data-section px-data" data-aos="fade-up" data-aos-delay="200">
            <div class="px-data-head">
                <h3 class="px-data-title">
                    <i class="bi bi-table"></i>
                    Lịch sử giá {{ $symbol }}
                </h3>
                <span class="px-data-hint">
                    Giá tính bằng {{ $q && $q['is_index'] ? 'điểm' : 'đồng (₫)' }} · <span id="pxRange"></span>
                </span>
            </div>

            <div class="px-scroll">
                <table class="px-table" id="priceTable">
                    <thead>
                        <tr>
                            <th class="px-left">Ngày</th>
                            <th>Đóng cửa</th>
                            <th>Thay đổi</th>
                            <th>Mở cửa</th>
                            <th>Cao nhất</th>
                            <th>Thấp nhất</th>
                            <th class="px-vol">Khối lượng</th>
                        </tr>
                    </thead>
                    <tbody id="priceTableBody">
                        @foreach(range(1, 10) as $i)
                            <tr class="sk-row" aria-hidden="true">
                                @foreach(range(1, 7) as $c)
                                    <td><i class="sk sk-text"></i></td>
                                @endforeach
                            </tr>
                        @endforeach
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
@php
    $unitData = $summary
        ? ['scale' => $summary['scale'], 'decimals' => $summary['decimals'], 'unit' => $summary['unit']]
        : ['scale' => 1000, 'decimals' => 0, 'unit' => '₫'];
@endphp
<script>
const rawData = @json($data);
const stockSymbol = '{{ $symbol }}';
const stockUnit = @json($unitData);
</script>
@php
    $watchData = ['auth' => auth()->check(), 'watched' => auth()->check() && app(WatchlistService::class)->isWatched(auth()->id(), $symbol) ? [$symbol] : []];
@endphp
<script>window.__WATCH__ = @json($watchData);</script>
@vite('resources/frontend/js/shared/watchlist-init.js')
@vite('resources/frontend/js/stock/stock.js')
@endsection
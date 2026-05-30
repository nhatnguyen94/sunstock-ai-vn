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
@vite('resources/frontend/css/index.css')
@endsection

@section('content')
<!-- Hero Search Section -->
<section class="hero-search" style="position:relative; overflow:hidden;">
    <!-- Animated background shapes -->
    <div style="position:absolute;inset:0;overflow:hidden;pointer-events:none;">
        <div style="position:absolute;top:-30%;right:-10%;width:500px;height:500px;border-radius:50%;background:rgba(255,255,255,0.04);"></div>
        <div style="position:absolute;bottom:-20%;left:-5%;width:350px;height:350px;border-radius:50%;background:rgba(255,255,255,0.03);"></div>
        <div style="position:absolute;top:20%;left:8%;width:3px;height:80px;background:rgba(251,191,36,0.4);border-radius:2px;"></div>
        <div style="position:absolute;top:40%;right:12%;width:3px;height:50px;background:rgba(52,211,153,0.3);border-radius:2px;"></div>
    </div>
    <div class="container">
        <div class="hero-content">
            <div style="display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.25);border-radius:30px;padding:6px 18px;margin-bottom:1.5rem;font-size:0.85rem;font-weight:600;backdrop-filter:blur(10px);">
                <span style="width:8px;height:8px;border-radius:50%;background:#34d399;animation:pulse-ring 1.5s ease-out infinite;display:inline-block;"></span>
                Dữ liệu cập nhật tự động mỗi ngày
            </div>
            <h1 class="hero-title">
                <i class="bi bi-graph-up-arrow" style="color: #fbbf24;"></i>
                Sun Stock AI
            </h1>
            <p class="hero-subtitle">
                Phân tích cổ phiếu Việt Nam thông minh &amp; Tỷ giá realtime với AI
            </p>
            <div class="d-flex flex-wrap justify-content-center gap-3 mb-4" style="gap:12px;">
                <a href="{{ url('/stock/compare') }}" class="btn-primary-custom" style="background:rgba(255,255,255,0.2);border:1px solid rgba(255,255,255,0.4);backdrop-filter:blur(10px);">
                    <i class="bi bi-bar-chart-steps"></i> So sánh cổ phiếu
                </a>
                @guest
                <a href="{{ route('register') }}" style="background:#fbbf24;color:#1e3a5f;border-radius:10px;padding:0.875rem 2rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:8px;transition:all 0.2s;">
                    <i class="bi bi-person-plus"></i> Đăng ký miễn phí
                </a>
                @endguest
            </div>
            <!-- Hero stats row -->
            <div class="d-flex flex-wrap justify-content-center" style="gap:2rem;margin-top:0.5rem;">
                <div style="color:rgba(255,255,255,0.9);text-align:center;">
                    <div style="font-size:1.6rem;font-weight:800;line-height:1;">700+</div>
                    <div style="font-size:0.78rem;opacity:0.7;margin-top:2px;">Mã cổ phiếu</div>
                </div>
                <div style="width:1px;background:rgba(255,255,255,0.2);height:40px;align-self:center;"></div>
                <div style="color:rgba(255,255,255,0.9);text-align:center;">
                    <div style="font-size:1.6rem;font-weight:800;line-height:1;">20+</div>
                    <div style="font-size:0.78rem;opacity:0.7;margin-top:2px;">Ngoại tệ</div>
                </div>
                <div style="width:1px;background:rgba(255,255,255,0.2);height:40px;align-self:center;"></div>
                <div style="color:rgba(255,255,255,0.9);text-align:center;">
                    <div style="font-size:1.6rem;font-weight:800;line-height:1;">AI</div>
                    <div style="font-size:0.78rem;opacity:0.7;margin-top:2px;">Phân tích</div>
                </div>
                <div style="width:1px;background:rgba(255,255,255,0.2);height:40px;align-self:center;"></div>
                <div style="color:rgba(255,255,255,0.9);text-align:center;">
                    <div style="font-size:1.6rem;font-weight:800;line-height:1;">Free</div>
                    <div style="font-size:0.78rem;opacity:0.7;margin-top:2px;">Hoàn toàn miễn phí</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Search Card -->
<div class="container">
    <div class="search-card" data-aos="fade-up" data-aos-delay="100">
        <form method="GET" action="{{ url('/stock') }}" class="search-form-wrapper" autocomplete="off">
            <div class="search-input-group">
                <div class="search-input-container">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" id="symbol" name="symbol" class="search-input" 
                           placeholder="Nhập mã cổ phiếu: FPT, VNM, VCB… hoặc tên công ty" required autocomplete="off">
                </div>
                <button type="submit" class="search-btn">
                    <i class="bi bi-search"></i>
                    <span class="btn-text">Tra cứu</span>
                </button>
            </div>
        </form>
        <p style="text-align:center;color:var(--text-secondary);font-size:0.82rem;margin:0.75rem 0 0.5rem;">
            <i class="bi bi-keyboard"></i> Phím tắt: <kbd style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:4px;padding:2px 6px;">Ctrl+K</kbd> để focus ô tìm kiếm
        </p>
        
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
            <a href="{{ url('/stock?symbol=VCB') }}" class="tag">VCB</a>
            <a href="{{ url('/stock?symbol=TCB') }}" class="tag">TCB</a>
            <a href="{{ url('/stock?symbol=HPG') }}" class="tag">HPG</a>
        </div>
    </div>
</div>

<div class="container">
    <!-- 1. FEATURED STOCKS -->
    <section class="featured-section" data-aos="fade-up">
        <h2 class="section-title">
            <i class="bi bi-star-fill" style="color: var(--warning-orange); margin-right: 10px;"></i>
            Cổ phiếu nổi bật
            <span style="display:block; font-size:0.95rem; color:#6b7280; font-weight:400; margin-top:0.5rem;">
                Giá hiển thị theo đơn vị <b>K = 1.000 VNĐ</b>
            </span>
        </h2>
        
        <div class="featured-grid">
            @foreach($featured as $i => $stock)
            <div class="stock-card" data-aos="fade-up" data-aos-delay="{{ $i * 100 }}">
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
                    <i class="bi {{ $stock['change'] >= 0 ? 'bi-arrow-up-circle-fill' : 'bi-arrow-down-circle-fill' }}"></i>
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

    <!-- FEATURES HIGHLIGHT SECTION -->
    <section style="margin:0 0 3rem;" data-aos="fade-up">
        <h2 style="text-align:center;font-size:1.8rem;font-weight:700;color:var(--text-primary);margin-bottom:0.5rem;">
            Tại sao chọn <span style="background:linear-gradient(135deg,var(--primary-blue),var(--secondary-blue));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">Sun Stock AI</span>?
        </h2>
        <p style="text-align:center;color:var(--text-secondary);margin-bottom:2.5rem;">Công cụ phân tích cổ phiếu toàn diện, hoàn toàn miễn phí</p>
        <div class="row g-3">
            @php
            $features = [
                ['icon'=>'bi-graph-up-arrow','color'=>'#2563eb','bg'=>'#eff6ff','title'=>'Biểu đồ giá lịch sử','desc'=>'Xem biểu đồ nến, đường giá với dữ liệu lịch sử lên đến nhiều năm'],
                ['icon'=>'bi-cpu','color'=>'#7c3aed','bg'=>'#f5f3ff','title'=>'Phân tích AI','desc'=>'Hỏi AI về bất kỳ cổ phiếu nào, nhận phân tích và dự đoán thông minh'],
                ['icon'=>'bi-currency-exchange','color'=>'#059669','bg'=>'#ecfdf5','title'=>'Tỷ giá realtime','desc'=>'Tỷ giá Vietcombank cập nhật hàng ngày, hỗ trợ 20+ ngoại tệ'],
                ['icon'=>'bi-intersect','color'=>'#dc2626','bg'=>'#fef2f2','title'=>'So sánh cổ phiếu','desc'=>'So sánh hiệu suất của nhiều cổ phiếu cùng lúc trên cùng một biểu đồ'],
                ['icon'=>'bi-fire','color'=>'#ea580c','bg'=>'#fff7ed','title'=>'Ngành hot','desc'=>'Theo dõi các ngành nghề đang nổi bật: Ngân hàng, BĐS, Công nghệ'],
                ['icon'=>'bi-briefcase','color'=>'#0891b2','bg'=>'#ecfeff','title'=>'Danh mục cá nhân','desc'=>'Quản lý danh mục đầu tư, theo dõi lãi/lỗ và target price (cần đăng ký)'],
            ];
            @endphp
            @foreach($features as $j => $f)
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="{{ $j * 80 }}">
                <div style="background:white;border-radius:16px;padding:1.75rem;border:1px solid var(--border-color);height:100%;display:flex;gap:1rem;align-items:flex-start;transition:all 0.3s ease;box-shadow:var(--shadow-sm);" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='var(--shadow-lg)'" onmouseout="this.style.transform='';this.style.boxShadow='var(--shadow-sm)'">
                    <div style="width:50px;height:50px;border-radius:14px;background:{{ $f['bg'] }};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="bi {{ $f['icon'] }}" style="font-size:1.4rem;color:{{ $f['color'] }};"></i>
                    </div>
                    <div>
                        <div style="font-weight:700;color:var(--text-primary);margin-bottom:0.4rem;">{{ $f['title'] }}</div>
                        <div style="font-size:0.875rem;color:var(--text-secondary);line-height:1.5;">{{ $f['desc'] }}</div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </section>

    <!-- AI Prediction Button -->
    <div class="text-center my-4" data-aos="zoom-in">
        <button id="aiPredictBtn" class="btn btn-primary-custom" style="font-size:1.05rem;padding:1rem 2.5rem;border-radius:20px;box-shadow:0 8px 30px rgba(37,99,235,0.3);display:inline-flex;align-items:center;gap:10px;">
            <i class="bi bi-robot" style="font-size:1.4rem;"></i>
            AI Dự đoán thị trường tuần này
            <span style="background:rgba(255,255,255,0.2);border-radius:20px;padding:2px 8px;font-size:0.75rem;">BETA</span>
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

    <!-- 2. EXCHANGE RATES -->
    @if(count($exchangeRates) > 0)
    <section class="info-section" data-aos="fade-up">
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

    <!-- 3. HOT INDUSTRIES -->
    @if($hotIndustries->count() > 0)
    <section class="info-section" id="hot-industries-section" data-aos="fade-up">
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

    <!-- CTA Banner for guests -->
    @guest
    <section data-aos="zoom-in" style="margin-bottom:3rem;">
        <div style="background:linear-gradient(135deg,var(--primary-blue),#7c3aed);border-radius:20px;padding:3rem 2rem;text-align:center;position:relative;overflow:hidden;">
            <div style="position:absolute;top:-20px;right:-20px;width:150px;height:150px;background:rgba(255,255,255,0.05);border-radius:50%;"></div>
            <div style="position:absolute;bottom:-30px;left:-10px;width:100px;height:100px;background:rgba(255,255,255,0.04);border-radius:50%;"></div>
            <h3 style="color:white;font-size:1.8rem;font-weight:800;margin-bottom:0.75rem;position:relative;">
                <i class="bi bi-briefcase" style="color:#fbbf24;"></i>
                Quản lý danh mục đầu tư
            </h3>
            <p style="color:rgba(255,255,255,0.85);font-size:1.05rem;max-width:550px;margin:0 auto 2rem;position:relative;line-height:1.7;">
                Đăng ký tài khoản miễn phí để theo dõi danh mục cổ phiếu cá nhân, tính toán lãi/lỗ tự động và nhận cảnh báo giá mục tiêu.
            </p>
            <div class="d-flex flex-wrap justify-content-center" style="gap:12px;position:relative;">
                <a href="{{ route('register') }}" style="background:#fbbf24;color:#1e3a5f;border-radius:12px;padding:0.875rem 2rem;font-weight:800;text-decoration:none;display:inline-flex;align-items:center;gap:8px;font-size:1rem;transition:all 0.2s;">
                    <i class="bi bi-person-plus-fill"></i> Tạo tài khoản miễn phí
                </a>
                <a href="{{ route('login') }}" style="background:rgba(255,255,255,0.15);color:white;border:2px solid rgba(255,255,255,0.4);border-radius:12px;padding:0.875rem 2rem;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:8px;font-size:1rem;transition:all 0.2s;">
                    <i class="bi bi-box-arrow-in-right"></i> Đăng nhập
                </a>
            </div>
        </div>
    </section>
    @endguest

    <!-- 4. NEWS -->
    @if(isset($news) && $news->isNotEmpty())
    <section class="info-section" data-aos="fade-up">
        <h3>
            <i class="bi bi-newspaper" style="color: var(--primary-blue); margin-right: 10px;"></i>
            Tin tức thị trường mới nhất
            <span style="font-size: 0.7em; color: var(--text-secondary); font-weight: 400;">
                (VnExpress · CafeF · Dân Trí)
            </span>
        </h3>
        
        <div class="news-grid">
            @foreach($news as $item)
            <article class="news-card" data-aos="fade-up" data-aos-delay="{{ $loop->index * 80 }}">
                @if($item->image_url)
                <div class="news-image">
                    <img src="{{ $item->image_url }}" alt="{{ $item->title }}" loading="lazy">
                    <div class="news-date-badge">
                        <i class="bi bi-clock"></i>
                        {{ $item->published_at->diffForHumans() }}
                    </div>
                </div>
                @endif
                
                <div class="news-content">
                    <a href="{{ $item->url }}" target="_blank" rel="noopener" class="news-title">
                        {{ $item->title }}
                    </a>
                    
                    <p class="news-description">
                        {{ $item->description }}
                    </p>
                    
                    <div class="news-meta">
                        <div class="news-date">
                            <i class="bi bi-calendar3"></i>
                            {{ $item->published_at->format('d/m/Y H:i') }}
                        </div>
                        <a href="{{ $item->url }}" target="_blank" rel="noopener" class="news-read-more">
                            Đọc thêm <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </article>
            @endforeach
        </div>
        
        <div style="text-align: center; margin-top: 2rem;">
            <a href="{{ route('news.index') }}"
               style="display:inline-flex;align-items:center;gap:0.5rem;background:var(--light-blue);color:var(--primary-blue);padding:0.75rem 1.5rem;border-radius:25px;text-decoration:none;font-weight:500;transition:all 0.3s ease;"
               onmouseover="this.style.background='var(--primary-blue)';this.style.color='white'"
               onmouseout="this.style.background='var(--light-blue)';this.style.color='var(--primary-blue)'">
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
window._isAuth = {{ json_encode(Auth::check()) }};
</script>
@vite('resources/frontend/js/index.js')
@endsection
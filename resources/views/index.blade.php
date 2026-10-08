@extends('layouts.app')
@use('Carbon\Carbon')
@use('App\Support\VnFormat', 'F')

@php
    // (guarded: a view is compiled to a plain PHP file, so a second render in the same process would redeclare it)
    if (! function_exists('parseRate')) {
        function parseRate($value) {
            $value = trim($value);
            if ($value === '-' || $value === '' || $value === null) return null;
            $value = str_replace(',', '', $value);
            return is_numeric($value) ? (float)$value : null;
        }
    }
@endphp

@section('head')
@vite(['resources/frontend/css/index.css', 'resources/frontend/css/market/market.css', 'resources/frontend/css/shared/watchlist.css', 'resources/frontend/css/shared/charts.css'])
@endsection

@section('content')
<!-- Hero: title + search in one compact band, the market comes right under it -->
<section class="hero-search hero-compact">
    <div class="hero-fx" aria-hidden="true">
        <i class="orb o1"></i><i class="orb o2"></i>
        <span class="bar b1"></span><span class="bar b2"></span><span class="bar b3"></span>
        <svg class="hero-line" viewBox="0 0 1200 120" preserveAspectRatio="none">
            <defs><linearGradient id="heroLineFill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".22"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></linearGradient></defs>
            <path d="M0,92 L50,86 L100,90 L150,78 L200,82 L250,70 L300,76 L350,64 L400,70 L450,58 L500,66 L550,54 L600,60 L650,48 L700,56 L750,44 L800,52 L850,40 L900,46 L950,34 L1000,42 L1050,30 L1100,36 L1150,24 L1200,30 L1200,120 L0,120 Z" fill="url(#heroLineFill)"/>
            <path d="M0,92 L50,86 L100,90 L150,78 L200,82 L250,70 L300,76 L350,64 L400,70 L450,58 L500,66 L550,54 L600,60 L650,48 L700,56 L750,44 L800,52 L850,40 L900,46 L950,34 L1000,42 L1050,30 L1100,36 L1150,24 L1200,30" fill="none" stroke="rgba(255,255,255,.55)" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round"/>
        </svg>
    </div>
    <div class="container">
        <div class="hero-content">
            <h1 class="hero-title">
                <i class="bi bi-graph-up-arrow" style="color: #fbbf24;"></i>
                Sun Stock AI
            </h1>
            <p class="hero-subtitle">Phân tích cổ phiếu Việt Nam &amp; tỷ giá, có AI hỗ trợ</p>

            <div class="search-card">
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

                <div id="notFoundMsg" class="error-message">
                    <i class="bi bi-exclamation-triangle"></i>
                    Không tìm thấy mã cổ phiếu phù hợp!
                </div>

                <div class="popular-tags">
                    <span class="popular-label">Mã phổ biến:</span>
                    @foreach($featured as $stock)
                        <a href="{{ url('/stock?symbol='.$stock['symbol']) }}" class="tag" title="{{ $stock['name'] }}">{{ $stock['symbol'] }}</a>
                    @endforeach
                    <a href="{{ url('/stock?symbol=VCB') }}" class="tag">VCB</a>
                    <a href="{{ url('/stock?symbol=TCB') }}" class="tag">TCB</a>
                    <a href="{{ url('/stock?symbol=HPG') }}" class="tag">HPG</a>
                    <a href="{{ url('/stock/compare') }}" class="tag tag-tool"><i class="bi bi-bar-chart-steps"></i> So sánh</a>
                    <kbd class="popular-kbd d-none d-md-inline" title="Phím tắt mở thanh tìm nhanh">Ctrl+K</kbd>
                </div>
            </div>
        </div>
    </div>
</section>
{{-- one continuous background from the hero to the footer: the hero blue turns to indigo, violet and orchid (analogous colours), with glows and a dot grid --}}
<div class="home-body {{ ! empty($mine) && ($market['has_data'] ?? false) ? 'has-mine' : '' }}">
<div class="home-glow" aria-hidden="true"><i class="g1"></i><i class="g2"></i><i class="g3"></i><i class="g4"></i><i class="g5"></i></div>

@php
    // helpers and numbers shared by the four tabs (an included partial sees them)
    $tz = 'Asia/Ho_Chi_Minh';
    $dir = fn ($v) => $v > 0 ? 'up' : ($v < 0 ? 'down' : 'flat');
    $icon = fn ($v) => $v > 0 ? 'bi-caret-up-fill' : ($v < 0 ? 'bi-caret-down-fill' : 'bi-dash');
    $signedMoney = fn ($v) => ($v > 0 ? '+' : '').F::bigMoney($v);
    $hasHeat = ! empty($heatmap['items']);
    if ($market['has_data'] ?? false) {
        $b = $market['breadth'];
        $tot = max($b['total'], 1);
        $liq = $market['liquidity'];
    }
    $homeSections = [
        'overview' => ['Tổng quan', 'house-door', null, null],
        'markets' => ['Thị trường', 'activity', 'Thị trường &amp; dòng tiền', 'Danh sách tăng giảm, độ rộng, khối ngoại, tâm lý và các thị trường thế giới'],
        'signals' => ['Tín hiệu & sự kiện', 'lightning-charge', 'Tín hiệu &amp; sự kiện', 'Điều vừa xảy ra trên bảng giá và lịch cổ tức, đại hội sắp tới'],
        'explore' => ['Khám phá', 'compass', 'Khám phá thêm', 'Cổ phiếu nổi bật, ngành hot và tỷ giá ngoại tệ'],
    ];
@endphp
<section class="mk" id="market">
<div class="container">
    {{-- Sticky jump bar: the page is one continuous scroll (nothing hidden behind tabs); this only says where things are --}}
    <nav class="home-jump" id="homeJump" aria-label="Các phần của trang chủ">
        @foreach($homeSections as $key => [$label, $secIcon])
            <a href="#hp{{ ucfirst($key) }}" data-sec="hp{{ ucfirst($key) }}" class="{{ $loop->first ? 'active' : '' }}"><i class="bi bi-{{ $secIcon }}" aria-hidden="true"></i> <span>{{ $label }}</span></a>
        @endforeach
        <a href="#homeNews" data-sec="homeNews"><i class="bi bi-newspaper" aria-hidden="true"></i> <span>Tin tức</span></a>
    </nav>

    @foreach($homeSections as $key => [$label, $secIcon, $title, $sub])
        <section id="hp{{ ucfirst($key) }}" class="home-section" @if($title) aria-labelledby="hp{{ ucfirst($key) }}Title" @endif>
            @if($title)
                <header class="home-sec-head">
                    <span class="home-sec-no" aria-hidden="true">{{ sprintf('%02d', $loop->index + 1) }}</span>
                    <div><h2 id="hp{{ ucfirst($key) }}Title">{!! $title !!}</h2><p>{{ $sub }}</p></div>
                </header>
            @endif
            @include('partials.home.'.$key)
        </section>
    @endforeach

    @include('partials.home.news')
</div>
</section>

</div>{{-- /home-body --}}
@endsection


@section('scripts')

@php
    $marketJs = ($market['has_data'] ?? false) ? [
        'auth' => auth()->check(),
        'open' => (bool) $market['market_open'],
        'dataUrl' => route('market.data', [], false),
        'watchlistUrl' => auth()->check() ? route('watchlist.index', [], false) : '/login',
        'series' => $market['vnindex_series'],
        'movers' => $market['movers'],
        'watchlist' => $watchRows,
        'watched' => $watched,
        'heatmap' => $heatmap,
        'foreign' => $market['foreign'] ?? null,
        'sentiment' => $extras['sentiment'] ?? null,
    ] : null;
@endphp
<script>
window._isAuth = {{ json_encode(Auth::check()) }};
window.__MARKET__ = @json($marketJs);
</script>
@vite('resources/frontend/js/index.js')
@vite('resources/frontend/js/market/home.js')
@endsection
@extends('layouts.app')

@section('title', $fund->short_name . ' – Quỹ mở | Sun Stock AI')

@section('head')
@vite(['resources/frontend/css/funds/funds.css', 'resources/frontend/css/shared/charts.css'])
@endsection

@use('App\Support\VnFormat', 'F')

@php
    $returns = [
        'nav_change_1m' => '1 tháng', 'nav_change_3m' => '3 tháng', 'nav_change_6m' => '6 tháng',
        'nav_change_12m' => '1 năm', 'nav_change_24m' => '2 năm', 'nav_change_36m' => '3 năm',
        'nav_change_36m_annualized' => '3 năm (năm hóa)', 'nav_change_inception' => 'Từ khi thành lập',
    ];
@endphp

@section('content')
<section class="fd-header">
    <div class="container">
        <div class="row align-items-center fd-header-row">
            <div class="col-lg-8">
                <div class="fd-badge"><i class="bi bi-pie-chart"></i> {{ $fund->type_label }}</div>
                <h1 class="fd-title"><span class="fd-symbol">{{ $fund->short_name }}</span></h1>
                <p class="fd-subtitle">{{ \Illuminate\Support\Str::title(mb_strtolower($fund->name)) }}</p>
                <p class="fd-owner"><i class="bi bi-building"></i> {{ \Illuminate\Support\Str::title(mb_strtolower((string) $fund->fund_owner_name)) }}</p>
            </div>
            <div class="col-lg-4 text-lg-right fd-header-actions">
                <a href="{{ route('funds.index') }}" class="fd-btn"><i class="bi bi-collection"></i> Tất cả quỹ</a>
                <a href="{{ route('funds.compare', ['codes' => $fund->short_name]) }}" class="fd-btn"><i class="bi bi-intersect"></i> So sánh</a>
            </div>
        </div>
    </div>
</section>

<div class="fd-main">
<div class="container">

    <div class="fd-kpis fd-kpis-up">
        <div class="fd-kpi"><span class="fd-kpi-label">NAV / CCQ</span><span class="fd-kpi-value">{{ F::number($fund->nav) }} ₫</span>
            <span class="fd-kpi-sub {{ F::trendClass($fund->nav_change_previous) }}">{{ F::percent($fund->nav_change_previous, 2, true) }} so với phiên trước</span></div>
        <div class="fd-kpi"><span class="fd-kpi-label">Ngày NAV</span><span class="fd-kpi-value">{{ F::date($fund->nav_update_at?->toDateString()) }}</span></div>
        <div class="fd-kpi"><span class="fd-kpi-label">Phí quản lý</span><span class="fd-kpi-value">{{ F::percent($fund->management_fee, 2) }}<small>/năm</small></span></div>
        <div class="fd-kpi"><span class="fd-kpi-label">Thành lập</span><span class="fd-kpi-value">{{ F::date($fund->inception_date?->toDateString()) }}</span></div>
    </div>

    <div class="fd-card">
        <h2 class="fd-h2"><i class="bi bi-percent"></i> Lợi suất</h2>
        <div class="fd-returns">
            @foreach($returns as $key => $label)
                <div class="fd-return">
                    <span class="fd-return-label">{{ $label }}</span>
                    <span class="fd-pill fd-pill-lg {{ F::trendClass($fund->$key) }}">{{ F::percent($fund->$key, 2, true) }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="fd-card" id="fdNavCard" data-url="{{ route('funds.detail', $fund->short_name) }}" data-code="{{ $fund->short_name }}">
        <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap:.5rem;">
            <h2 class="fd-h2 mb-0"><i class="bi bi-graph-up"></i> Diễn biến NAV</h2>
            <div class="fd-ranges" id="fdRanges">
                @foreach(['1M', '3M', '6M', '1Y', '3Y', 'ALL'] as $r)
                    <button type="button" data-range="{{ $r }}" class="{{ $r === '1Y' ? 'active' : '' }}">{{ $r === 'ALL' ? 'Tất cả' : $r }}</button>
                @endforeach
            </div>
        </div>
        <div class="fd-loading" id="fdLoading"><div class="fd-spinner"></div><span>Đang tải dữ liệu NAV và danh mục…</span></div>
        <div class="fd-error" id="fdError" hidden>
            <span id="fdErrorText"></span>
            <button type="button" class="fd-link-btn" id="fdRetry"><i class="bi bi-arrow-clockwise"></i> Thử lại</button>
        </div>
        <div id="fdNavContent" hidden>
            <div id="fdNavChart" class="fd-chart-lg"></div>
            <div class="fd-stats" id="fdStats">
                <div><span>Lợi suất kỳ này</span><strong id="fdStReturn">—</strong></div>
                <div><span>Sụt giảm tối đa</span><strong id="fdStDD">—</strong></div>
                <div><span>Biến động (năm hóa)</span><strong id="fdStVol">—</strong></div>
            </div>
        </div>
    </div>

    <div class="row" id="fdHoldings" hidden>
        <div class="col-lg-5">
            <div class="fd-card">
                <h2 class="fd-h2"><i class="bi bi-diagram-3"></i> Phân bổ tài sản</h2>
                <div id="fdAssetChart" class="fd-chart"></div>
                <h2 class="fd-h2 mt-4"><i class="bi bi-bar-chart"></i> Theo ngành</h2>
                <div id="fdIndustryChart"></div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="fd-card">
                <h2 class="fd-h2"><i class="bi bi-list-ol"></i> Top khoản đầu tư <small id="fdAsOf" class="fd-asof"></small></h2>
                <div class="table-responsive">
                    <table class="fd-table">
                        <thead><tr><th>#</th><th>Mã CP</th><th>Ngành</th><th class="num">% tài sản</th></tr></thead>
                        <tbody id="fdTopBody"></tbody>
                    </table>
                </div>
                <p class="fd-footnote mb-0">Bấm vào mã cổ phiếu để xem hồ sơ doanh nghiệp.</p>
            </div>
        </div>
    </div>

    <p class="fd-footnote">Nguồn: Fmarket qua vnstock. Hiệu suất quá khứ không đảm bảo kết quả tương lai — thông tin chỉ mang tính tham khảo, không phải khuyến nghị đầu tư.</p>
</div>
</div>
@endsection

@section('scripts')
<script>window.__COMPANY_URL__ = @json(url('/company'));</script>
@vite('resources/frontend/js/funds/show.js')
@endsection

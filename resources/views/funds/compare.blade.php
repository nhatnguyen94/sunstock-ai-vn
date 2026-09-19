@extends('layouts.app')

@section('title', 'So sánh quỹ mở | Sun Stock AI')

@section('head')
@vite(['resources/frontend/css/funds/funds.css', 'resources/frontend/css/shared/charts.css'])
@endsection

@section('content')
<section class="fd-header">
    <div class="container">
        <div class="row align-items-center fd-header-row">
            <div class="col-md-8">
                <div class="fd-badge"><i class="bi bi-intersect"></i> Tối đa {{ $max }} quỹ</div>
                <h1 class="fd-title"><i class="bi bi-bar-chart-steps"></i> So sánh quỹ mở</h1>
                <p class="fd-subtitle">Tăng trưởng NAV quy đổi về 100, lợi suất theo kỳ, sụt giảm tối đa, biến động và phí quản lý</p>
            </div>
            <div class="col-md-4 text-md-right fd-header-actions">
                <a href="{{ route('funds.index') }}" class="fd-btn"><i class="bi bi-collection"></i> Tất cả quỹ</a>
            </div>
        </div>
    </div>
</section>

<div class="fd-main">
<div class="container">

    <div class="fd-card">
        <div class="d-flex flex-wrap align-items-end" style="gap:.75rem;">
            <div style="flex:1 1 280px;">
                <label for="fdAdd" class="fd-label">Thêm quỹ để so sánh</label>
                <select id="fdAdd" class="form-control"></select>
            </div>
            <div id="fdChips" class="fd-chips"></div>
        </div>
    </div>

    <div id="fdEmpty" class="fd-card fd-state" hidden>
        <i class="bi bi-intersect fd-state-icon"></i>
        <h3>Chọn ít nhất 2 quỹ để so sánh</h3>
        <p>Dùng ô phía trên để thêm quỹ, hoặc chọn nhiều quỹ ngay ở <a href="{{ route('funds.index') }}">danh sách quỹ</a>.</p>
    </div>

    <div id="fdCompare" hidden>
        <div class="fd-card">
            <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap:.5rem;">
                <h2 class="fd-h2 mb-0"><i class="bi bi-graph-up"></i> Tăng trưởng NAV (quy đổi = 100)</h2>
                <div class="fd-ranges" id="fdRanges">
                    @foreach(['1M', '3M', '6M', '1Y', '3Y', 'ALL'] as $r)
                        <button type="button" data-range="{{ $r }}" class="{{ $r === '1Y' ? 'active' : '' }}">{{ $r === 'ALL' ? 'Tất cả' : $r }}</button>
                    @endforeach
                </div>
            </div>
            <div class="fd-loading" id="fdLoading"><div class="fd-spinner"></div><span>Đang tải dữ liệu NAV…</span></div>
            <div id="fdLineChart" class="fd-chart-lg"></div>
            <p class="fd-footnote mb-0" id="fdLineNote"></p>
        </div>

        <div class="fd-card">
            <h2 class="fd-h2"><i class="bi bi-bar-chart"></i> Lợi suất theo kỳ (%)</h2>
            <div id="fdBarChart"></div>
        </div>

        <div class="fd-card">
            <h2 class="fd-h2"><i class="bi bi-table"></i> Bảng so sánh <small class="fd-asof" id="fdTableRange"></small></h2>
            <div class="table-responsive">
                <table class="fd-table fd-compare-table" id="fdCompareTable"></table>
            </div>
            <p class="fd-footnote mb-0">Ô <span class="fd-best-sample">xanh đậm</span> = giá trị tốt nhất trong hàng. Sụt giảm tối đa và biến động tính từ chuỗi NAV trong kỳ đã chọn (không có cho “Tất cả”). Hiệu suất quá khứ không đảm bảo kết quả tương lai.</p>
        </div>
    </div>

</div>
</div>
@endsection

@section('scripts')
@php
    $pageData = [
        'max'          => $max,
        'picker'       => $picker,
        'detailUrl'    => url('/funds'),
        'returnLabels' => \App\Models\Fund::RETURN_COLUMNS,
        'selected'     => $funds->map(fn ($f) => [
            'code'    => $f->short_name,
            'name'    => $f->name,
            'type'    => $f->type_label,
            'fee'     => $f->management_fee,
            'nav'     => $f->nav,
            'returns' => collect(\App\Models\Fund::RETURN_COLUMNS)->keys()->mapWithKeys(fn ($k) => [$k => $f->$k])->all(),
        ])->values(),
    ];
@endphp
<script>window.__COMPARE__ = @json($pageData);</script>
@vite('resources/frontend/js/funds/compare.js')
@endsection

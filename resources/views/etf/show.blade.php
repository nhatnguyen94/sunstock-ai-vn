@extends('layouts.app')

@section('title', $etf->symbol . ' – Quỹ ETF | Sun Stock AI')

@section('head')
@vite(['resources/frontend/css/funds/funds.css', 'resources/frontend/css/etf/etf.css', 'resources/frontend/css/shared/charts.css', 'resources/frontend/css/shared/watchlist.css'])
@endsection

@use('App\Support\VnFormat', 'F')
@use('App\Models\Etf')

@php
    $windowLabels = ['1M' => '1 tháng', '3M' => '3 tháng', '6M' => '6 tháng', '1Y' => '1 năm', '3Y' => '3 năm'];
    $isEtf = $etf->kind === Etf::KIND_ETF;
    $chartPoints = count($series);
@endphp

@section('content')
<section class="fd-header">
    <div class="container">
        <div class="row align-items-center fd-header-row">
            <div class="col-lg-8">
                <div class="fd-badge"><i class="bi bi-bar-chart-steps"></i> {{ $isEtf ? 'Quỹ ETF' : 'Quỹ đóng niêm yết' }} · {{ $etf->exchange ?? 'HOSE' }}</div>
                <h1 class="fd-title"><span class="fd-symbol">{{ $etf->symbol }}</span></h1>
                <p class="fd-subtitle">{{ $row['short'] }}</p>
                @if($row['manager'] || $row['index'])
                    <p class="fd-owner">
                        @if($row['manager'])<i class="bi bi-building"></i> {{ $row['manager'] }}@endif
                        @if($row['index'])&nbsp;·&nbsp;<i class="bi bi-graph-up"></i> Bám chỉ số {{ $row['index'] }}@endif
                    </p>
                @endif
            </div>
            <div class="col-lg-4 text-lg-right fd-header-actions">
                <a href="{{ route('etf.index') }}" class="fd-btn"><i class="bi bi-collection"></i> Tất cả ETF</a>
                <button type="button" class="fd-btn wl-toggle" data-watch="{{ $etf->symbol }}"><i class="bi bi-star"></i> <span data-watch-label>Theo dõi</span></button>
                <a href="{{ route('portfolio.quick-add', ['symbol' => $etf->symbol]) }}" class="fd-btn"><i class="bi bi-briefcase"></i> Thêm vào danh mục</a>
            </div>
        </div>
    </div>
</section>

<div class="fd-main">
<div class="container">

    <div class="fd-kpis fd-kpis-up">
        <div class="fd-kpi"><span class="fd-kpi-label">Giá {{ $row['source'] === 'live' ? 'hiện tại' : 'đóng cửa' }}</span>
            <span class="fd-kpi-value">{{ F::number($row['price']) }} ₫</span>
            <span class="fd-kpi-sub {{ F::trendClass($row['percent']) }}">{{ F::percent($row['percent'], 2, true) }} @if($row['as_of']) · {{ F::date($row['as_of']) }} @endif</span></div>
        <div class="fd-kpi"><span class="fd-kpi-label">GTGD phiên gần nhất</span>
            <span class="fd-kpi-value">{{ $row['value'] !== null ? F::bigMoney($row['value']) : '—' }}</span>
            <span class="fd-kpi-sub">{{ $row['volume'] !== null ? F::number($row['volume']) . ' CCQ' : '' }}</span></div>
        <div class="fd-kpi"><span class="fd-kpi-label">GTGD TB 20 phiên</span>
            <span class="fd-kpi-value">{{ $row['avg_value'] !== null ? F::bigMoney($row['avg_value']) : '—' }}</span>
            <span class="fd-kpi-sub">thanh khoản</span></div>
        <div class="fd-kpi"><span class="fd-kpi-label">Vùng giá 52 tuần</span>
            <span class="fd-kpi-value">{{ $range['low'] !== null ? F::number($range['low']) . ' – ' . F::number($range['high']) : '—' }}</span>
            <span class="fd-kpi-sub">₫ / chứng chỉ quỹ</span></div>
    </div>

    @if($index_note)
        <div class="fd-card">
            <h2 class="fd-h2"><i class="bi bi-graph-up"></i> Chỉ số {{ $row['index'] }}</h2>
            <p class="etf-note">{{ $index_note }} <span class="fd-asof">Mô tả tóm tắt, quy tắc chính thức do HOSE công bố.</span></p>
        </div>
    @endif

    <div class="fd-card" id="etfChartCard">
        <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:.6rem;">
            <h2 class="fd-h2 mb-0"><i class="bi bi-graph-up-arrow"></i> Biểu đồ giá</h2>
            <div class="etf-ranges" id="etfRanges">
                <button type="button" data-days="91">3T</button>
                <button type="button" data-days="182">6T</button>
                <button type="button" data-days="365" class="active">1N</button>
                <button type="button" data-days="0">Tất cả</button>
            </div>
        </div>
        @if($chartPoints >= 2)
            <div id="etfChart" class="etf-chart mt-3"></div>
            <p class="fd-footnote mb-0">Giá đóng cửa theo ngày (₫). Muốn xem nến và chỉ báo kỹ thuật, mở <a href="{{ route('stock.index', ['symbol' => $etf->symbol]) }}">trang cổ phiếu {{ $etf->symbol }}</a>.</p>
        @else
            <p class="etf-empty-chart">Chưa có đủ dữ liệu giá của quỹ này.</p>
        @endif
    </div>

    <div class="fd-card">
        <h2 class="fd-h2"><i class="bi bi-percent"></i> Hiệu suất và rủi ro</h2>
        <div class="table-responsive">
            <table class="etf-windows">
                <thead>
                    <tr><th>Kỳ</th><th class="num">Lợi suất giá</th><th class="num">Sụt giảm tối đa</th><th class="num">Biến động (năm hóa)</th></tr>
                </thead>
                <tbody>
                    @foreach($windows as $key => $w)
                        <tr>
                            <td>{{ $windowLabels[$key] }}</td>
                            <td class="num"><span class="fd-pill {{ F::trendClass($w['return_pct']) }}">{{ F::percent($w['return_pct'], 2, true) }}</span></td>
                            <td class="num">{{ F::percent($w['max_drawdown'], 2) }}</td>
                            <td class="num">{{ F::percent($w['volatility'], 2) }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td>Từ đầu năm</td>
                        <td class="num"><span class="fd-pill {{ F::trendClass($ytd) }}">{{ F::percent($ytd, 2, true) }}</span></td>
                        <td class="num">—</td><td class="num">—</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="fd-footnote mb-0">Tính từ giá đóng cửa trên sàn, chưa gồm cổ tức/phí giao dịch. “—” nghĩa là quỹ chưa đủ tuổi cho kỳ đó.</p>
    </div>

    @if(count($peers))
        <div class="fd-card fd-table-card">
            <h2 class="fd-h2" style="padding:1.2rem 1.4rem 0;"><i class="bi bi-intersect"></i> Các quỹ cùng bám {{ $row['index'] }}</h2>
            <div class="table-responsive">
                <table class="fd-table etf-table">
                    <thead>
                        <tr><th>Quỹ</th><th class="num">Giá (₫)</th><th class="num">GTGD TB 20p</th><th class="num">1 năm</th><th class="num">3 năm</th></tr>
                    </thead>
                    <tbody>
                        @foreach(array_merge([$row], $peers) as $p)
                            <tr @if($p['symbol'] === $etf->symbol) style="background:var(--light-blue);" @endif>
                                <td>
                                    <a class="fd-code" href="{{ route('etf.show', $p['symbol']) }}">{{ $p['symbol'] }}</a>
                                    <span class="fd-fullname">{{ $p['manager'] ?? $p['short'] }}</span>
                                </td>
                                <td class="num">{{ F::number($p['price']) }}</td>
                                <td class="num">{{ $p['avg_value'] !== null ? F::bigMoney($p['avg_value']) : '—' }}</td>
                                <td class="num"><span class="fd-pill {{ F::trendClass($p['r1y']) }}">{{ F::percent($p['r1y'], 2, true) }}</span></td>
                                <td class="num"><span class="fd-pill {{ F::trendClass($p['r3y']) }}">{{ F::percent($p['r3y'], 2, true) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="fd-footnote" style="padding:0 1.4rem;">Các quỹ bám cùng một chỉ số nên hiệu suất gần nhau; khác biệt nhỏ chủ yếu do phí và sai số bám sát. Thanh khoản cao hơn nghĩa là mua bán dễ hơn.</p>
        </div>
    @endif

    <p class="fd-footnote">Nguồn: KBS qua vnstock. Chưa có NAV/iNAV, danh mục nắm giữ và phí quản lý của ETF. Hiệu suất quá khứ không đảm bảo kết quả tương lai; đây không phải lời khuyên đầu tư.</p>
</div>
</div>
@endsection

@section('scripts')
<script>window.__ETF_DETAIL__ = { symbol: @json($etf->symbol), series: @json($series) };</script>
<script>window.__WATCH__ = @json($watch);</script>
@vite('resources/frontend/js/shared/watchlist-init.js')
@vite('resources/frontend/js/etf/show.js')
@endsection

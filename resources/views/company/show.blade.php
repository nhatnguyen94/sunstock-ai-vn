@extends('layouts.app')

@section('title', $symbol . ' – Hồ sơ công ty | Sun Stock AI')

@section('head')
@vite(['resources/frontend/css/company/show.css', 'resources/frontend/css/shared/charts.css'])
@endsection

@use('App\Support\VnFormat', 'F')

@php
    $o = $company['overview'] ?? [];
    $displayName = $o['name'] ?? $o['short_name'] ?? $symbol;
    $errorLabels = [
        'kbs_overview' => 'thông tin chung', 'kbs_ownership' => 'cơ cấu sở hữu', 'kbs_shareholders' => 'cổ đông',
        'kbs_officers' => 'ban lãnh đạo', 'kbs_subsidiaries' => 'công ty con/liên kết',
        'vci_overview' => 'định giá & khuyến nghị', 'vci_shareholders' => 'danh sách cổ đông',
        'vci_officers' => 'sở hữu của lãnh đạo', 'vci_events' => 'sự kiện doanh nghiệp',
    ];
@endphp

@section('content')
<section class="cp-header">
    <div class="container">
        <div class="row align-items-center cp-header-row">
            <div class="col-lg-8">
                <div class="cp-badge"><i class="bi bi-building"></i> Hồ sơ doanh nghiệp</div>
                <h1 class="cp-title">
                    <span class="cp-symbol">{{ $symbol }}</span>
                    @if($company) <span class="cp-name">{{ $displayName }}</span> @endif
                </h1>
                @if($company)
                <div class="cp-chips">
                    @if(!empty($o['exchange'])) <span class="cp-chip"><i class="bi bi-bank"></i> {{ $o['exchange'] }}</span> @endif
                    @if(!empty($o['sector'])) <span class="cp-chip"><i class="bi bi-diagram-3"></i> {{ $o['sector'] }}</span> @endif
                    @if(!empty($o['rating']))
                        <span class="cp-chip cp-chip-rating cp-rating-{{ strtolower($o['rating']) }}" title="Khuyến nghị của nhà phân tích ({{ $o['rating_as_of'] ?? '' }})">
                            <i class="bi bi-award"></i> {{ $o['rating'] }}
                        </span>
                    @endif
                </div>
                @else
                <p class="cp-subtitle">Cổ đông, ban lãnh đạo, công ty con – công ty liên kết và sự kiện doanh nghiệp</p>
                @endif
            </div>
            <div class="col-lg-4 text-lg-right cp-header-actions">
                <a href="{{ url('/stock?symbol=' . $symbol) }}" class="cp-btn"><i class="bi bi-graph-up-arrow"></i> Giá &amp; biểu đồ</a>
                <a href="{{ url('/stock/compare?symbols=' . $symbol) }}" class="cp-btn"><i class="bi bi-bar-chart-steps"></i> So sánh</a>
            </div>
        </div>
    </div>
</section>

<div class="cp-main">
<div class="container">

@if(! $company)
    {{-- ── No cached profile: loader shell (first visit) or "not found" ── --}}
    <div class="cp-card cp-state" id="cpState" data-symbol="{{ $symbol }}"
         data-load-url="{{ route('company.load', $symbol) }}" data-not-found="{{ $notFound ? '1' : '0' }}">
        @if($notFound)
            <i class="bi bi-search cp-state-icon"></i>
            <h3>Không tìm thấy thông tin cho mã {{ $symbol }}</h3>
            <p>Mã này không phải cổ phiếu niêm yết (ví dụ chứng chỉ quỹ ETF) hoặc chưa có dữ liệu doanh nghiệp.</p>
            <a class="cp-btn cp-btn-solid" href="{{ url('/stock') }}"><i class="bi bi-search"></i> Tra cứu mã khác</a>
        @else
            <div class="cp-spinner" id="cpSpinner"></div>
            <h3 id="cpStateTitle">Đang tải hồ sơ {{ $symbol }} lần đầu…</h3>
            <p id="cpStateText">Đang lấy dữ liệu cổ đông, ban lãnh đạo và sự kiện từ nguồn (khoảng 5 giây). Những lần sau trang sẽ mở ngay lập tức.</p>
            <button type="button" class="cp-btn cp-btn-solid d-none" id="cpRetry"><i class="bi bi-arrow-clockwise"></i> Thử lại</button>
        @endif
    </div>
@else
    {{-- ── Freshness bar ── --}}
    <div class="cp-fresh" id="cpFresh" data-symbol="{{ $symbol }}" data-load-url="{{ route('company.load', $symbol) }}">
        <span>
            <i class="bi bi-clock-history"></i>
            Cập nhật {{ $company['synced_at']?->format('d/m/Y H:i') }}
            @if($company['stale']) · <em>đang làm mới ngầm</em> @endif
        </span>
        <button type="button" class="cp-link-btn" id="cpRefresh"><i class="bi bi-arrow-clockwise"></i> Làm mới</button>
    </div>

    @if(!empty($company['errors']))
        <div class="cp-warn">
            <i class="bi bi-exclamation-triangle"></i>
            Một số mục tạm thời chưa tải được:
            {{ collect($company['errors'])->keys()->map(fn ($k) => $errorLabels[$k] ?? $k)->unique()->implode(', ') }}.
            Bấm <strong>Làm mới</strong> sau ít phút để thử lại.
        </div>
    @endif

    {{-- ── KPI cards ── --}}
    <div class="cp-kpis">
        <div class="cp-kpi"><span class="cp-kpi-label">Vốn hóa</span><span class="cp-kpi-value">{{ F::bigMoney($o['market_cap'] ?? null) }}</span></div>
        <div class="cp-kpi"><span class="cp-kpi-label">Giá tham chiếu*</span><span class="cp-kpi-value">{{ F::number($o['current_price'] ?? null) }} ₫</span>
            @if(!empty($o['highest_price_1y']))<span class="cp-kpi-sub">52 tuần: {{ F::number($o['lowest_price_1y'] ?? null) }} – {{ F::number($o['highest_price_1y']) }}</span>@endif</div>
        <div class="cp-kpi"><span class="cp-kpi-label">Vốn điều lệ</span><span class="cp-kpi-value">{{ F::bigMoney($o['charter_capital'] ?? null) }}</span></div>
        <div class="cp-kpi"><span class="cp-kpi-label">CP lưu hành</span><span class="cp-kpi-value">{{ F::number($o['outstanding_shares'] ?? null) }}</span></div>
        <div class="cp-kpi"><span class="cp-kpi-label">Nhân sự</span><span class="cp-kpi-value">{{ F::number($o['employees'] ?? null) }}</span></div>
        <div class="cp-kpi"><span class="cp-kpi-label">Room ngoại</span>
            <span class="cp-kpi-value">{{ F::percent($o['foreign_percent'] ?? null, 1) }}</span>
            @if(!empty($o['foreign_max_percent']))<span class="cp-kpi-sub">tối đa {{ F::percent($o['foreign_max_percent'], 0) }}</span>@endif</div>
        <div class="cp-kpi"><span class="cp-kpi-label">Free float</span><span class="cp-kpi-value">{{ F::percent($o['free_float_percent'] ?? null, 0) }}</span></div>
        @if(!empty($o['target_price']))
        <div class="cp-kpi"><span class="cp-kpi-label">Giá mục tiêu</span><span class="cp-kpi-value">{{ F::number($o['target_price']) }} ₫</span>
            <span class="cp-kpi-sub {{ F::trendClass($o['upside_percent'] ?? null) }}">{{ F::percent($o['upside_percent'] ?? null, 1, true) }} so với giá</span></div>
        @endif
    </div>
    <p class="cp-footnote">* Giá và vốn hóa tại thời điểm cập nhật hồ sơ, không phải giá realtime. Xem giá mới nhất ở trang <a href="{{ url('/stock?symbol=' . $symbol) }}">Giá &amp; biểu đồ</a>.</p>

    {{-- ── Section nav ── --}}
    <nav class="cp-nav" id="cpNav">
        <a href="#gioi-thieu">Giới thiệu</a>
        <a href="#co-dong">Cổ đông</a>
        <a href="#lanh-dao">Ban lãnh đạo</a>
        <a href="#cong-ty-con">Công ty con &amp; liên kết</a>
        <a href="#su-kien">Sự kiện @if(count($company['upcoming']))<span class="cp-dot">{{ count($company['upcoming']) }}</span>@endif</a>
    </nav>

    {{-- ── Intro ── --}}
    <section class="cp-card" id="gioi-thieu">
        <h2 class="cp-h2"><i class="bi bi-info-circle"></i> Giới thiệu</h2>
        <div class="row">
            <div class="col-lg-8">
                @if(!empty($o['profile']))<p class="cp-text">{{ $o['profile'] }}</p>@endif
                @if(!empty($o['business_model']))
                    <h3 class="cp-h3">Lĩnh vực kinh doanh</h3>
                    <div class="cp-text cp-prewrap cp-collapsible" id="cpBiz">{{ trim($o['business_model']) }}</div>
                @endif
                @if(!empty($o['history']))
                    <h3 class="cp-h3">Lịch sử hình thành</h3>
                    <div class="cp-text cp-prewrap cp-collapsible" id="cpHistory">{{ trim($o['history']) }}</div>
                @endif
            </div>
            <div class="col-lg-4">
                <dl class="cp-dl">
                    <dt>Loại hình</dt><dd>{{ $o['company_type'] ?? '—' }}</dd>
                    <dt>Ngày thành lập</dt><dd>{{ $o['founded_date'] ?? '—' }}</dd>
                    <dt>Ngày niêm yết</dt><dd>{{ F::date($o['listing_date'] ?? null) }}</dd>
                    <dt>Mệnh giá</dt><dd>{{ F::number($o['par_value'] ?? null) }} ₫</dd>
                    <dt>Mã số thuế</dt><dd>{{ $o['tax_id'] ?? '—' }}</dd>
                    <dt>Kiểm toán</dt><dd>{{ $o['auditor'] ?? '—' }}</dd>
                    <dt>Người đại diện</dt><dd>{{ $o['ceo_name'] ?? '—' }}@if(!empty($o['ceo_position'])) <small>({{ $o['ceo_position'] }})</small>@endif</dd>
                    <dt>Địa chỉ</dt><dd>{{ $o['address'] ?? '—' }}</dd>
                    <dt>Điện thoại</dt><dd>{{ $o['phone'] ?? '—' }}</dd>
                    <dt>Email</dt><dd>{{ $o['email'] ?? '—' }}</dd>
                    <dt>Website</dt>
                    <dd>@if(!empty($o['website']) && preg_match('#^https?://#i', $o['website']))
                            <a href="{{ $o['website'] }}" target="_blank" rel="noopener noreferrer">{{ $o['website'] }}</a>
                        @else {{ $o['website'] ?? '—' }} @endif</dd>
                </dl>
            </div>
        </div>
    </section>

    {{-- ── Shareholders ── --}}
    <section class="cp-card" id="co-dong">
        <h2 class="cp-h2"><i class="bi bi-pie-chart"></i> Cơ cấu cổ đông</h2>
        <div class="row">
            <div class="col-lg-6 mb-3">
                <h3 class="cp-h3 text-center">Cơ cấu sở hữu</h3>
                @if(count($company['ownership_chart']['series']))
                    <div id="cpOwnershipChart" class="cp-chart"></div>
                @else <p class="cp-empty">Chưa có dữ liệu cơ cấu sở hữu.</p> @endif
            </div>
            <div class="col-lg-6 mb-3">
                <h3 class="cp-h3 text-center">Cổ đông lớn</h3>
                @if(count($company['holders_chart']['series']))
                    <div id="cpHoldersChart" class="cp-chart"></div>
                @else <p class="cp-empty">Chưa có dữ liệu cổ đông lớn.</p> @endif
            </div>
        </div>
        @if(count($company['shareholders']))
        <div class="table-responsive">
            <table class="cp-table">
                <thead><tr><th>#</th><th>Cổ đông</th><th class="num">Số cổ phiếu</th><th class="num">Tỷ lệ</th><th>Cập nhật</th></tr></thead>
                <tbody>
                @foreach($company['shareholders'] as $i => $s)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $s['name'] }}</td>
                        <td class="num">{{ F::number($s['shares'] ?? null) }}</td>
                        <td class="num"><span class="cp-bar"><span style="width: {{ min(100, $s['percent']) }}%"></span></span> {{ F::percent($s['percent']) }}</td>
                        <td>{{ F::date($s['date'] ?? null) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </section>

    {{-- ── Officers ── --}}
    <section class="cp-card" id="lanh-dao">
        <h2 class="cp-h2"><i class="bi bi-people"></i> Ban lãnh đạo</h2>
        @php
            $groups = ['board' => ['Hội đồng quản trị', 'bi-bank2'], 'executive' => ['Ban điều hành', 'bi-briefcase'], 'supervisory' => ['Ban kiểm soát', 'bi-shield-check']];
            $any = false;
        @endphp
        <div class="row">
        @foreach($groups as $key => [$label, $icon])
            @if(count($company['officers'][$key] ?? []))
                @php $any = true; @endphp
                <div class="col-lg-4 mb-3">
                    <h3 class="cp-h3"><i class="bi {{ $icon }}"></i> {{ $label }}</h3>
                    <ul class="cp-people">
                    @foreach($company['officers'][$key] as $p)
                        <li>
                            <span class="cp-avatar">{{ mb_strtoupper(mb_substr(collect(explode(' ', trim($p['name'])))->last() ?? '?', 0, 1)) }}</span>
                            <span class="cp-person">
                                <strong>{{ $p['name'] }}</strong>
                                <small>{{ $p['position'] }}@if($p['independent']) · <em>độc lập</em>@endif @if($p['since']) · từ {{ $p['since'] }}@endif</small>
                                @if(!empty($p['own_percent']))
                                    <small class="cp-own">Sở hữu {{ F::percent($p['own_percent']) }} ({{ F::number($p['own_quantity'] ?? null) }} CP)</small>
                                @endif
                            </span>
                        </li>
                    @endforeach
                    </ul>
                </div>
            @endif
        @endforeach
        </div>
        @unless($any) <p class="cp-empty">Chưa có dữ liệu ban lãnh đạo.</p> @endunless
    </section>

    {{-- ── Subsidiaries / affiliates ── --}}
    <section class="cp-card" id="cong-ty-con">
        <h2 class="cp-h2"><i class="bi bi-diagram-2"></i> Công ty con &amp; công ty liên kết</h2>
        @foreach([['Công ty con', $company['subsidiaries']], ['Công ty liên kết', $company['affiliates']]] as [$label, $list])
            <h3 class="cp-h3">{{ $label }} <span class="cp-count">{{ count($list) }}</span></h3>
            @if(count($list))
            <div class="table-responsive mb-3">
                <table class="cp-table">
                    <thead><tr><th>Tên công ty</th><th class="num">Vốn điều lệ</th><th class="num">Tỷ lệ sở hữu</th></tr></thead>
                    <tbody>
                    @foreach($list as $c)
                        <tr>
                            <td>{{ $c['name'] }}</td>
                            <td class="num">{{ F::bigMoney($c['charter_capital'] ?? null) }}</td>
                            <td class="num"><span class="cp-bar"><span style="width: {{ min(100, $c['percent'] ?? 0) }}%"></span></span> {{ F::percent($c['percent'] ?? null, 1) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @else <p class="cp-empty">Không có dữ liệu.</p> @endif
        @endforeach
    </section>

    {{-- ── Events ── --}}
    <section class="cp-card" id="su-kien">
        <h2 class="cp-h2"><i class="bi bi-calendar-event"></i> Sự kiện doanh nghiệp</h2>
        @php
            $tabs = [
                'upcoming' => ['Sắp diễn ra', $company['upcoming']],
                'dividends' => [$company['event_labels']['DIVIDEND'], $company['dividends']],
                'meetings' => [$company['event_labels']['SHAREHOLDER_MEETING'], $company['meetings']],
                'insider' => [$company['event_labels']['MAJOR_SHAREHOLDER_TRADING'], $company['insider_trades']],
                'other' => [$company['event_labels']['OTHER'], $company['other_events']],
            ];
            $firstTab = count($company['upcoming']) ? 'upcoming' : 'dividends';
        @endphp
        <ul class="cp-tabs" id="cpEventTabs">
            @foreach($tabs as $key => [$label, $list])
                <li><button type="button" data-tab="{{ $key }}" class="{{ $key === $firstTab ? 'active' : '' }}">{{ $label }} <span class="cp-count">{{ count($list) }}</span></button></li>
            @endforeach
        </ul>
        @foreach($tabs as $key => [$label, $list])
            <div class="cp-tabpane {{ $key === $firstTab ? 'active' : '' }}" data-pane="{{ $key }}">
                @forelse($list as $e)
                    <div class="cp-event">
                        <div class="cp-event-date">
                            <strong>{{ F::date($e['exright_date'] ?? $e['record_date'] ?? $e['issue_date'] ?? $e['public_date'] ?? null) }}</strong>
                            <small>{{ ($e['exright_date'] ?? null) ? 'GDKHQ' : (($e['record_date'] ?? null) ? 'Chốt DS' : 'Công bố') }}</small>
                        </div>
                        <div class="cp-event-body">
                            <strong>
                                @if(($e['side'] ?? null))<span class="cp-side cp-side-{{ $e['side'] === 'Mua' ? 'buy' : 'sell' }}">{{ $e['side'] }}</span>@endif
                                {{ $e['title'] }}
                            </strong>
                            <small>
                                {{ $e['name'] ?? '' }}
                                @if(!empty($e['public_date'])) · công bố {{ F::date($e['public_date']) }}@endif
                                @if(!empty($e['record_date'])) · chốt DS {{ F::date($e['record_date']) }}@endif
                                @if(!empty($e['payout_date'])) · thanh toán {{ F::date($e['payout_date']) }}@endif
                            </small>
                        </div>
                    </div>
                @empty
                    <p class="cp-empty">Không có sự kiện trong mục này.</p>
                @endforelse
            </div>
        @endforeach
    </section>
@endif

</div>
</div>
@endsection

@section('scripts')
@if($company)
@php $pageData = ['symbol' => $symbol, 'ownership' => $company['ownership_chart'], 'holders' => $company['holders_chart']]; @endphp
<script>window.__COMPANY__ = @json($pageData);</script>
@endif
@vite('resources/frontend/js/company/show.js')
@endsection

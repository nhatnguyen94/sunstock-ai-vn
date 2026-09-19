@extends('layouts.app')

@section('title', 'Quỹ mở – So sánh hiệu suất | Sun Stock AI')

@section('head')
@vite('resources/frontend/css/funds/funds.css')
@endsection

@use('App\Support\VnFormat', 'F')
@use('App\Models\Fund')

@php
    $active = array_filter($filters, fn ($v, $k) => $v !== null && ! in_array($k, ['sort', 'dir'], true), ARRAY_FILTER_USE_BOTH);
    $sortLink = function (string $key) use ($filters, $active) {
        $dir = ($filters['sort'] === $key && $filters['dir'] === 'desc') ? 'asc' : 'desc';
        // Names read best A→Z first; every numeric column reads best high→low first.
        if ($key === 'short_name' && $filters['sort'] !== $key) { $dir = 'asc'; }
        return route('funds.index', $active + ['sort' => $key, 'dir' => $dir]);
    };
    $arrow = fn (string $key) => $filters['sort'] === $key ? ($filters['dir'] === 'asc' ? '↑' : '↓') : '';
    $sortKeep = ['sort' => $filters['sort'], 'dir' => $filters['dir']];
    $columns = [
        'nav_change_1m' => '1 tháng', 'nav_change_3m' => '3 tháng', 'nav_change_6m' => '6 tháng',
        'nav_change_12m' => '1 năm', 'nav_change_36m_annualized' => '3 năm/năm',
    ];
@endphp

@section('content')
<section class="fd-header">
    <div class="container">
        <div class="row align-items-center fd-header-row">
            <div class="col-md-8">
                <div class="fd-badge"><i class="bi bi-pie-chart"></i> Dữ liệu Fmarket · {{ $total }} quỹ mở</div>
                <h1 class="fd-title"><i class="bi bi-collection"></i> Quỹ mở</h1>
                <p class="fd-subtitle">So sánh hiệu suất, phí quản lý và danh mục của các quỹ đầu tư mở tại Việt Nam</p>
            </div>
            <div class="col-md-4 text-md-right fd-header-actions">
                <a href="{{ route('funds.compare') }}" class="fd-btn"><i class="bi bi-intersect"></i> So sánh quỹ</a>
            </div>
        </div>
    </div>
</section>

<div class="fd-main">
<div class="container">

    @if($error && $total === 0)
        <div class="fd-card fd-state">
            <i class="bi bi-cloud-slash fd-state-icon"></i>
            <h3>Chưa tải được danh sách quỹ</h3>
            <p>{{ $error }}</p>
            <a class="fd-btn fd-btn-solid" href="{{ route('funds.index') }}"><i class="bi bi-arrow-clockwise"></i> Thử lại</a>
        </div>
    @else

    {{-- ── Type cards (double as filter tabs) ── --}}
    <div class="fd-types">
        <a href="{{ route('funds.index', array_diff_key($active, ['type' => 1]) + $sortKeep) }}" class="fd-type {{ $filters['type'] === null ? 'active' : '' }}">
            <span class="fd-type-name">Tất cả</span>
            <span class="fd-type-count">{{ $total }}</span>
            <span class="fd-type-meta">quỹ mở</span>
        </a>
        @foreach(Fund::TYPE_LABELS as $code => $label)
            @continue(empty($counts[$code]))
            @php $st = $type_stats[$code] ?? null; @endphp
            <a href="{{ route('funds.index', array_merge($active, ['type' => $code]) + $sortKeep) }}" class="fd-type {{ $filters['type'] === $code ? 'active' : '' }}">
                <span class="fd-type-name">{{ $label }}</span>
                <span class="fd-type-count">{{ $counts[$code] }}</span>
                <span class="fd-type-meta">
                    TB 1 năm <strong class="{{ F::trendClass($st['avg_12m'] ?? null) }}">{{ F::percent($st['avg_12m'] ?? null, 1, true) }}</strong>
                    · phí {{ F::percent($st['avg_fee'] ?? null, 2) }}
                </span>
            </a>
        @endforeach
    </div>

    {{-- ── Filters ── --}}
    <form method="GET" action="{{ route('funds.index') }}" class="fd-card fd-filter">
        @if($filters['type'])<input type="hidden" name="type" value="{{ $filters['type'] }}">@endif
        <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
        <input type="hidden" name="dir" value="{{ $filters['dir'] }}">
        <div class="form-row align-items-end">
            <div class="col-md-5 form-group mb-md-0">
                <label for="fdQ">Tìm quỹ</label>
                <input type="text" id="fdQ" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="Mã hoặc tên quỹ, ví dụ: DCDS, Bảo Việt…" maxlength="60">
            </div>
            <div class="col-md-4 form-group mb-md-0">
                <label for="fdOwner">Công ty quản lý</label>
                <select id="fdOwner" name="owner" class="form-control">
                    <option value="">Tất cả</option>
                    @foreach($owners as $owner)
                        <option value="{{ $owner }}" @selected($filters['owner'] === $owner)>{{ \Illuminate\Support\Str::of($owner)->replaceMatches('/^(CÔNG TY (TNHH|CỔ PHẦN|CP) )?(QUẢN LÝ QUỸ )?/iu', '')->title() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 form-group mb-0 fd-filter-actions">
                <button type="submit" class="fd-btn fd-btn-solid"><i class="bi bi-search"></i> Lọc</button>
                <a href="{{ route('funds.index') }}" class="fd-btn fd-btn-ghost"><i class="bi bi-x-circle"></i> Xóa</a>
            </div>
        </div>
    </form>

    <div class="fd-meta">
        <span><strong>{{ count($funds) }}</strong> quỹ phù hợp</span>
        <span>
            @if($synced_at) <i class="bi bi-clock-history"></i> NAV cập nhật {{ $synced_at->format('d/m/Y H:i') }} @endif
        </span>
    </div>

    {{-- ── Table ── --}}
    <div class="fd-card fd-table-card">
        <div class="table-responsive">
            <table class="fd-table" id="fdTable">
                <thead>
                    <tr>
                        <th class="fd-check" title="Chọn tối đa {{ \App\Frontend\Services\FundService::MAX_COMPARE }} quỹ để so sánh"><i class="bi bi-intersect"></i></th>
                        <th><a href="{{ $sortLink('short_name') }}">Quỹ <span>{{ $arrow('short_name') }}</span></a></th>
                        <th>Loại</th>
                        <th class="num"><a href="{{ $sortLink('nav') }}">NAV/CCQ <span>{{ $arrow('nav') }}</span></a></th>
                        <th class="num"><a href="{{ $sortLink('management_fee') }}">Phí QL <span>{{ $arrow('management_fee') }}</span></a></th>
                        @foreach($columns as $key => $label)
                            <th class="num"><a href="{{ $sortLink($key) }}">{{ $label }} <span>{{ $arrow($key) }}</span></a></th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                @forelse($funds as $f)
                    <tr>
                        <td class="fd-check"><input type="checkbox" class="fd-pick" value="{{ $f->short_name }}" aria-label="Chọn {{ $f->short_name }} để so sánh"></td>
                        <td>
                            <a class="fd-code" href="{{ route('funds.show', $f->short_name) }}">{{ $f->short_name }}</a>
                            <span class="fd-fullname">{{ \Illuminate\Support\Str::title(mb_strtolower(preg_replace('/^QUỸ ĐẦU TƯ /iu', '', $f->name))) }}</span>
                        </td>
                        <td><span class="fd-type-pill fd-tp-{{ strtolower($f->type_code) }}">{{ $f->type_label }}</span></td>
                        <td class="num">{{ F::number($f->nav) }}</td>
                        <td class="num">{{ F::percent($f->management_fee, 2) }}</td>
                        @foreach($columns as $key => $label)
                            <td class="num"><span class="fd-pill {{ F::trendClass($f->$key) }}">{{ F::percent($f->$key, 2, true) }}</span></td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ 5 + count($columns) }}" class="fd-empty">Không có quỹ nào khớp bộ lọc.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="fd-footnote">Lợi suất là mức thay đổi NAV đã trừ phí quản lý; “—” nghĩa là quỹ chưa đủ tuổi cho kỳ đó. Hiệu suất quá khứ không đảm bảo kết quả tương lai — đây là thông tin tham khảo, không phải khuyến nghị đầu tư.</p>
    @endif

</div>
</div>

{{-- Compare bar --}}
<div class="fd-compare-bar" id="fdCompareBar" hidden>
    <div class="container d-flex align-items-center justify-content-between flex-wrap" style="gap:.75rem;">
        <span><strong id="fdPickCount">0</strong> quỹ đã chọn <small id="fdPickList"></small></span>
        <span>
            <button type="button" class="fd-btn fd-btn-ghost" id="fdPickClear">Bỏ chọn</button>
            <a href="{{ route('funds.compare') }}" class="fd-btn fd-btn-solid" id="fdCompareGo"><i class="bi bi-intersect"></i> So sánh</a>
        </span>
    </div>
</div>
@endsection

@section('scripts')
<script>window.__FUNDS__ = { max: {{ \App\Frontend\Services\FundService::MAX_COMPARE }}, compareUrl: @json(route('funds.compare')) };</script>
@vite('resources/frontend/js/funds/index.js')
@endsection

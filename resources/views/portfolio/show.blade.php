@extends('layouts.app')

@section('title', $portfolio->name . ' – Danh mục đầu tư | Sun Stock AI')

@section('head')
@vite(['resources/frontend/css/portfolio/portfolio.css', 'resources/frontend/css/shared/charts.css'])
@endsection

@use('App\Support\VnFormat', 'F')

@php
    $up = $stats['is_positive'];
    $pageData = [
        'refreshUrl'  => route('portfolio.update-prices', $portfolio->id, false),
        'itemUrl'     => '/portfolio/item',
        'performance' => $performance,
        'allocation'  => [
            'labels' => array_column($allocation, 'symbol'),
            'series' => array_map(fn ($a) => round($a['percent'], 2), $allocation),
        ],
    ];
    $stale = $price_info['as_of'] && \Carbon\Carbon::parse($price_info['as_of'])->diffInDays(now()) > 4;
    $eventLabel = ['DIVIDEND' => 'Cổ tức / phát hành', 'SHAREHOLDER_MEETING' => 'Đại hội cổ đông'];
@endphp

@section('content')
<div class="pf-page">
<div class="container">

    {{-- ── Header ── --}}
    <div class="pf-head">
        <div>
            <div class="pf-crumbs">
                <a href="{{ route('portfolio.index') }}">Danh mục đầu tư</a><span aria-hidden="true">/</span><span>{{ $portfolio->name }}</span>
            </div>
            <h1 class="pf-title">
                <i class="bi bi-briefcase"></i> {{ $portfolio->name }}
                @unless($portfolio->is_active) <span class="pf-chip">Tạm dừng</span> @endunless
            </h1>
            @if($portfolio->description)<p class="pf-sub">{{ $portfolio->description }}</p>@endif
            <p class="pf-sub">
                @if($price_info['as_of'])
                    <i class="bi bi-clock-history"></i> Giá phiên <strong>{{ F::date($price_info['as_of']) }}</strong>
                    @if($stale) <span class="pf-pill warn">dữ liệu cũ</span> @endif
                @else
                    <i class="bi bi-hourglass-split"></i> Chưa có dữ liệu giá cho danh mục này
                @endif
            </p>
        </div>

        <div class="pf-actions">
            <button type="button" class="pf-btn pf-btn-soft" id="pfRefresh" title="Lấy giá đóng cửa mới nhất cho các mã trong danh mục">
                <i class="bi bi-arrow-repeat"></i><span class="pf-spin"></span> Làm mới giá
            </button>
            <a href="{{ route('portfolio.add-stock', $portfolio->id) }}" class="pf-btn pf-btn-primary"><i class="bi bi-plus-lg"></i> Thêm cổ phiếu</a>
            <div class="pf-menu" id="pfMenu">
                <button type="button" class="pf-btn pf-btn-ghost pf-btn-icon" id="pfMenuBtn" aria-haspopup="true" aria-expanded="false" aria-label="Thêm tùy chọn"><i class="bi bi-three-dots"></i></button>
                <div class="pf-menu-list" role="menu">
                    <a href="{{ route('portfolio.edit', $portfolio->id) }}"><i class="bi bi-pencil"></i> Chỉnh sửa danh mục</a>
                    <a href="{{ route('portfolio.export', $portfolio->id) }}"><i class="bi bi-filetype-csv"></i> Xuất CSV</a>
                    <hr>
                    <button type="button" class="danger" data-toggle="modal" data-target="#pfDeletePortfolio"><i class="bi bi-trash"></i> Xóa danh mục</button>
                </div>
            </div>
        </div>
    </div>

    @if(session('info'))<div class="pf-alert info"><i class="bi bi-info-circle"></i><div>{{ session('info') }}</div></div>@endif
    @if(session('success'))<div class="pf-alert good"><i class="bi bi-check-circle"></i><div>{{ session('success') }}</div></div>@endif
    @if($errors->any())<div class="pf-alert bad"><i class="bi bi-exclamation-triangle"></i><div>{{ $errors->first() }}</div></div>@endif

    @if($price_info['missing'])
        <div class="pf-alert warn">
            <i class="bi bi-cloud-download"></i>
            <div><strong>Đang chờ dữ liệu giá cho {{ implode(', ', $price_info['missing']) }}</strong>
                <span>{{ $price_info['queued'] ? 'Đã đưa vào hàng đợi tải dữ liệu — quay lại sau vài phút hoặc bấm “Làm mới giá”.' : 'Các mã này chưa có giá nên tạm tính theo giá mua.' }}</span></div>
        </div>
    @endif

    {{-- ── KPIs ── --}}
    <div class="pf-kpis">
        <div class="pf-kpi">
            <div class="pf-kpi-label">Giá trị hiện tại</div>
            <div class="pf-kpi-value">{{ F::number($stats['current_value']) }}₫</div>
            <div class="pf-kpi-sub">
                Hôm nay
                <span class="{{ $today['is_positive'] ? 'up' : 'down' }}"><strong>{{ $today['value'] >= 0 ? '+' : '' }}{{ F::number($today['value']) }}₫ ({{ F::percent($today['percent'], 2, true) }})</strong></span>
            </div>
        </div>
        <div class="pf-kpi">
            <div class="pf-kpi-label">Vốn đầu tư</div>
            <div class="pf-kpi-value">{{ F::number($stats['total_invested']) }}₫</div>
            <div class="pf-kpi-sub">{{ $stats['total_items'] }} mã cổ phiếu</div>
        </div>
        <div class="pf-kpi {{ $up ? 'up' : 'down' }}">
            <div class="pf-kpi-label">Lãi / Lỗ</div>
            <div class="pf-kpi-value {{ $up ? 'up' : 'down' }}">{{ $up ? '+' : '' }}{{ F::number($stats['profit_loss']) }}₫</div>
            <div class="pf-kpi-sub"><span class="{{ $up ? 'up' : 'down' }}"><strong>{{ F::percent($stats['profit_loss_percent'], 2, true) }}</strong></span> so với vốn</div>
        </div>
        <div class="pf-kpi">
            <div class="pf-kpi-label">Mã nổi bật</div>
            @if($best)
                <div class="pf-kpi-sub" style="margin-top:.35rem">
                    <span class="up">▲ <strong>{{ $best['symbol'] }}</strong> {{ F::percent($best['pnl_percent'], 1, true) }}</span>
                    @if($worst)<br><span class="down">▼ <strong>{{ $worst['symbol'] }}</strong> {{ F::percent($worst['pnl_percent'], 1, true) }}</span>@endif
                </div>
            @else
                <div class="pf-kpi-sub" style="margin-top:.35rem">Thêm cổ phiếu để xem mã tốt nhất / kém nhất.</div>
            @endif
        </div>
    </div>

    {{-- ── Target / stop-loss alerts ── --}}
    @if($alerts['targets_reached'] > 0)
        <div class="pf-alert good"><i class="bi bi-bullseye"></i><div><strong>{{ $alerts['targets_reached'] }} cổ phiếu đã đạt giá mục tiêu</strong><span>Cân nhắc chốt lời một phần.</span></div></div>
    @endif
    @if($alerts['stop_losses_hit'] > 0)
        <div class="pf-alert bad"><i class="bi bi-exclamation-octagon"></i><div><strong>{{ $alerts['stop_losses_hit'] }} cổ phiếu chạm giá cắt lỗ</strong><span>Xem lại kế hoạch của bạn cho các mã được đánh dấu bên dưới.</span></div></div>
    @endif

    @if(count($holdings) > 0)
        {{-- ── Performance + allocation ── --}}
        <div class="row">
            <div class="col-lg-8">
                <div class="pf-card">
                    <div class="pf-card-head">
                        <h2 class="pf-card-title"><i class="bi bi-graph-up-arrow"></i> Hiệu suất danh mục</h2>
                        <div class="pf-legend-inline"><span><i style="background:#2563eb"></i>Giá trị</span><span><i style="background:#94a3b8"></i>Vốn đã bỏ ra</span></div>
                    </div>
                    <div class="pf-card-body">
                        <div id="pfPerfChart" class="pf-chart"></div>
                        <p class="pf-hint">Dựng lại từ giá đóng cửa hằng ngày và ngày mua của từng mã, giả định số lượng giữ nguyên từ ngày mua.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="pf-card">
                    <div class="pf-card-head"><h2 class="pf-card-title"><i class="bi bi-pie-chart"></i> Tỷ trọng</h2></div>
                    <div class="pf-card-body"><div id="pfAllocChart"></div></div>
                </div>
            </div>
        </div>

        {{-- ── Holdings ── --}}
        <div class="pf-card">
            <div class="pf-card-head">
                <h2 class="pf-card-title"><i class="bi bi-list-ul"></i> Danh sách cổ phiếu <small>({{ count($holdings) }} mã)</small></h2>
            </div>
            <div class="table-responsive">
                <table class="pf-table">
                    <thead>
                        <tr>
                            <th>Mã</th><th class="num">SL</th><th class="num">Giá vốn</th><th class="num">Giá hiện tại</th>
                            <th class="num">Giá trị</th><th class="num">Lãi / Lỗ</th><th class="num">Tỷ trọng</th><th>Mục tiêu / Cắt lỗ</th><th class="ctr">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($holdings as $h)
                        @php
                            $hUp = $h['pnl'] >= 0;
                            $dUp = ($h['day_change_percent'] ?? 0) >= 0;
                        @endphp
                        <tr>
                            <td>
                                <a class="pf-sym" href="{{ route('company.show', $h['symbol']) }}" title="Xem hồ sơ công ty">{{ $h['symbol'] }}</a>
                                @if($h['at_target'])<i class="bi bi-bullseye up pf-flag" title="Đạt giá mục tiêu"></i>@endif
                                @if($h['at_stop_loss'])<i class="bi bi-exclamation-triangle-fill down pf-flag" title="Chạm giá cắt lỗ"></i>@endif
                                <span class="pf-name" title="{{ $h['name'] }}">{{ $h['name'] }}</span>
                            </td>
                            <td class="num">{{ F::number($h['quantity']) }}</td>
                            <td class="num">{{ F::number($h['buy_price']) }}₫</td>
                            <td class="num">
                                <strong>{{ F::number($h['current_price']) }}₫</strong>
                                @if($h['day_change_percent'] !== null)
                                    <span class="pf-cell-sub {{ $dUp ? 'up' : 'down' }}">{{ F::percent($h['day_change_percent'], 2, true) }} hôm nay</span>
                                @elseif($h['price_date'] === null)
                                    <span class="pf-cell-sub">chưa có giá thị trường</span>
                                @endif
                            </td>
                            <td class="num"><strong>{{ F::number($h['value']) }}₫</strong></td>
                            <td class="num">
                                <span class="{{ $hUp ? 'up' : 'down' }}"><strong>{{ $hUp ? '+' : '' }}{{ F::number($h['pnl']) }}₫</strong></span>
                                <span class="pf-cell-sub {{ $hUp ? 'up' : 'down' }}">{{ F::percent($h['pnl_percent'], 2, true) }}</span>
                            </td>
                            <td class="num"><div class="pf-weight"><span class="pf-bar"><span style="width: {{ min(100, $h['weight']) }}%"></span></span>{{ F::percent($h['weight'], 1) }}</div></td>
                            <td>
                                @if($h['target_price'] || $h['stop_loss_price'])
                                    <div class="pf-levels">
                                        @if($h['target_price'])<span class="tp">▲ {{ F::number($h['target_price']) }}₫ <small>({{ F::percent(($h['target_price'] / max($h['current_price'], 1) - 1) * 100, 1, true) }})</small></span>@endif
                                        @if($h['stop_loss_price'])<span class="sl">▼ {{ F::number($h['stop_loss_price']) }}₫ <small>({{ F::percent(($h['stop_loss_price'] / max($h['current_price'], 1) - 1) * 100, 1, true) }})</small></span>@endif
                                    </div>
                                @else
                                    <span class="pf-cell-sub">—</span>
                                @endif
                            </td>
                            <td class="ctr">
                                <div class="pf-row-actions">
                                    <button type="button" class="pf-btn pf-btn-soft pf-btn-icon pf-edit" title="Chỉnh sửa {{ $h['symbol'] }}"
                                            data-id="{{ $h['id'] }}" data-symbol="{{ $h['symbol'] }}" data-quantity="{{ $h['quantity'] }}"
                                            data-buy="{{ round($h['buy_price']) }}" data-target="{{ $h['target_price'] ? round($h['target_price']) : '' }}"
                                            data-stop="{{ $h['stop_loss_price'] ? round($h['stop_loss_price']) : '' }}" data-notes="{{ $h['notes'] }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="pf-btn pf-btn-danger pf-btn-icon pf-del" title="Xóa {{ $h['symbol'] }}" data-id="{{ $h['id'] }}" data-symbol="{{ $h['symbol'] }}">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── Events + insights ── --}}
        <div class="row">
            <div class="col-lg-6">
                <div class="pf-card">
                    <div class="pf-card-head"><h2 class="pf-card-title"><i class="bi bi-calendar-event"></i> Lịch sự kiện sắp tới</h2></div>
                    <div class="pf-card-body">
                        @if(count($events))
                            <ul class="pf-list">
                                @foreach($events as $e)
                                <li>
                                    <div class="pf-date"><strong>{{ \Carbon\Carbon::parse($e['key_date'])->format('d') }}</strong><small>Th{{ \Carbon\Carbon::parse($e['key_date'])->format('m') }}</small></div>
                                    <div class="grow">
                                        <b><a class="pf-sym" href="{{ route('company.show', $e['symbol']) }}">{{ $e['symbol'] }}</a> · {{ $eventLabel[$e['category']] ?? 'Sự kiện' }}</b>
                                        <small>{{ $e['title'] }}</small>
                                    </div>
                                </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="pf-hint" style="margin:0">Chưa có cổ tức hay đại hội cổ đông sắp diễn ra cho các mã bạn đang giữ. Lịch này lấy từ hồ sơ công ty và tự cập nhật.</p>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="pf-card">
                    <div class="pf-card-head"><h2 class="pf-card-title"><i class="bi bi-lightbulb"></i> Gợi ý cho danh mục</h2></div>
                    <div class="pf-card-body">
                        @forelse($suggestions as $s)
                            <div class="pf-alert warn" style="margin-bottom:.6rem"><i class="bi bi-pie-chart"></i>
                                <div><strong>{{ $s['symbol'] }} chiếm {{ F::percent($s['current_percent'], 1) }} danh mục</strong><span>{{ $s['reason'] }} (gợi ý dưới {{ $s['suggested_percent'] }}%).</span></div></div>
                        @empty
                            <div class="pf-alert good" style="margin-bottom:.6rem"><i class="bi bi-check2-circle"></i><div><strong>Tỷ trọng cân đối</strong><span>Không có mã nào chiếm quá 30% danh mục.</span></div></div>
                        @endforelse
                        @if(count($holdings) < 3)
                            <div class="pf-alert info" style="margin-bottom:0"><i class="bi bi-diagram-3"></i><div><strong>Danh mục mới có {{ count($holdings) }} mã</strong><span>Đa dạng hóa qua nhiều ngành giúp giảm rủi ro. Xem <a href="{{ route('stock.screener') }}">Stock Screener</a> để tìm mã phù hợp.</span></div></div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="pf-card">
            <div class="pf-empty">
                <i class="bi bi-inbox big"></i>
                <h4>Chưa có cổ phiếu nào</h4>
                <p>Thêm cổ phiếu đầu tiên — chỉ cần chọn mã, số lượng và giá mua. Giá hiện tại, lãi/lỗ và biểu đồ hiệu suất được tính tự động.</p>
                <a href="{{ route('portfolio.add-stock', $portfolio->id) }}" class="pf-btn pf-btn-primary"><i class="bi bi-plus-lg"></i> Thêm cổ phiếu đầu tiên</a>
            </div>
        </div>
    @endif
</div>
</div>

{{-- ── Edit holding ── --}}
<div class="modal fade pf-modal" id="pfEditModal" tabindex="-1" role="dialog" aria-labelledby="pfEditTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form class="modal-content" method="POST" id="pfEditForm" action="#">
            @csrf @method('PUT')
            <div class="modal-header"><h5 class="modal-title" id="pfEditTitle">Chỉnh sửa <span id="pfEditSymbol"></span></h5><button type="button" class="close" data-dismiss="modal" aria-label="Đóng">&times;</button></div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-6"><div class="pf-field"><label for="pfEQty">Số lượng</label><input class="pf-input" type="number" min="1" step="1" id="pfEQty" name="quantity" required></div></div>
                    <div class="col-6"><div class="pf-field"><label for="pfEBuy">Giá mua bình quân <small>(₫)</small></label><input class="pf-input" type="number" min="100" step="any" id="pfEBuy" name="buy_price" required></div></div>
                    <div class="col-6"><div class="pf-field"><label for="pfETarget">Giá mục tiêu <small>(₫, tùy chọn)</small></label><input class="pf-input" type="number" min="100" step="any" id="pfETarget" name="target_price"></div></div>
                    <div class="col-6"><div class="pf-field"><label for="pfEStop">Giá cắt lỗ <small>(₫, tùy chọn)</small></label><input class="pf-input" type="number" min="100" step="any" id="pfEStop" name="stop_loss_price"></div></div>
                </div>
                <div class="pf-field mb-0"><label for="pfENotes">Ghi chú</label><textarea class="pf-input" id="pfENotes" name="notes" rows="2" maxlength="1000"></textarea></div>
                <p class="pf-hint">Khi giá chạm mục tiêu hoặc cắt lỗ, hệ thống gửi email cho bạn (một lần cho mỗi lần chạm).</p>
            </div>
            <div class="modal-footer"><button type="button" class="pf-btn pf-btn-ghost" data-dismiss="modal">Hủy</button><button type="submit" class="pf-btn pf-btn-primary"><i class="bi bi-check2"></i> Lưu thay đổi</button></div>
        </form>
    </div>
</div>

{{-- ── Delete holding ── --}}
<div class="modal fade pf-modal" id="pfDeleteModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
        <form class="modal-content" method="POST" id="pfDeleteForm" action="#">
            @csrf @method('DELETE')
            <div class="modal-body text-center">
                <i class="bi bi-trash3 down" style="font-size:2rem"></i>
                <h5 class="mt-2" style="font-weight:800">Xóa <span id="pfDelSymbol"></span> khỏi danh mục?</h5>
                <p class="pf-hint">Hành động này không thể hoàn tác.</p>
                <div class="d-flex justify-content-center" style="gap:.5rem"><button type="button" class="pf-btn pf-btn-ghost" data-dismiss="modal">Giữ lại</button><button type="submit" class="pf-btn pf-btn-danger-solid">Xóa</button></div>
            </div>
        </form>
    </div>
</div>

{{-- ── Delete portfolio ── --}}
<div class="modal fade pf-modal" id="pfDeletePortfolio" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
        <form class="modal-content" method="POST" action="{{ route('portfolio.destroy', $portfolio->id) }}">
            @csrf @method('DELETE')
            <div class="modal-body text-center">
                <i class="bi bi-exclamation-triangle down" style="font-size:2rem"></i>
                <h5 class="mt-2" style="font-weight:800">Xóa danh mục “{{ $portfolio->name }}”?</h5>
                <p class="pf-hint">Toàn bộ {{ count($holdings) }} mã trong danh mục sẽ bị xóa và không thể khôi phục.</p>
                <div class="d-flex justify-content-center" style="gap:.5rem"><button type="button" class="pf-btn pf-btn-ghost" data-dismiss="modal">Hủy</button><button type="submit" class="pf-btn pf-btn-danger-solid">Xóa danh mục</button></div>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>window.__PF__ = @json($pageData);</script>
@vite('resources/frontend/js/portfolio/show.js')
@endsection

@extends('layouts.app')

@section('title', 'Giá vàng hôm nay – SJC, BTMC, thế giới | Sun Stock AI')

@section('head')
@vite(['resources/frontend/css/gold/gold.css', 'resources/frontend/css/shared/charts.css'])
@endsection

@use('App\Support\VnFormat', 'F')

@php
    $tz = 'Asia/Ho_Chi_Minh';
    $headline ??= null; $synced_at ??= null; $stale ??= false; $world ??= null; $error ??= null; $sjc_uniform ??= false; $chart_options ??= [];
    $sjc_branches ??= collect(); $btmc_gold ??= collect(); $silver ??= collect();
    $h = $headline['quote'] ?? null;
    $ring = $btmc_gold->first(fn ($r) => str_contains(mb_strtoupper($r['quote']->product), 'NHẪN TRÒN'));
    $delta = function (?int $v) {
        if ($v === null) return '<span class="gd-delta flat">—</span>';
        if ($v === 0) return '<span class="gd-delta flat">không đổi</span>';
        return '<span class="gd-delta ' . ($v > 0 ? 'up' : 'down') . '">' . ($v > 0 ? '▲ +' : '▼ ') . number_format($v, 0, ',', '.') . '</span>';
    };
    $pageData = [
        'historyUrl' => '/gold/history',
        'refreshUrl' => route('gold.refresh', [], false),
        'options'    => collect($chart_options)->map(function ($o) use ($sjc_branches, $btmc_gold) {
            $q = $sjc_branches->firstWhere('id', $o['id']) ?? $btmc_gold->pluck('quote')->firstWhere('id', $o['id']);
            return $o + ['buy' => $q?->buy_price, 'sell' => $q?->sell_price];
        })->values(),
    ];
@endphp

@section('content')
<section class="gd-header">
    <div class="container">
        <div class="row align-items-center gd-header-row">
            <div class="col-lg-8">
                <div class="gd-badge"><i class="bi bi-coin"></i> SJC · Bảo Tín Minh Châu · Thế giới</div>
                <h1 class="gd-title">Giá vàng hôm nay</h1>
                <p class="gd-subtitle">
                    @if($synced_at)
                        Cập nhật {{ $synced_at->timezone($tz)->format('H:i d/m/Y') }}
                        @if($stale)<span class="gd-stale">· đang làm mới ngầm</span>@endif
                    @else Chưa có dữ liệu @endif
                </p>
            </div>
            <div class="col-lg-4 text-lg-right gd-header-actions">
                <button type="button" class="gd-btn" id="gdRefresh"><i class="bi bi-arrow-repeat"></i><span class="gd-spin"></span> Làm mới</button>
                <a href="{{ route('exchange-rate.index') }}" class="gd-btn"><i class="bi bi-currency-exchange"></i> Tỷ giá</a>
            </div>
        </div>
    </div>
</section>

<div class="gd-main">
<div class="container">

@if(! $has_data)
    <div class="gd-card gd-state">
        <i class="bi bi-cloud-slash gd-state-icon"></i>
        <h3>Chưa tải được giá vàng</h3>
        <p>{{ $error ?? 'Nguồn dữ liệu đang chậm. Vui lòng thử lại sau ít phút.' }}</p>
        <a class="gd-btn gd-btn-solid" href="{{ route('gold.index') }}"><i class="bi bi-arrow-clockwise"></i> Thử lại</a>
    </div>
@else

    {{-- ── Headline cards ── --}}
    <div class="gd-kpis">
        @if($h)
        <div class="gd-kpi gd-kpi-gold">
            <div class="gd-kpi-label">{{ $h->source === 'SJC' ? 'Vàng miếng SJC' : $h->display_name }}</div>
            <div class="gd-kpi-pair">
                <div><small>Mua vào</small><b class="gd-price" data-luong="{{ $h->buy_price }}">{{ F::number($h->buy_price) }}</b></div>
                <div><small>Bán ra</small><b class="gd-price" data-luong="{{ $h->sell_price }}">{{ F::number($h->sell_price) }}</b></div>
            </div>
            <div class="gd-kpi-foot"><span>Hôm nay {!! $delta($headline['sell_change']) !!}</span><span>Chênh mua-bán <b class="gd-price" data-luong="{{ $h->spread }}">{{ F::number($h->spread) }}</b></span></div>
            <div class="gd-unit">₫ / <span class="gd-unit-label">lượng</span></div>
        </div>
        @endif
        @if($ring)
        @php $r = $ring['quote']; @endphp
        <div class="gd-kpi">
            <div class="gd-kpi-label">Nhẫn tròn trơn BTMC</div>
            <div class="gd-kpi-pair">
                <div><small>Mua vào</small><b class="gd-price" data-luong="{{ $r->buy_price }}">{{ F::number($r->buy_price) }}</b></div>
                <div><small>Bán ra</small><b class="gd-price" data-luong="{{ $r->sell_price }}">{{ F::number($r->sell_price) }}</b></div>
            </div>
            <div class="gd-kpi-foot"><span>Hôm nay {!! $delta($ring['sell_change']) !!}</span><span>Chênh mua-bán <b class="gd-price" data-luong="{{ $r->spread }}">{{ F::number($r->spread) }}</b></span></div>
            <div class="gd-unit">₫ / <span class="gd-unit-label">lượng</span></div>
        </div>
        @endif
        @if($world)
        <div class="gd-kpi">
            <div class="gd-kpi-label">Vàng thế giới</div>
            <div class="gd-kpi-value">{{ F::number($world['usd_oz'], 0) }} <small>USD/oz</small></div>
            @if($world['vnd_luong'])
                <div class="gd-kpi-foot"><span>Quy đổi ≈ <b class="gd-price" data-luong="{{ $world['vnd_luong'] }}">{{ F::number($world['vnd_luong']) }}</b> ₫/<span class="gd-unit-label">lượng</span></span></div>
                <div class="gd-hint">Theo tỷ giá VCB bán ra {{ F::number($world['usd_vnd']) }}₫/USD</div>
            @endif
        </div>
        @endif
        @if($world && $world['premium_percent'] !== null)
        @php $pUp = $world['premium_percent'] >= 0; @endphp
        <div class="gd-kpi">
            <div class="gd-kpi-label">Chênh lệch trong nước – thế giới</div>
            <div class="gd-kpi-value {{ $pUp ? 'up' : 'down' }}">{{ F::percent($world['premium_percent'], 2, true) }}</div>
            <div class="gd-kpi-foot"><span>≈ <b class="gd-price" data-luong="{{ $world['premium_vnd'] }}">{{ F::number($world['premium_vnd']) }}</b> ₫/<span class="gd-unit-label">lượng</span></span></div>
            <div class="gd-hint">Giá bán SJC so với vàng thế giới quy đổi</div>
        </div>
        @endif
    </div>

    {{-- ── Chart ── --}}
    <div class="gd-card">
        <div class="gd-card-head">
            <h2 class="gd-h2"><i class="bi bi-graph-up"></i> Biến động giá</h2>
            <div class="gd-controls">
                <select id="gdProduct" class="gd-select" aria-label="Chọn loại vàng">
                    @foreach($chart_options as $o)<option value="{{ $o['id'] }}">{{ $o['label'] }}</option>@endforeach
                </select>
                <div class="gd-ranges" id="gdRanges">
                    @foreach(['1D' => '24 giờ', '7D' => '7 ngày', '30D' => '30 ngày', 'ALL' => 'Tất cả'] as $k => $l)
                        <button type="button" data-range="{{ $k }}" class="{{ $k === '7D' ? 'active' : '' }}">{{ $l }}</button>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="gd-legend"><span><i style="background:#16a34a"></i>Mua vào</span><span><i style="background:#d97706"></i>Bán ra</span></div>
        <div id="gdChart" class="gd-chart"></div>
        <p class="gd-note" id="gdChartNote"></p>
    </div>

    <div class="row">
        <div class="col-lg-8">
            {{-- ── Price tables ── --}}
            <div class="gd-card">
                <div class="gd-card-head">
                    <h2 class="gd-h2"><i class="bi bi-table"></i> Bảng giá vàng</h2>
                    <div class="gd-ranges" id="gdUnit">
                        <button type="button" data-unit="luong" class="active">Lượng</button>
                        <button type="button" data-unit="chi">Chỉ</button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="gd-table">
                        <thead><tr><th>Loại vàng</th><th class="num">Mua vào</th><th class="num">Bán ra</th><th class="num">Chênh lệch</th><th class="num">Hôm nay (bán)</th></tr></thead>
                        <tbody>
                        @if($sjc_branches->isNotEmpty())
                            <tr class="gd-group"><td colspan="5">SJC</td></tr>
                            @foreach($sjc_uniform ? $sjc_branches->take(1) : $sjc_branches as $q)
                                @php $chg = $q->id === ($h->id ?? null) ? $headline['sell_change'] : null; @endphp
                                <tr>
                                    <td><strong>{{ $q->product }}</strong><small class="gd-sub">{{ $sjc_uniform ? 'Áp dụng toàn quốc' : $q->branch }}</small></td>
                                    <td class="num gd-price" data-luong="{{ $q->buy_price }}">{{ F::number($q->buy_price) }}</td>
                                    <td class="num gd-price" data-luong="{{ $q->sell_price }}">{{ F::number($q->sell_price) }}</td>
                                    <td class="num gd-price gd-muted" data-luong="{{ $q->spread }}">{{ F::number($q->spread) }}</td>
                                    <td class="num">{!! $delta($chg) !!}</td>
                                </tr>
                            @endforeach
                        @endif
                        @if($btmc_gold->isNotEmpty())
                            <tr class="gd-group"><td colspan="5">Bảo Tín Minh Châu</td></tr>
                            @foreach($btmc_gold as $row)
                                @php $q = $row['quote']; @endphp
                                <tr>
                                    <td><strong>{{ $q->display_name }}</strong>@if($q->purity)<small class="gd-sub">Hàm lượng {{ $q->purity }}</small>@endif</td>
                                    <td class="num gd-price" data-luong="{{ $q->buy_price }}">{{ F::number($q->buy_price) }}</td>
                                    <td class="num gd-price" data-luong="{{ $q->sell_price }}">{{ $q->sell_price ? F::number($q->sell_price) : '—' }}</td>
                                    <td class="num gd-price gd-muted" data-luong="{{ $q->spread }}">{{ $q->spread !== null ? F::number($q->spread) : '—' }}</td>
                                    <td class="num">{!! $delta($row['sell_change']) !!}</td>
                                </tr>
                            @endforeach
                        @endif
                        </tbody>
                    </table>
                </div>
                <p class="gd-note" style="padding:0 1.4rem 1rem">Đơn vị: ₫ / <span class="gd-unit-label">lượng</span> (1 lượng = 10 chỉ = 37,5 g). “Hôm nay” so với mức giá cuối ngày hôm trước (hoặc mức đầu tiên trong ngày nếu chưa có).</p>
            </div>

            @if($silver->isNotEmpty())
            <div class="gd-card">
                <div class="gd-card-head"><h2 class="gd-h2"><i class="bi bi-gem"></i> Giá bạc (BTMC)</h2></div>
                <div class="table-responsive">
                    <table class="gd-table">
                        <thead><tr><th>Sản phẩm</th><th class="num">Mua vào</th><th class="num">Bán ra</th><th class="num">Hôm nay (bán)</th></tr></thead>
                        <tbody>
                        @foreach($silver as $row)
                            @php $q = $row['quote']; @endphp
                            <tr>
                                <td><strong>{{ $q->display_name }}</strong></td>
                                <td class="num">{{ F::number($q->buy_price) }}</td>
                                <td class="num">{{ $q->sell_price ? F::number($q->sell_price) : '—' }}</td>
                                <td class="num">{!! $delta($row['sell_change']) !!}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="gd-note" style="padding:0 1.4rem 1rem">Giá theo từng sản phẩm/quy cách ghi trong tên (1 lượng, 5 lượng, 1 kg…), đơn vị ₫.</p>
            </div>
            @endif
        </div>

        <div class="col-lg-4">
            {{-- ── Calculator ── --}}
            <div class="gd-card">
                <div class="gd-card-head"><h2 class="gd-h2"><i class="bi bi-calculator"></i> Tính giá trị vàng</h2></div>
                <div class="gd-card-body">
                    <label class="gd-label" for="gdCalcProduct">Loại vàng</label>
                    <select id="gdCalcProduct" class="gd-select w-100"></select>

                    <div class="row mt-3">
                        <div class="col-7"><label class="gd-label" for="gdQty">Số lượng</label><input id="gdQty" type="number" min="0" step="any" value="1" class="gd-input"></div>
                        <div class="col-5"><label class="gd-label" for="gdQtyUnit">Đơn vị</label>
                            <select id="gdQtyUnit" class="gd-select w-100"><option value="chi">chỉ</option><option value="luong">lượng</option></select></div>
                    </div>
                    <label class="gd-label mt-3" for="gdCost">Giá vốn của bạn <small>(₫ / lượng, tùy chọn)</small></label>
                    <input id="gdCost" type="number" min="0" step="any" class="gd-input" placeholder="VD: 120000000">

                    <div class="gd-result">
                        <div><small>Bán ngay được</small><b id="gdSellNow">—</b></div>
                        <div><small>Mua vào cần</small><b id="gdBuyNow">—</b></div>
                        <div id="gdPnlRow" hidden><small>Lãi / lỗ nếu bán ngay</small><b id="gdPnl">—</b></div>
                    </div>
                    <p class="gd-note">“Bán ngay được” tính theo giá <b>mua vào</b> của cửa hàng; chưa gồm phí gia công/chế tác.</p>
                </div>
            </div>

            <div class="gd-card">
                <div class="gd-card-body">
                    <h3 class="gd-h3">Về dữ liệu</h3>
                    <ul class="gd-list">
                        <li>Nguồn: SJC, Bảo Tín Minh Châu và giá vàng thế giới qua vnstock; cập nhật mỗi 15 phút (7h–19h).</li>
                        <li>Chưa có nguồn lịch sử miễn phí, nên biểu đồ được <b>tích lũy từ khi hệ thống bắt đầu ghi</b> và dày dần theo thời gian.</li>
                        <li>Giá SJC được đối chiếu chéo với BTMC; mức chênh bất thường sẽ bị loại.</li>
                        <li>Thông tin mang tính tham khảo, không phải khuyến nghị đầu tư.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endif

</div>
</div>
@endsection

@section('scripts')
<script>window.__GOLD__ = @json($pageData);</script>
@vite('resources/frontend/js/gold/index.js')
@endsection

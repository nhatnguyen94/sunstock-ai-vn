@use('App\Support\VnFormat', 'F')
@php
    $tz = 'Asia/Ho_Chi_Minh';
    $dir = fn ($v) => $v > 0 ? 'up' : ($v < 0 ? 'down' : 'flat');
    $icon = fn ($v) => $v > 0 ? 'bi-caret-up-fill' : ($v < 0 ? 'bi-caret-down-fill' : 'bi-dash');
@endphp

<section class="mk" id="market">
<div class="container">
@if(! ($market['has_data'] ?? false))
    <div class="mk-card mk-empty">
        <i class="bi bi-cloud-slash"></i>
        <div><strong>Chưa tải được dữ liệu thị trường</strong>
            <span>{{ $market['error'] ?? 'Nguồn dữ liệu đang chậm — hệ thống sẽ tự thử lại, bạn có thể tải lại trang sau ít phút.' }}</span></div>
    </div>
@else
    @php
        $b = $market['breadth'];
        $tot = max($b['total'], 1);
        $liq = $market['liquidity'];
    @endphp

    <div class="mk-head">
        <div>
            <h2 class="mk-title"><i class="bi bi-activity"></i> Tổng quan thị trường</h2>
            <p class="mk-meta">
                Phiên <strong>{{ $market['trade_date']->format('d/m/Y') }}</strong>
                · cập nhật <span id="mkUpdated">{{ $market['synced_at']?->timezone($tz)->format('H:i') }}</span>
                @if($market['stale'])<span class="mk-note">· đang làm mới ngầm</span>@endif
            </p>
        </div>
        <span class="mk-status {{ $market['market_open'] ? 'open' : '' }}" id="mkStatus">
            <i></i> {{ $market['market_open'] ? 'Đang giao dịch' : 'Ngoài giờ giao dịch' }}
        </span>
    </div>

    {{-- Indices --}}
    <div class="mk-indices">
        @foreach($market['indices'] as $i)
            @php $d = $dir($i['change']); @endphp
            <div class="mk-idx {{ $d }}" data-idx="{{ $i['code'] }}">
                <div class="mk-idx-name">{{ $i['name'] }}</div>
                <div class="mk-idx-close">{{ F::number($i['close'], 2) }}</div>
                <div class="mk-idx-chg {{ $d }}"><i class="bi {{ $icon($i['change']) }}"></i>
                    <span>{{ F::number(abs($i['change']), 2) }} ({{ F::percent(abs($i['percent']), 2) }})</span></div>
                @if($i['spark'])
                    <svg class="mk-spark" viewBox="0 0 100 32" preserveAspectRatio="none" aria-hidden="true"><polyline points="{{ $i['spark'] }}" fill="none" vector-effect="non-scaling-stroke"/></svg>
                @endif
                <div class="mk-idx-vol">KL {{ F::number($i['volume'] / 1e6, 1) }} triệu cp</div>
            </div>
        @endforeach
    </div>

    @if(! empty($brief))
    {{-- Session brief: sentences written from the numbers on this page (App\Support\MarketBrief), refreshed by the poll --}}
    <div class="mk-card mk-brief" id="mkBrief">
        <div class="mk-brief-head">
            <span class="mk-brief-badge"><i class="bi bi-stars" aria-hidden="true"></i> Bản tin phiên</span>
            <span class="mk-brief-date">{{ $market['trade_date']->format('d/m/Y') }}</span>
            <button type="button" class="mk-brief-ask" id="mkBriefAsk" data-ask="{{ $brief['ask'] }}"><i class="bi bi-robot" aria-hidden="true"></i> Hỏi AI vì sao</button>
        </div>
        <p class="mk-brief-headline {{ $brief['tone'] }}" id="mkBriefHeadline">{{ $brief['headline'] }}</p>
        <ul class="mk-brief-points" id="mkBriefPoints">
            @foreach($brief['points'] as $p)
                <li class="mk-bp {{ $p['tone'] }}" data-key="{{ $p['key'] }}">
                    <span class="mk-bp-icon"><i class="bi bi-{{ $p['icon'] }}" aria-hidden="true"></i></span>
                    <span class="mk-bp-body"><b>{{ $p['title'] }}</b><span class="mk-bp-text">{{ $p['text'] }}</span></span>
                </li>
            @endforeach
        </ul>
    </div>
    @endif

    @if(! empty($heatmap['items']))
    {{-- Heat map: drawn by js/market/heatmap.js from window.__MARKET__.heatmap (size = traded value, colour = % change) --}}
    <div class="mk-card mk-heat" id="mkHeat">
        <div class="mk-card-head">
            <h3>
                <button type="button" class="mk-heat-toggle" id="mkHeatToggle" aria-expanded="true" aria-controls="mkHeatBody" title="Thu gọn / mở rộng bản đồ nhiệt">
                    <i class="bi bi-chevron-down" aria-hidden="true"></i><span class="sr-only">Thu gọn hoặc mở rộng bản đồ nhiệt</span>
                </button>
                <i class="bi bi-grid-3x3-gap-fill"></i> Bản đồ nhiệt thị trường
                <span class="mk-heat-mini" id="mkHeatMini"></span>
            </h3>
            <div class="mk-heat-tools">
                <button type="button" class="mk-heat-back" id="mkHeatBack" hidden><i class="bi bi-arrow-left"></i> Tất cả ngành</button>
                <div class="mk-chips" id="mkHeatEx">
                    @foreach(['ALL' => 'Tất cả', 'HOSE' => 'HOSE', 'HNX' => 'HNX', 'UPCOM' => 'UPCoM'] as $k => $label)
                        <button type="button" data-ex="{{ $k }}" class="{{ $k === 'ALL' ? 'active' : '' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="mk-heat-body" id="mkHeatBody"><div class="mk-heat-inner">
        <p class="mk-heat-sub">Kích thước ô = giá trị giao dịch · màu = % thay đổi trong phiên · bấm tên ngành để phóng to, bấm mã để xem chi tiết.</p>
        <div class="mk-heat-stage" id="mkHeatStage" role="group" aria-label="Bản đồ nhiệt thị trường theo ngành">
            <noscript><p class="mk-loading">Bản đồ nhiệt cần bật JavaScript.</p></noscript>
            <div class="mk-heat-tip" id="mkHeatTip" role="tooltip" hidden></div>
        </div>
        <div class="mk-heat-foot">
            <span id="mkHeatSummary" class="mk-heat-summary">{{ $heatmap['shown'] }} mã</span>
            <span class="mk-heat-legend"><small>−7%</small><i id="mkHeatLegend"></i><small>+7%</small></span>
        </div>
        </div></div>
    </div>
    {{-- apply the remembered choice before the first paint (no jump): closed by default on phones --}}
    <script>
    (function () {
        try {
            var s = localStorage.getItem('sunstock-heatmap');
            if (s === 'closed' || (s === null && window.innerWidth < 768)) {
                document.getElementById('mkHeat').classList.add('is-collapsed');
                document.getElementById('mkHeatToggle').setAttribute('aria-expanded', 'false');
            }
        } catch (e) {}
    })();
    </script>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="mk-card">
                <div class="mk-card-head"><h3><i class="bi bi-graph-up-arrow"></i> VN-Index — {{ count($market['vnindex_series']) }} phiên gần nhất</h3></div>
                <div id="mkChart" class="mk-chart"></div>
            </div>
        </div>
        <div class="col-lg-4" data-aos="fade-up" data-aos-delay="120">
            <div class="mk-card">
                <div class="mk-card-head"><h3><i class="bi bi-bar-chart-steps"></i> Độ rộng thị trường</h3></div>
                <div class="mk-breadth">
                    <div class="mk-breadth-bar" id="mkBreadthBar">
                        <span class="up" style="width: {{ $b['advancers'] / $tot * 100 }}%"></span>
                        <span class="flat" style="width: {{ $b['unchanged'] / $tot * 100 }}%"></span>
                        <span class="down" style="width: {{ $b['decliners'] / $tot * 100 }}%"></span>
                    </div>
                    <div class="mk-breadth-nums">
                        <div class="up"><b id="mkAdv">{{ F::number($b['advancers']) }}</b><small>Tăng</small></div>
                        <div class="flat"><b id="mkUnch">{{ F::number($b['unchanged']) }}</b><small>Đứng giá</small></div>
                        <div class="down"><b id="mkDec">{{ F::number($b['decliners']) }}</b><small>Giảm</small></div>
                    </div>
                    <div class="mk-breadth-extra">
                        <span><i class="bi bi-arrow-up-circle-fill" style="color:#a855f7"></i> Trần <b id="mkCeil">{{ $b['ceiling'] }}</b></span>
                        <span><i class="bi bi-arrow-down-circle-fill" style="color:#06b6d4"></i> Sàn <b id="mkFloor">{{ $b['floor'] }}</b></span>
                    </div>
                </div>
                <div class="mk-liq">
                    <div class="mk-liq-top">
                        <span class="mk-liq-label">Thanh khoản</span>
                        <b id="mkLiq">{{ F::bigMoney($liq['value']) }}</b>
                    </div>
                    @if($liq['change_percent'] !== null)
                        <div class="mk-liq-cmp {{ $dir($liq['change_percent']) }}" id="mkLiqCmp">{{ F::percent($liq['change_percent'], 1, true) }} so với phiên trước</div>
                    @else
                        <div class="mk-liq-cmp" id="mkLiqCmp">{{ $market['market_open'] ? 'Đang cập nhật trong phiên' : 'Chưa có phiên trước để so sánh' }}</div>
                    @endif
                    <div class="mk-liq-ex">
                        @foreach(['HOSE' => 'HOSE', 'HNX' => 'HNX', 'UPCOM' => 'UPCoM'] as $k => $label)
                            @php $ev = $market['exchanges'][$k]['value'] ?? 0; @endphp
                            <div><span>{{ $label }}</span>
                                <span class="mk-liq-track"><i style="width: {{ $liq['value'] > 0 ? min(100, $ev / $liq['value'] * 100) : 0 }}%"></i></span>
                                <em data-ex-value="{{ $k }}">{{ F::bigMoney($ev) }}</em></div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8" data-aos="fade-up">
            <div class="mk-card">
                <div class="mk-card-head">
                    <h3><i class="bi bi-trophy"></i> Cổ phiếu nổi bật trong phiên</h3>
                    <div class="mk-chips" id="mkExchanges">
                        @foreach(['ALL' => 'Tất cả', 'HOSE' => 'HOSE', 'HNX' => 'HNX', 'UPCOM' => 'UPCoM'] as $k => $label)
                            <button type="button" data-ex="{{ $k }}" class="{{ $k === 'ALL' ? 'active' : '' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="mk-tabs" id="mkTabs" role="tablist">
                    <button type="button" role="tab" data-tab="gainers" class="active"><i class="bi bi-arrow-up-right"></i> Tăng mạnh</button>
                    <button type="button" role="tab" data-tab="losers"><i class="bi bi-arrow-down-right"></i> Giảm mạnh</button>
                    <button type="button" role="tab" data-tab="value"><i class="bi bi-droplet-half"></i> Thanh khoản cao</button>
                </div>
                <div class="table-responsive"><table class="mk-table"><thead><tr>
                    <th style="width:32px"></th><th>Mã</th><th class="num">Giá (₫)</th><th class="num">Thay đổi</th><th class="num">GTGD</th><th class="num d-none d-md-table-cell">KLGD</th>
                </tr></thead><tbody id="mkMovers"><tr><td colspan="6" class="mk-loading">Đang tải…</td></tr></tbody></table></div>
                <p class="mk-note-line">Chỉ xếp hạng cổ phiếu có giá trị giao dịch từ 5 tỷ đồng để loại các lệnh lẻ. Bấm ★ để theo dõi.</p>
            </div>
        </div>
        <div class="col-lg-4" data-aos="fade-up" data-aos-delay="120">
            <div class="mk-card mk-watch" id="mkWatch">
                <div class="mk-card-head">
                    <h3><i class="bi bi-star-fill" style="color:#f59e0b"></i> Danh sách theo dõi</h3>
                    @auth<a href="{{ route('watchlist.index') }}" class="mk-link">Xem tất cả <i class="bi bi-arrow-right"></i></a>@endauth
                </div>
                @auth
                    <div id="mkWatchBody"><p class="mk-loading">Đang tải…</p></div>
                @else
                    <div class="mk-cta">
                        <i class="bi bi-star"></i>
                        <h4>Theo dõi cổ phiếu yêu thích</h4>
                        <p>Đăng nhập để lưu danh sách theo dõi và xem giá, % thay đổi ngay tại trang chủ mỗi ngày.</p>
                        <a href="{{ route('login') }}" class="mk-btn mk-btn-primary">Đăng nhập</a>
                        <a href="{{ route('register') }}" class="mk-btn">Tạo tài khoản miễn phí</a>
                    </div>
                @endauth
            </div>
        </div>
    </div>
@endif
</div>
</section>

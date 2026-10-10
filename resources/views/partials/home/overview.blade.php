@use('App\Support\VnFormat', 'F')
{{-- Section "Tổng quan": the indices, one line about the session and the heat map. --}}
@if(! ($market['has_data'] ?? false))
    <div class="mk-card mk-empty">
        <i class="bi bi-cloud-slash"></i>
        <div><strong>Chưa tải được dữ liệu thị trường</strong>
            <span>{{ $market['error'] ?? 'Nguồn dữ liệu đang chậm — hệ thống sẽ tự thử lại, bạn có thể tải lại trang sau ít phút.' }}</span></div>
    </div>
@else
    @if(! empty($mine))
    {{-- "Của tôi" first for a signed-in visitor: their portfolios in one line + their watchlist (chips drawn by home.js from window.__MARKET__.watchlist) --}}
    @php $pf = $mine['portfolio']; @endphp
    <div class="mk-card mk-mine" id="mkMine">
        <div class="mk-mine-pf">
            <div class="mk-mine-label"><i class="bi bi-briefcase-fill" aria-hidden="true"></i> Danh mục của tôi</div>
            @if($pf['holdings'] > 0)
                <div class="mk-mine-value" title="Theo giá đã lưu gần nhất; mở danh mục để cập nhật giá mới">{{ F::bigMoney($pf['value']) }}</div>
                <div class="mk-mine-chips">
                    <span class="mk-chip {{ $dir($pf['pnl']) }}">Lãi/lỗ {{ $signedMoney($pf['pnl']) }}@if($pf['pnl_percent'] !== null) ({{ F::percent($pf['pnl_percent'], 2, true) }})@endif</span>
                    <span class="mk-chip {{ $dir($pf['day']) }}">Hôm nay {{ $signedMoney($pf['day']) }}@if($pf['day_percent'] !== null) ({{ F::percent($pf['day_percent'], 2, true) }})@endif</span>
                </div>
                <a href="{{ route('portfolio.index') }}" class="mk-link">Xem danh mục <i class="bi bi-arrow-right"></i></a>
            @else
                <p class="mk-mine-empty">{{ $pf['count'] > 0 ? 'Danh mục chưa có mã nào có giá.' : 'Bạn chưa có danh mục nào.' }}</p>
                <a href="{{ route('portfolio.index') }}" class="mk-btn mk-btn-primary">{{ $pf['count'] > 0 ? 'Mở danh mục' : 'Tạo danh mục' }}</a>
            @endif
        </div>
        <div class="mk-mine-watch">
            <div class="mk-mine-label"><i class="bi bi-star-fill" aria-hidden="true"></i> Theo dõi <a href="{{ route('watchlist.index') }}" class="mk-link">Xem tất cả <i class="bi bi-arrow-right"></i></a></div>
            <div class="mk-mine-row" id="mkMineWatch"><span class="mk-mine-empty">Đang tải…</span></div>
        </div>
    </div>
    @endif

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

    {{-- Indices: one compact strip --}}
    <div class="mk-indices">
        @foreach($market['indices'] as $i)
            @php $d = $dir($i['change']); @endphp
            <div class="mk-idx {{ $d }}" data-idx="{{ $i['code'] }}">
                <i class="mk-idx-orb" aria-hidden="true"></i>
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
    {{-- Session brief: the headline always, the five detail cards one click away (progressive disclosure); the choice is remembered --}}
    <div class="mk-card mk-brief is-collapsed" id="mkBrief">
        <div class="mk-brief-head">
            <span class="mk-brief-badge"><i class="bi bi-stars" aria-hidden="true"></i> Bản tin phiên</span>
            <span class="mk-brief-date">{{ $market['trade_date']->format('d/m/Y') }}</span>
            <span class="mk-brief-actions">
                <button type="button" class="mk-brief-more" id="mkBriefToggle" aria-expanded="false" aria-controls="mkBriefMore"><span>Chi tiết</span> <i class="bi bi-chevron-down" aria-hidden="true"></i></button>
                <button type="button" class="mk-brief-ask" id="mkBriefAsk" data-ask="{{ $brief['ask'] }}"><i class="bi bi-robot" aria-hidden="true"></i> <span>Hỏi AI vì sao</span></button>
            </span>
        </div>
        <p class="mk-brief-headline {{ $brief['tone'] }}" id="mkBriefHeadline">{{ $brief['headline'] }}</p>
        <div class="mk-brief-fold" id="mkBriefMore"><div class="mk-brief-inner">
            <ul class="mk-brief-points" id="mkBriefPoints">
                @foreach($brief['points'] as $p)
                    <li class="mk-bp {{ $p['tone'] }}" data-key="{{ $p['key'] }}">
                        <span class="mk-bp-icon"><i class="bi bi-{{ $p['icon'] }}" aria-hidden="true"></i></span>
                        <span class="mk-bp-body"><b>{{ $p['title'] }}</b><span class="mk-bp-text">{{ $p['text'] }}</span></span>
                    </li>
                @endforeach
            </ul>
        </div></div>
    </div>
    <script>
    (function () {
        try {
            if (localStorage.getItem('sunstock-brief') === 'open') {
                document.getElementById('mkBrief').classList.remove('is-collapsed');
                document.getElementById('mkBriefToggle').setAttribute('aria-expanded', 'true');
            }
        } catch (e) {}
    })();
    </script>
    @endif

    <div class="row mk-work">
        <div class="col-12 mk-col">
            <div class="mk-card mk-heat mk-view" id="mkHeat">
                <div class="mk-card-head">
                    <h3>
                        @if($hasHeat)
                        <button type="button" class="mk-heat-toggle" id="mkHeatToggle" aria-expanded="true" aria-controls="mkHeatBody" title="Thu gọn / mở rộng">
                            <i class="bi bi-chevron-down" aria-hidden="true"></i><span class="sr-only">Thu gọn hoặc mở rộng bản đồ nhiệt</span>
                        </button>
                        @endif
                        <span class="mk-vtabs" id="mkViewTabs" role="tablist" aria-label="Chế độ xem thị trường">
                            @if($hasHeat)
                            <button type="button" role="tab" id="mkTabMap" data-view="map" aria-controls="mkPanelMap" aria-selected="true" class="active"><i class="bi bi-grid-3x3-gap-fill" aria-hidden="true"></i> Bản đồ nhiệt</button>
                            @endif
                            <button type="button" role="tab" id="mkTabChart" data-view="chart" aria-controls="mkPanelChart" aria-selected="{{ $hasHeat ? 'false' : 'true' }}" tabindex="{{ $hasHeat ? -1 : 0 }}" class="{{ $hasHeat ? '' : 'active' }}"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i> VN-Index</button>
                        </span>
                        <span class="mk-heat-mini" id="mkHeatMini"></span>
                    </h3>
                    @if($hasHeat)
                    <div class="mk-heat-tools" id="mkHeatTools">
                        <button type="button" class="mk-heat-back" id="mkHeatBack" hidden><i class="bi bi-arrow-left"></i> Tất cả ngành</button>
                        <div class="mk-chips" id="mkHeatEx">
                            @foreach(['ALL' => 'Tất cả', 'HOSE' => 'HOSE', 'HNX' => 'HNX', 'UPCOM' => 'UPCoM'] as $k => $label)
                                <button type="button" data-ex="{{ $k }}" class="{{ $k === 'ALL' ? 'active' : '' }}">{{ $label }}</button>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
                <div class="mk-heat-body" id="mkHeatBody"><div class="mk-heat-inner">
                    @if($hasHeat)
                    <div class="mk-view-panel" id="mkPanelMap" role="tabpanel" aria-labelledby="mkTabMap">
                        <p class="mk-heat-sub">Kích thước ô = giá trị giao dịch · màu = % thay đổi trong phiên · bấm tên ngành để phóng to, bấm mã để xem chi tiết.</p>
                        <div class="mk-heat-stage" id="mkHeatStage" role="group" aria-label="Bản đồ nhiệt thị trường theo ngành">
                            <noscript><p class="mk-loading">Bản đồ nhiệt cần bật JavaScript.</p></noscript>
                            <div class="mk-heat-tip" id="mkHeatTip" role="tooltip" hidden></div>
                        </div>
                        <div class="mk-heat-foot">
                            <span id="mkHeatSummary" class="mk-heat-summary">{{ $heatmap['shown'] }} mã</span>
                            <span class="mk-heat-legend"><small>−7%</small><i id="mkHeatLegend"></i><small>+7%</small></span>
                        </div>
                    </div>
                    @endif
                    <div class="mk-view-panel" id="mkPanelChart" role="tabpanel" aria-labelledby="mkTabChart" @if($hasHeat) hidden @endif>
                        <p class="mk-heat-sub">VN-Index — {{ count($market['vnindex_series']) }} phiên gần nhất, kèm khối lượng.</p>
                        <div id="mkChart" class="mk-chart"></div>
                    </div>
                </div></div>
            </div>
            @if($hasHeat)
            {{-- apply the remembered choice before the first paint (no jump): folded by default on phones --}}
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
        </div>
    </div>

@endif

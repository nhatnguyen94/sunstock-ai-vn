@use('App\Support\VnFormat', 'F')
{{-- Section "Thị trường": the market as a whole — movers, breadth and liquidity, the world, foreign flow, sentiment, gold / USD / funds. --}}
    <!-- AI Prediction: a banner, not a lonely button -->
    <div class="ai-hero fx-tilt" data-aos="zoom-in">
        <i class="ai-orb o1" aria-hidden="true"></i><i class="ai-orb o2" aria-hidden="true"></i>
        <span class="ai-bars" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span>
        <div class="ai-hero-text">
            <span class="ai-hero-badge"><i class="bi bi-stars" aria-hidden="true"></i> AI · BETA</span>
            <h3>Dự đoán thị trường tuần này</h3>
            <p>AI đọc số liệu phiên gần nhất và viết nhận định ngắn gọn về xu hướng, nhóm ngành và rủi ro cần để ý.</p>
        </div>
        <button id="aiPredictBtn" type="button" class="ai-hero-btn"><i class="bi bi-robot" aria-hidden="true"></i> <span>Hỏi AI ngay</span></button>
        <div id="aiPredictResult" class="ai-hero-result" style="display:none;">
            <div id="aiPredictLoading" style="display:none;"><span class="loading"></span> Đang lấy dự đoán từ AI...</div>
            <div id="aiPredictContent"></div>
        </div>
    </div>
@if(! ($market['has_data'] ?? false))
    <div class="mk-card mk-empty">
        <i class="bi bi-cloud-slash"></i>
        <div><strong>Chưa có dữ liệu thị trường</strong>
            <span>Các danh sách sẽ hiện khi dữ liệu thị trường được tải.</span></div>
    </div>
@else
    @include('partials.world-strip')

    <div class="row mk-work">
        <div class="col-lg-8 mk-col">
            <div class="mk-card mk-side" id="mkSide">
                <div class="mk-side-tabs" id="mkSideTabs" role="tablist" aria-label="Danh sách thị trường">
                    <button type="button" role="tab" id="mkTabGainers" data-tab="gainers" aria-controls="mkPanelMovers" aria-selected="true" class="active"><i class="bi bi-arrow-up-right" aria-hidden="true"></i> Tăng</button>
                    <button type="button" role="tab" id="mkTabLosers" data-tab="losers" aria-controls="mkPanelMovers" aria-selected="false" tabindex="-1"><i class="bi bi-arrow-down-right" aria-hidden="true"></i> Giảm</button>
                    <button type="button" role="tab" id="mkTabValue" data-tab="value" aria-controls="mkPanelMovers" aria-selected="false" tabindex="-1" title="Giá trị giao dịch cao nhất"><i class="bi bi-droplet-half" aria-hidden="true"></i> GTGD</button>
                    <button type="button" role="tab" id="mkTabWatch" data-tab="watch" aria-controls="mkPanelWatch" aria-selected="false" tabindex="-1"><i class="bi bi-star-fill" aria-hidden="true"></i> Theo dõi</button>
                </div>

                <div class="mk-side-body">
                    <div id="mkPanelMovers" role="tabpanel" aria-labelledby="mkTabGainers">
                        <div class="mk-chips mk-side-chips" id="mkExchanges">
                            @foreach(['ALL' => 'Tất cả', 'HOSE' => 'HOSE', 'HNX' => 'HNX', 'UPCOM' => 'UPCoM'] as $k => $label)
                                <button type="button" data-ex="{{ $k }}" class="{{ $k === 'ALL' ? 'active' : '' }}">{{ $label }}</button>
                            @endforeach
                        </div>
                        <table class="mk-table mk-table-side"><thead><tr>
                            <th style="width:26px"></th><th>Mã</th><th class="num">Giá (₫)</th><th class="num">Thay đổi</th><th class="num">GTGD</th>
                        </tr></thead><tbody id="mkMovers"><tr><td colspan="5" class="mk-loading">Đang tải…</td></tr></tbody></table>
                        <p class="mk-note-line">Chỉ xếp hạng cổ phiếu có giá trị giao dịch từ 5 tỷ đồng để loại các lệnh lẻ. Bấm ★ để theo dõi.</p>
                    </div>

                    <div id="mkPanelWatch" role="tabpanel" aria-labelledby="mkTabWatch" class="mk-watch" hidden>
                        @auth
                            <div id="mkWatchBody"><p class="mk-loading">Đang tải…</p></div>
                            <p class="mk-side-link"><a href="{{ route('watchlist.index') }}" class="mk-link">Xem tất cả <i class="bi bi-arrow-right"></i></a></p>
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
        </div>
        <div class="col-lg-4 mk-col">
            <div class="mk-card mk-breadthcard" id="mkBreadthCard">
                <div class="mk-card-title"><i class="bi bi-bar-chart-steps" aria-hidden="true"></i> Độ rộng &amp; thanh khoản</div>
                <div class="mk-breadthcard-body">
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
    </div>

    @include('partials.market-pulse')
@endif

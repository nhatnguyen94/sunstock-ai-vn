@use('Carbon\Carbon')
{{-- Section "Khám phá": featured stocks, hot industries and exchange rates in one tabbed card, plus the product pitch for guests. --}}
    @php
        $exploreTab = request()->has('page') ? 'hot' : 'featured';          // a page of the hot-industries list was asked for
        $rateOrder = ['USD', 'EUR', 'JPY', 'CNY', 'KRW', 'AUD'];
        $rateDate = $exchangeRates ? array_key_first($exchangeRates) : null;
        $rateItems = collect($rateDate ? $exchangeRates[$rateDate] : []);
        $mainRates = collect($rateOrder)->map(fn ($c) => $rateItems->firstWhere('currency_code', $c))->filter()->values();
        if ($mainRates->isEmpty()) {
            $mainRates = $rateItems->take(6)->values();
        }
        $hasHot = $hotIndustries->count() > 0;
        $hasRates = $mainRates->isNotEmpty();
    @endphp
    <section class="mk-card mk-explore" id="explore" data-aos="fade-up">
        <div class="mk-explore-head">
            <h3 class="mk-explore-title"><i class="bi bi-compass" aria-hidden="true"></i> Khám phá thêm</h3>
            <div class="mk-explore-tabs" id="exploreTabs" role="tablist" aria-label="Khám phá thêm">
                <button type="button" role="tab" id="exTabFeatured" aria-controls="exPanelFeatured" aria-selected="{{ $exploreTab === 'featured' ? 'true' : 'false' }}" class="{{ $exploreTab === 'featured' ? 'active' : '' }}" tabindex="{{ $exploreTab === 'featured' ? 0 : -1 }}"><i class="bi bi-star-fill" aria-hidden="true"></i> Cổ phiếu nổi bật</button>
                @if($hasHot)
                <button type="button" role="tab" id="exTabHot" aria-controls="exPanelHot" aria-selected="{{ $exploreTab === 'hot' ? 'true' : 'false' }}" class="{{ $exploreTab === 'hot' ? 'active' : '' }}" tabindex="{{ $exploreTab === 'hot' ? 0 : -1 }}"><i class="bi bi-fire" aria-hidden="true"></i> Ngành hot <small>({{ $hotIndustries->total() }})</small></button>
                @endif
                @if($hasRates)
                <button type="button" role="tab" id="exTabRates" aria-controls="exPanelRates" aria-selected="false" tabindex="-1"><i class="bi bi-currency-exchange" aria-hidden="true"></i> Tỷ giá</button>
                @endif
            </div>
        </div>

        <div id="exPanelFeatured" role="tabpanel" aria-labelledby="exTabFeatured" class="mk-explore-panel" @if($exploreTab !== 'featured') hidden @endif>
            <p class="mk-explore-note">Giá hiển thị theo đơn vị <b>K = 1.000 VNĐ</b></p>
        <div class="featured-grid">
            @foreach($featured as $i => $stock)
            <div class="stock-card" data-aos="fade-up" data-aos-delay="{{ $i * 100 }}">
                <div class="stock-header">
                    <span class="stock-symbol">{{ $stock['symbol'] }}</span>
                    <span class="stock-exchange">{{ $stock['exchange'] }}</span>
                </div>
                
                <h3 class="stock-name">{{ $stock['name'] }}</h3>
                
                <div class="stock-price">
                    @if($stock['price'])
                        {{ number_format($stock['price'], 0, ',', '.') }}K
                    @else
                        N/A
                    @endif
                </div>
                
                @if($stock['change'] !== null)
                <div class="stock-change {{ $stock['change'] >= 0 ? 'positive' : 'negative' }}">
                    <i class="bi {{ $stock['change'] >= 0 ? 'bi-arrow-up-circle-fill' : 'bi-arrow-down-circle-fill' }}"></i>
                    {{ $stock['change'] >= 0 ? '+' : '' }}{{ number_format($stock['change'], 2) }}%
                </div>
                @endif
                
                <div class="stock-industry">
                    <i class="bi bi-building"></i>
                    {{ $stock['industry'] }}
                </div>
                
                <a href="{{ url('/stock?symbol='.$stock['symbol']) }}" class="view-detail-btn">
                    <i class="bi bi-eye"></i>
                    Xem chi tiết
                </a>
            </div>
            @endforeach
        </div>
        </div>

        @if($hasHot)
        <div id="exPanelHot" role="tabpanel" aria-labelledby="exTabHot" class="mk-explore-panel" @if($exploreTab !== 'hot') hidden @endif>
            <span id="hot-industries-section"></span>
            <p class="mk-explore-note">{{ $hotIndustries->total() }} công ty nổi bật theo ngành, 10 mã mỗi trang.</p>
        <div class="hot-table" id="hot-industries-table">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Mã CK</th>
                        <th>Tên công ty</th>
                        <th>Ngành</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($hotIndustries as $item)
                    <tr>
                        <td>
                            <span class="currency-code" style="background: var(--danger-red);">{{ $item['symbol'] }}</span>
                        </td>
                        <td style="font-weight: 500; text-align: left;">{{ $item['organ_name'] }}</td>
                        <td style="color: var(--text-secondary);">{{ $item['icb_name3'] }}</td>
                        <td>
                            <a href="{{ url('/stock?symbol='.$item['symbol']) }}" class="btn btn-primary-custom btn-sm">
                                <i class="bi bi-eye"></i>
                                Xem
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            
            @if($hotIndustries->hasPages())
            <div class="hot-industries-pagination">
                <div class="pagination-info">
                    <span>
                        Hiển thị {{ $hotIndustries->firstItem() }}-{{ $hotIndustries->lastItem() }} 
                        trong tổng số {{ $hotIndustries->total() }} công ty
                    </span>
                </div>
                <div class="pagination-links">
                    {{ $hotIndustries->appends(['#' => 'hot-industries-section'])->links('pagination::bootstrap-4') }}
                </div>
            </div>
            @endif
        </div>
        </div>
        @endif

        @if($hasRates)
        <div id="exPanelRates" role="tabpanel" aria-labelledby="exTabRates" class="mk-explore-panel" hidden>
            <p class="mk-explore-note">
                Tỷ giá ngoại tệ Vietcombank, ngày {{ Carbon::parse($rateDate)->format('d/m/Y') }}
                @if(Carbon::parse($rateDate)->isToday())<span class="badge badge-primary ml-1">Hôm nay</span>@endif
            </p>
            <div class="exchange-table">
                <table class="table table-hover mk-rates">
                    <thead><tr><th>Ngoại tệ</th><th>Tên</th><th class="num">Mua chuyển khoản</th><th class="num">Bán</th></tr></thead>
                    <tbody>
                        @foreach($mainRates as $item)
                        @php
                            $buy = parseRate($item['buy_transfer'] ?? $item['buy _transfer'] ?? null);
                            $sell = parseRate($item['sell'] ?? null);
                        @endphp
                        <tr>
                            <td><span class="currency-code">{{ $item['currency_code'] }}</span></td>
                            <td>{{ $item['currency_name'] }}</td>
                            <td class="num" style="color: var(--primary-blue); font-weight: 600;">{{ $buy !== null ? number_format($buy, 2) : '-' }}</td>
                            <td class="num" style="color: var(--danger-red); font-weight: 600;">{{ $sell !== null ? number_format($sell, 2) : '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mk-explore-more"><a href="{{ route('exchange-rate.index') }}" class="mk-link">Xem tất cả {{ $rateItems->count() }} ngoại tệ <i class="bi bi-arrow-right"></i></a></p>
        </div>
        @endif
    </section>

    <!-- Why Sun Stock AI: only for visitors who have not signed up -->
    @guest
    <section style="margin:0 0 3rem;" data-aos="fade-up">
        <h2 style="text-align:center;font-size:1.8rem;font-weight:700;color:var(--text-primary);margin-bottom:0.5rem;">
            Tại sao chọn <span style="background:linear-gradient(135deg,var(--primary-blue),var(--secondary-blue));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">Sun Stock AI</span>?
        </h2>
        <p style="text-align:center;color:var(--text-secondary);margin-bottom:2.5rem;">Công cụ phân tích cổ phiếu toàn diện, hoàn toàn miễn phí</p>
        <div class="row g-3">
            @php
            $features = [
                ['icon'=>'bi-graph-up-arrow','color'=>'#2563eb','bg'=>'#eff6ff','title'=>'Biểu đồ giá lịch sử','desc'=>'Xem biểu đồ nến, đường giá với dữ liệu lịch sử lên đến nhiều năm'],
                ['icon'=>'bi-cpu','color'=>'#7c3aed','bg'=>'#f5f3ff','title'=>'Phân tích AI','desc'=>'Hỏi AI về bất kỳ cổ phiếu nào, nhận phân tích và dự đoán thông minh'],
                ['icon'=>'bi-currency-exchange','color'=>'#059669','bg'=>'#ecfdf5','title'=>'Tỷ giá realtime','desc'=>'Tỷ giá Vietcombank cập nhật hàng ngày, hỗ trợ 20+ ngoại tệ'],
                ['icon'=>'bi-intersect','color'=>'#dc2626','bg'=>'#fef2f2','title'=>'So sánh cổ phiếu','desc'=>'So sánh hiệu suất của nhiều cổ phiếu cùng lúc trên cùng một biểu đồ'],
                ['icon'=>'bi-fire','color'=>'#ea580c','bg'=>'#fff7ed','title'=>'Ngành hot','desc'=>'Theo dõi các ngành nghề đang nổi bật: Ngân hàng, BĐS, Công nghệ'],
                ['icon'=>'bi-briefcase','color'=>'#0891b2','bg'=>'#ecfeff','title'=>'Danh mục cá nhân','desc'=>'Quản lý danh mục đầu tư, theo dõi lãi/lỗ và target price (cần đăng ký)'],
            ];
            @endphp
            @foreach($features as $j => $f)
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="{{ $j * 80 }}">
                <div style="background:white;border-radius:16px;padding:1.75rem;border:1px solid var(--border-color);height:100%;display:flex;gap:1rem;align-items:flex-start;transition:all 0.3s ease;box-shadow:var(--shadow-sm);" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='var(--shadow-lg)'" onmouseout="this.style.transform='';this.style.boxShadow='var(--shadow-sm)'">
                    <div style="width:50px;height:50px;border-radius:14px;background:{{ $f['bg'] }};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="bi {{ $f['icon'] }}" style="font-size:1.4rem;color:{{ $f['color'] }};"></i>
                    </div>
                    <div>
                        <div style="font-weight:700;color:var(--text-primary);margin-bottom:0.4rem;">{{ $f['title'] }}</div>
                        <div style="font-size:0.875rem;color:var(--text-secondary);line-height:1.5;">{{ $f['desc'] }}</div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </section>
    @endguest

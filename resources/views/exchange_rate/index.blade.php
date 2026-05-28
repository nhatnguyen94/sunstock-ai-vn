@extends('layouts.app')

@section('head')
@vite('resources/frontend/css/exchange_rate/index.css')
@endsection

@section('content')
@php
$currencyFlags = ['USD'=>'🇺🇸','EUR'=>'🇪🇺','JPY'=>'🇯🇵','GBP'=>'🇬🇧','CNY'=>'🇨🇳','SGD'=>'🇸🇬','HKD'=>'🇭🇰','AUD'=>'🇦🇺','CAD'=>'🇨🇦','CHF'=>'🇨🇭','KRW'=>'🇰🇷','THB'=>'🇹🇭','NOK'=>'🇳🇴','SEK'=>'🇸🇪','DKK'=>'🇩🇰','MYR'=>'🇲🇾','INR'=>'🇮🇳','NZD'=>'🇳🇿','SAR'=>'🇸🇦','AED'=>'🇦🇪','KWD'=>'🇰🇼'];
$keyRates = ['USD','EUR','JPY','GBP','CNY'];
@endphp
<!-- Header Section -->
<section class="exchange-header" style="padding-bottom:0;">
    <div class="container">
        <div class="row align-items-center" style="padding-top:2.5rem;padding-bottom:2rem;">
            <div class="col-md-7">
                <div style="display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.25);border-radius:30px;padding:5px 16px;margin-bottom:1rem;font-size:0.8rem;font-weight:600;">
                    <span style="width:8px;height:8px;border-radius:50%;background:#34d399;animation:pulse-ring 1.5s ease-out infinite;display:inline-block;"></span>
                    Cập nhật hàng ngày lúc 07:30
                </div>
                <h1 class="exchange-title fade-in" style="margin-bottom:0.5rem;">
                    <i class="bi bi-currency-exchange"></i>
                    Tỷ Giá Ngoại Tệ
                </h1>
                <p class="exchange-subtitle fade-in">Vietcombank · Cập nhật mỗi ngày · Hỗ trợ 20+ ngoại tệ</p>
            </div>
            <div class="col-md-5 text-md-end">
                <div style="display:flex;flex-wrap:wrap;justify-content:flex-end;gap:8px;align-items:center;">
                    <a href="{{ url('/') }}" class="back-button">
                        <i class="bi bi-house"></i> Trang chủ
                    </a>
                    <a href="{{ url('/exchange-rate') }}" class="back-button">
                        <i class="bi bi-arrow-clockwise"></i> Mới nhất
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Highlights Strip -->
        @php
        $highlightCurrencies = ['USD' => ['🇺🇸', '#2563eb'], 'EUR' => ['🇪🇺', '#7c3aed'], 'JPY' => ['🇯🇵', '#dc2626'], 'GBP' => ['🇬🇧', '#059669'], 'CNY' => ['🇨🇳', '#ea580c'], 'SGD' => ['🇸🇬', '#0891b2']];
        $highlightData = [];
        $ratesForHighlight = $rates ?? [];
        if (!empty($ratesForHighlight)) {
            $firstDateItems = reset($ratesForHighlight);
            foreach ($firstDateItems as $item) {
                $code = $item['currency_code'] ?? '';
                if (isset($highlightCurrencies[$code])) {
                    $highlightData[$code] = $item;
                }
            }
        }
        @endphp
        @if(!empty($highlightData))
        <div style="display:flex;gap:0;border-top:1px solid rgba(255,255,255,0.15);overflow-x:auto;padding-bottom:0;">
            @foreach($highlightCurrencies as $code => $meta)
            @if(isset($highlightData[$code]))
            @php $item = $highlightData[$code]; $sell = $item['sell'] ?? '-'; @endphp
            <div style="flex:1;min-width:100px;padding:1rem 1.25rem;border-right:1px solid rgba(255,255,255,0.1);text-align:center;background:rgba(255,255,255,0.06);cursor:pointer;transition:background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.12)'" onmouseout="this.style.background='rgba(255,255,255,0.06)'">
                <div style="font-size:1.4rem;margin-bottom:2px;">{{ $meta[0] }}</div>
                <div style="font-size:0.72rem;font-weight:700;opacity:0.8;letter-spacing:1px;">{{ $code }}</div>
                <div style="font-size:0.95rem;font-weight:800;color:white;margin-top:2px;">{{ is_numeric(str_replace([',','.'], '', $sell)) ? number_format((float)str_replace(',', '', $sell), 0, ',', '.') : $sell }}</div>
                <div style="font-size:0.65rem;opacity:0.5;margin-top:1px;">VNĐ</div>
            </div>
            @endif
            @endforeach
        </div>
        @endif
    </div>
</section>

<!-- Main Content -->
<div class="main-content">
    <div class="container">

        <!-- Key Rates Bar Chart -->
        @php
        $chartRates = [];
        $chartColors = ['#2563eb','#7c3aed','#dc2626','#059669','#ea580c','#0891b2'];
        $ratesForChart = $rates ?? [];
        if (!empty($ratesForChart)) {
            $firstItems = reset($ratesForChart);
            $idx = 0;
            foreach (['USD','EUR','JPY','GBP','CNY','SGD'] as $code) {
                foreach ($firstItems as $it) {
                    if (($it['currency_code'] ?? '') === $code) {
                        $sell = $it['sell'] ?? '0';
                        $sellNum = (float)str_replace([',', ' '], '', $sell);
                        if ($sellNum > 0) $chartRates[] = ['code' => $code, 'sell' => $sellNum, 'flag' => ['USD'=>'🇺🇸','EUR'=>'🇪🇺','JPY'=>'🇯🇵','GBP'=>'🇬🇧','CNY'=>'🇨🇳','SGD'=>'🇸🇬'][$code] ?? '', 'color' => $chartColors[$idx]];
                        $idx++;
                        break;
                    }
                }
            }
        }
        @endphp
        @if(!empty($chartRates))
        <div style="background:white;border-radius:20px;padding:1.75rem 2rem 0.5rem;box-shadow:0 4px 24px rgba(37,99,235,0.08);border:1px solid #e5e7eb;margin-bottom:2.5rem;position:relative;overflow:hidden;" data-aos="fade-up">
            <div style="position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,#2563eb,#7c3aed,#10b981);border-radius:20px 20px 0 0;"></div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:8px;">
                <div>
                    <h4 style="font-size:1.1rem;font-weight:700;color:#1f2937;margin:0;">
                        <i class="bi bi-bar-chart-fill" style="color:#2563eb;margin-right:6px;"></i>
                        Tỷ giá bán (VNĐ) các ngoại tệ chính
                    </h4>
                    <p style="font-size:0.8rem;color:#9ca3af;margin:2px 0 0;">Giá bán Vietcombank · {{ !empty($ratesForChart) ? \Carbon\Carbon::parse(array_key_first($ratesForChart))->format('d/m/Y') : 'Hôm nay' }}</p>
                </div>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    @foreach($chartRates as $cr)
                    <span style="display:inline-flex;align-items:center;gap:4px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:3px 10px;font-size:0.78rem;font-weight:700;color:#374151;">
                        {{ $cr['flag'] }} {{ $cr['code'] }}: <span style="color:{{ $cr['color'] }};">{{ number_format($cr['sell'], 0, ',', '.') }}</span>
                    </span>
                    @endforeach
                </div>
            </div>
            <div id="keyRatesChart"></div>
        </div>
        @endif
        <div class="search-section slide-up">
            <h3 class="search-title">
                <i class="bi bi-search"></i>
                Tìm kiếm tỷ giá theo ngày
            </h3>
            
            <form method="GET" action="{{ route('exchange-rate.search') }}" class="search-form">
                <div class="search-group">
                    <label class="search-label" for="search_date">
                        <i class="bi bi-calendar3"></i>
                        Chọn ngày tra cứu
                    </label>
                    
                    <div class="date-input-container">
                        <input 
                            type="date" 
                            name="date" 
                            id="search_date" 
                            class="search-input {{ isset($date) ? 'has-value' : '' }}" 
                            value="{{ $date ?? '' }}"
                            max="{{ date('Y-m-d') }}"
                            min="2020-01-01"
                            data-placeholder="dd/mm/yyyy"
                        >
                        
                        <button type="button" class="clear-date-btn {{ isset($date) ? 'show' : '' }}" id="clear-date" title="Xóa ngày đã chọn">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                    
                    <div class="date-display {{ isset($date) ? 'show' : '' }}" id="date-display">
                        @if(isset($date))
                            <i class="bi bi-check-circle"></i>
                            Ngày đã chọn: {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}
                            ({{ \Carbon\Carbon::parse($date)->diffForHumans() }})
                        @endif
                    </div>
                    
                    @if(!isset($date))
                        <div class="date-help" id="date-help">
                            <i class="bi bi-info-circle"></i>
                            Chọn ngày để tra cứu tỷ giá hối đoái
                        </div>
                    @endif
                </div>
                
                <div class="button-container">
                    <button type="submit" class="search-btn" id="search-button">
                        <i class="bi bi-search"></i>
                        {{ isset($date) ? 'Tìm lại' : 'Tìm kiếm' }}
                    </button>
                    
                    @if(isset($date))
                        <a href="{{ route('exchange-rate.index') }}" class="clear-btn">
                            <i class="bi bi-x-circle"></i>
                            Xóa bộ lọc
                        </a>
                    @else
                        <a href="{{ route('exchange-rate.index') }}" class="clear-btn">
                            <i class="bi bi-arrow-clockwise"></i>
                            Làm mới
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Statistics Section -->
        <div class="stats-section">
            <div class="stats-container">
                <div class="stat-card slide-up" style="animation-delay: 0.1s;">
                    <i class="stat-icon bi bi-calendar3"></i>
                    <span class="stat-number">{{ count($rates ?? []) }}</span>
                    <span class="stat-label">Ngày có dữ liệu</span>
                </div>
                
                <div class="stat-card slide-up" style="animation-delay: 0.2s;">
                    <i class="stat-icon bi bi-cash-coin"></i>
                    <span class="stat-number">{{ count($rates ?? []) > 0 ? count(reset($rates)) : (count($searchRates ?? [])) }}</span>
                    <span class="stat-label">Loại tiền tệ</span>
                </div>
                
                <div class="stat-card slide-up" style="animation-delay: 0.3s;">
                    <i class="stat-icon bi bi-bank"></i>
                    <span class="stat-number">VCB</span>
                    <span class="stat-label">Vietcombank</span>
                </div>
                
                <div class="stat-card slide-up" style="animation-delay: 0.4s;">
                    <i class="stat-icon bi bi-clock"></i>
                    <span class="stat-number" id="current-time">--:--:--</span>
                    <span class="stat-label">Thời gian hiện tại</span>
                </div>
            </div>
        </div>

        <!-- Results Section -->
        <div class="results-section">
            @if(isset($searchRates) && count($searchRates) > 0)
                <!-- Search Results -->
                <h2 class="section-title">Kết quả tìm kiếm</h2>
                @foreach($searchRates as $rateDate=> $items)
                <div class="date-header slide-up">
                    <h4 class="date-title">
                        <i class="bi bi-calendar-check"></i>
                        {{ \Carbon\Carbon::parse($rateDate)->format('d/m/Y') }}
                        <span class="date-badge">
                            {{ \Carbon\Carbon::parse($rateDate)->diffForHumans() }}
                        </span>
                    </h4>
                </div>

                <div class="exchange-container slide-up">
                    <div class="table-responsive">
                        <table class="exchange-table">
                            <thead>
                                <tr>
                                    <th><i class="bi bi-tag"></i> Mã</th>
                                    <th><i class="bi bi-globe"></i> Tên tiền tệ</th>
                                    <th><i class="bi bi-calculator"></i> Đơn vị</th>
                                    <th><i class="bi bi-cash-stack text-danger"></i> Mua tiền mặt</th>
                                    <th><i class="bi bi-credit-card text-warning"></i> Mua chuyển khoản</th>
                                    <th><i class="bi bi-arrow-up-circle text-success"></i> Bán ra</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($items as $item)
                                @php $code = $item['currency_code'] ?? ''; $isKey = in_array($code, $keyRates); @endphp
                                <tr class="{{ $isKey ? 'currency-row-highlight' : '' }}">
                                    <td>
                                        <div style="display:flex;align-items:center;justify-content:center;gap:6px;">
                                            <span style="font-size:1.3rem;">{{ $currencyFlags[$code] ?? '🏳️' }}</span>
                                            <span class="currency-code" style="{{ $isKey ? 'box-shadow:0 3px 12px rgba(37,99,235,0.4);' : '' }}">{{ $code }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="currency-name">{{ $item['currency_name'] }}</div>
                                    </td>
                                    <td>
                                        <div class="currency-unit">1 {{ $code }} = VNĐ</div>
                                    </td>
                                    <td>
                                        <span class="rate-value buy-cash" title="Click để copy">
                                            {{ $item['buy _cash'] ?? $item['buy_cash'] ?? '-' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="rate-value buy-transfer" title="Click để copy">
                                            {{ $item['buy _transfer'] ?? $item['buy_transfer'] ?? '-' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="rate-value sell-rate" title="Click để copy">
                                            {{ $item['sell'] ?? '-' }}
                                        </span>
                                    </td>
                                </tr>
                                 @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endforeach

            @elseif(isset($searchRates))
                <!-- No Search Results -->
                <div class="no-data slide-up">
                    <i class="no-data-icon bi bi-calendar-x"></i>
                    <h4 class="no-data-title">Không tìm thấy dữ liệu</h4>
                    <p class="no-data-text">Không có dữ liệu tỷ giá cho ngày {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</p>
                </div>
            @endif

            @if(isset($rates) && count($rates) > 0)
                <!-- Latest Rates -->
                @if(!isset($searchRates))
                    <h2 class="section-title">Tỷ giá 3 ngày gần nhất</h2>
                @else
                    <h2 class="section-title" style="margin-top: 4rem;">Tỷ giá 3 ngày gần nhất</h2>
                @endif

                @foreach($rates as $rateDate => $items)
                    <div class="date-header slide-up" style="animation-delay: {{ $loop->index * 0.1 }}s;">
                        <h4 class="date-title">
                            <i class="bi bi-calendar-check"></i>
                            {{ \Carbon\Carbon::parse($rateDate)->format('d/m/Y') }}
                            <span class="date-badge">
                                {{ \Carbon\Carbon::parse($rateDate)->diffForHumans() }}
                            </span>
                        </h4>
                    </div>

                    <div class="exchange-container slide-up" style="animation-delay: {{ $loop->index * 0.1 + 0.1 }}s;">
                        <div class="table-responsive">
                            <table class="exchange-table">
                                <thead>
                                    <tr>
                                        <th><i class="bi bi-tag"></i> Mã</th>
                                        <th><i class="bi bi-globe"></i> Tên tiền tệ</th>
                                        <th><i class="bi bi-calculator"></i> Đơn vị</th>
                                        <th><i class="bi bi-cash-stack text-danger"></i> Mua tiền mặt</th>
                                        <th><i class="bi bi-credit-card text-warning"></i> Mua chuyển khoản</th>
                                        <th><i class="bi bi-arrow-up-circle text-success"></i> Bán ra</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($items as $item)
                                    @php $code = $item['currency_code'] ?? ''; $isKey = in_array($code, $keyRates); @endphp
                                    <tr class="{{ $isKey ? 'currency-row-highlight' : '' }}">
                                        <td>
                                            <div style="display:flex;align-items:center;justify-content:center;gap:6px;">
                                                <span style="font-size:1.3rem;">{{ $currencyFlags[$code] ?? '🏳️' }}</span>
                                                <span class="currency-code" style="{{ $isKey ? 'box-shadow:0 3px 12px rgba(37,99,235,0.4);' : '' }}">{{ $code }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="currency-name">{{ $item['currency_name'] ?? '' }}</div>
                                        </td>
                                        <td>
                                            <div class="currency-unit">1 {{ $code }} = VNĐ</div>
                                        </td>
                                        <td>
                                            <span class="rate-value buy-cash" title="Click để copy">
                                                {{ $item['buy _cash'] ?? $item['buy_cash'] ?? '-' }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="rate-value buy-transfer" title="Click để copy">
                                                {{ $item['buy _transfer'] ?? $item['buy_transfer'] ?? '-' }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="rate-value sell-rate" title="Click để copy">
                                                {{ $item['sell'] ?? '-' }}
                                            </span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            @endif

            @if((!isset($rates) || count($rates) == 0) && !isset($searchRates))
                <!-- No Data At All -->
                <div class="no-data slide-up">
                    <i class="no-data-icon bi bi-info-circle"></i>
                    <h4 class="no-data-title">Chưa có dữ liệu</h4>
                    <p class="no-data-text">Hiện tại chưa có thông tin tỷ giá. Vui lòng thử lại sau.</p>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Floating Action Buttons -->
<div class="fab-container">
    <button class="fab fab-secondary" onclick="window.print()" title="In trang">
        <i class="bi bi-printer"></i>
    </button>
    <button class="fab fab-primary" onclick="refreshPage()" title="Làm mới trang">
        <i class="bi bi-arrow-clockwise" id="refresh-icon"></i>
    </button>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.0/dist/apexcharts.min.js"></script>

<script>
window._exchangeRateUrl = '{{ route("exchange-rate.index") }}';
</script>
@vite('resources/frontend/js/exchange_rate/index.js')
@endsection
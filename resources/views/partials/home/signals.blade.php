@use('Carbon\Carbon')
{{-- Section "Tín hiệu & sự kiện": signals are built in the background and events come from cached profiles; this tab only reads them. --}}
    <section class="mk-card mk-explore" id="signals">
        <div class="mk-explore-head"><h3 class="mk-explore-title"><i class="bi bi-lightning-charge-fill" aria-hidden="true"></i> Tín hiệu hôm nay</h3></div>
        {{-- Tín hiệu: built in the background (StockSignalService), so this panel only reads the cached result --}}
        @php
            $sig = $extras['signals'] ?? null;
            $sigLists = [
                'breakout' => ['Vượt đỉnh 52 tuần', 'bi-graph-up-arrow', 'up'],
                'breakdown' => ['Thủng đáy 52 tuần', 'bi-graph-down-arrow', 'down'],
                'volume' => ['Khối lượng đột biến', 'bi-bar-chart-fill', 'flat'],
                'overbought' => ['RSI quá mua (từ 70)', 'bi-thermometer-high', 'down'],
                'oversold' => ['RSI quá bán (đến 30)', 'bi-thermometer-low', 'up'],
            ];
            $sigExtra = fn (string $key, array $r) => match ($key) {
                'breakout' => 'trên đỉnh '.number_format($r['over'], 2, ',', '.').'%',
                'breakdown' => 'dưới đáy '.number_format($r['under'], 2, ',', '.').'%',
                'volume' => '×'.($r['ratio'] > 99 ? '99+' : number_format($r['ratio'], 1, ',', '.')).' KL TB',
                default => 'RSI '.number_format($r['rsi'], 0),
            };
        @endphp
        <div id="exPanelSignals" class="mk-explore-panel">
            @if($sig)
                <p class="mk-explore-note">Phiên {{ Carbon::parse($sig['trade_date'])->format('d/m/Y') }} · xét {{ $sig['universe'] }} cổ phiếu thanh khoản cao (giao dịch từ 3 tỷ, đủ lịch sử). Mô tả điều đã xảy ra, không phải khuyến nghị mua bán.</p>
                <div class="mk-sig-grid">
                    @foreach($sigLists as $key => [$title, $icon, $tone])
                        <div class="mk-sig {{ $tone }}">
                            <h5><i class="bi {{ $icon }}" aria-hidden="true"></i> {{ $title }} <small>{{ $sig[$key.'_total'] }}</small></h5>
                            <ul>
                                @forelse($sig[$key] as $r)
                                    <li><a class="mk-sym" href="{{ url('/stock?symbol='.$r['s']) }}">{{ $r['s'] }}</a><span class="mk-sig-px">{{ number_format($r['p'], 0, ',', '.') }}</span><span class="mk-pct {{ $r['c'] > 0 ? 'up' : ($r['c'] < 0 ? 'down' : 'flat') }}">{{ ($r['c'] > 0 ? '+' : '').number_format($r['c'], 2, ',', '.') }}%</span><small>{{ $sigExtra($key, $r) }}</small></li>
                                @empty
                                    <li class="mk-sig-none">Không có mã nào</li>
                                @endforelse
                            </ul>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mk-explore-note">Tín hiệu đang được tính lần đầu ở chế độ nền (khoảng một phút). Tải lại trang sau ít phút để xem.</p>
            @endif
        </div>
    </section>

    <section class="mk-card mk-explore" id="events">
        <div class="mk-explore-head"><h3 class="mk-explore-title"><i class="bi bi-calendar-event" aria-hidden="true"></i> Sự kiện sắp tới</h3></div>
        {{-- Sự kiện: dividends / bonus shares / shareholder meetings coming up, from the profiles the site has cached (+ the visitor's own symbols) --}}
        @php $ev = $extras['events'] ?? null; $weekday = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7']; @endphp
        <div id="exPanelEvents" class="mk-explore-panel">
            @if($ev && $ev['events'])
                <ul class="mk-ev-list">
                    @foreach($ev['events'] as $e)
                        @php $d = Carbon::parse($e['date']); @endphp
                        <li class="mk-ev {{ $e['kind'] }}">
                            <span class="mk-ev-date"><b>{{ $d->format('d/m') }}</b><small>{{ $weekday[$d->dayOfWeek] }}</small></span>
                            <div class="mk-ev-body">
                                <a class="mk-sym" href="{{ route('company.show', $e['symbol']) }}">{{ $e['symbol'] }}</a>
                                @if($e['mine'])<span class="mk-badge">của bạn</span>@endif
                                <em>{{ $e['label'] }}</em>
                                @if($e['title'] !== '')<div class="mk-ev-title">{{ $e['title'] }}</div>@endif
                            </div>
                        </li>
                    @endforeach
                </ul>
                <p class="mk-explore-note">Từ {{ $ev['covered'] }} công ty đã có hồ sơ{{ auth()->check() ? ' và các mã bạn theo dõi hoặc nắm giữ' : '' }}. Hồ sơ của các cổ phiếu giao dịch mạnh được nạp dần mỗi đêm.</p>
            @else
                <p class="mk-explore-note">Chưa có sự kiện sắp tới (cổ tức, thưởng cổ phiếu, đại hội cổ đông) trong dữ liệu hiện có{{ $ev ? ' — '.$ev['covered'].' công ty đã có hồ sơ' : '' }}.
                    {{ auth()->check() ? 'Thêm mã vào danh sách theo dõi để theo dõi sự kiện của mã đó.' : 'Đăng nhập và theo dõi các mã bạn quan tâm để xem sự kiện của chúng.' }}</p>
            @endif
        </div>
    </section>

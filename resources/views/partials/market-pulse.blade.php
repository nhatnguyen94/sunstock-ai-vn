{{-- "Nhịp thị trường": three small cards under the workspace. Foreign flow and sentiment are drawn by js/market/pulse.js from window.__MARKET__
     (and redrawn by every poll); "Vàng · Tỷ giá · Quỹ" is server-rendered from tables the site already fills. A card without data is simply left out. --}}
@use('Illuminate\Support\Carbon')
@use('App\Support\VnFormat', 'F')
@php
    $pulse = $extras['pulse'] ?? null;
    $hasForeign = ! empty($market['foreign']);
    $hasSentiment = ! empty($extras['sentiment']);
    $hasPulse = is_array($pulse) && ($pulse['gold'] || $pulse['usd'] || ! empty($pulse['funds']));
    $cards = (int) $hasForeign + (int) $hasSentiment + (int) $hasPulse;
    $col = $cards === 1 ? 'col-12' : ($cards === 2 ? 'col-lg-6' : 'col-lg-4');
@endphp
@if($cards > 0)
<div class="row mk-pulse" id="mkPulse">
    @if($hasForeign)
    <div class="{{ $col }} mk-col">
        <div class="mk-card mk-pc" id="mkForeignCard">
            <div class="mk-card-head"><h3><i class="bi bi-globe-asia-australia" aria-hidden="true"></i> Khối ngoại <span class="mk-badge">ước tính</span></h3></div>
            <div class="mk-pc-body" id="mkForeign"><p class="mk-loading">Đang tải…</p></div>
        </div>
    </div>
    @endif

    @if($hasSentiment)
    <div class="{{ $col }} mk-col">
        <div class="mk-card mk-pc" id="mkSentimentCard">
            <div class="mk-card-head"><h3><i class="bi bi-speedometer2" aria-hidden="true"></i> Tâm lý thị trường <span class="mk-badge">tự tính</span></h3></div>
            <div class="mk-pc-body" id="mkSentiment"><p class="mk-loading">Đang tải…</p></div>
        </div>
    </div>
    @endif

    @if($hasPulse)
    <div class="{{ $col }} mk-col">
        <div class="mk-card mk-pc" id="mkPulseCard">
            <div class="mk-card-head"><h3><i class="bi bi-coin" aria-hidden="true"></i> Vàng · Tỷ giá · Quỹ</h3></div>
            <div class="mk-pc-body">
                @if($pulse['gold'])
                    @php $g = $pulse['gold']; @endphp
                    <a class="mk-pr" href="{{ route('gold.index') }}">
                        <span class="mk-pr-name"><i class="bi bi-coin" style="color:#d97706" aria-hidden="true"></i> {{ $g['name'] }}</span>
                        <span class="mk-pr-val">{{ F::number($g['price'] / 1e6, 1) }} tr<small>/{{ $g['unit'] }}</small></span>
                        @if($g['percent'] !== null)<span class="mk-pct {{ $dir($g['percent']) }}">{{ F::percent($g['percent'], 2, true) }}</span>@else<span class="mk-pr-na">—</span>@endif
                        <span class="mk-pr-sub">
                            @if($g['world_usd'])Thế giới {{ F::number($g['world_usd']) }} USD/oz · @endif
                            cập nhật {{ Carbon::parse($g['quoted_at'])->timezone($tz)->format('d/m H:i') }}
                        </span>
                    </a>
                @endif
                @if($pulse['usd'])
                    @php $u = $pulse['usd']; @endphp
                    <a class="mk-pr" href="{{ route('exchange-rate.index') }}">
                        <span class="mk-pr-name"><i class="bi bi-currency-exchange" style="color:#059669" aria-hidden="true"></i> USD (Vietcombank bán)</span>
                        <span class="mk-pr-val">{{ F::number($u['sell']) }} ₫</span>
                        @if($u['percent'] !== null)<span class="mk-pct {{ $dir($u['percent']) }}">{{ F::percent($u['percent'], 2, true) }}</span>@else<span class="mk-pr-na">—</span>@endif
                        <span class="mk-pr-sub">Mua chuyển khoản {{ $u['buy'] ? F::number($u['buy']) : '—' }} · ngày {{ Carbon::parse($u['date'])->format('d/m') }}</span>
                    </a>
                @endif
                @if(! empty($pulse['funds']))
                    <div class="mk-pr-funds">
                        <div class="mk-pr-head"><span>Quỹ cổ phiếu, 12 tháng</span><a href="{{ route('funds.index') }}" class="mk-link">Tất cả quỹ <i class="bi bi-arrow-right"></i></a></div>
                        <ul>
                            @foreach($pulse['funds'] as $f)
                                <li><a href="{{ url('/funds/'.rawurlencode($f['code'])) }}" title="{{ $f['name'] }}"><b>{{ $f['code'] }}</b></a><span class="mk-pct {{ $dir($f['percent']) }}">{{ F::percent($f['percent'], 2, true) }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </div>
    @endif
</div>
@endif

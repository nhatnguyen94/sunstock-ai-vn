@use('App\Support\VnFormat', 'F')
@foreach($items as $t)
    @php
        $cls = $t['percent'] > 0 ? 'up' : ($t['percent'] < 0 ? 'dn' : 'neu');
        $icon = $t['percent'] > 0 ? 'bi-caret-up-fill' : ($t['percent'] < 0 ? 'bi-caret-down-fill' : 'bi-dash');
    @endphp
    <a class="ticker-item" href="{{ $t['kind'] === 'stock' ? url('/stock?symbol=' . $t['label']) : url('/') }}#market">
        @if($t['kind'] === 'index')<i class="bi bi-reception-4 {{ $cls }}"></i>@endif
        <span class="sym">{{ $t['label'] }}</span>
        @if($t['value'] !== null)<span class="tk-val">{{ F::number($t['value'], 2) }}</span>@endif
        <span class="{{ $cls }}"><i class="bi {{ $icon }}"></i> {{ F::percent(abs($t['percent']), 2) }}</span>
    </a>
    <span class="ticker-sep">|</span>
@endforeach
<span class="ticker-item"><i class="bi bi-clock" style="color:#94a3b8;"></i> <span style="color:#94a3b8;">Giờ GD: 9:00 – 15:00 (T2–T6)</span></span>
<span class="ticker-sep">|</span>

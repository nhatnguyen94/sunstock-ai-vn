{{-- World indices (S&P 500, Nasdaq, ...) plus world gold / silver and USD/VND: one thin strip under the Vietnamese indices. Read from the cache by WorldMarketService; absent until the first fetch. --}}
@use('Illuminate\Support\Carbon')
@use('App\Support\VnFormat', 'F')
@php $worldMarkets = $extras['world']['markets'] ?? []; @endphp
@if($worldMarkets)
<div class="mk-world" id="mkWorld" role="list" aria-label="Thị trường thế giới">
    <span class="mk-world-label"><i class="bi bi-globe2" aria-hidden="true"></i> Thế giới</span>
    @foreach($worldMarkets as $w)
        @php $wd = $dir($w['percent']); @endphp
        <span class="mk-w {{ $wd }}" role="listitem" title="{{ $w['name'] }} ({{ $w['region'] }}{{ isset($w['unit']) ? ', '.$w['unit'] : '' }}) · phiên {{ Carbon::parse($w['date'])->format('d/m') }}">
            <b>{{ $w['name'] }}</b>
            <span>{{ F::number($w['close'], $w['decimals'] ?? 2) }}</span>
            <small><i class="bi {{ $icon($w['percent']) }}" aria-hidden="true"></i> {{ F::percent(abs($w['percent']), 2) }}</small>
            @if($w['spark'])<svg class="mk-w-spark" viewBox="0 0 100 32" preserveAspectRatio="none" aria-hidden="true"><polyline points="{{ $w['spark'] }}" fill="none" vector-effect="non-scaling-stroke"/></svg>@endif
        </span>
    @endforeach
    <span class="mk-world-note">phiên gần nhất của từng thị trường</span>
</div>
@endif

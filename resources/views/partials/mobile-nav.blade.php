{{-- Bottom navigation for phones (< 768 px): the five places people open most. Everything else stays in the top menu.
     Styles: css/shared/mobile-nav.css; hide-on-scroll-down / show-on-scroll-up: js/shared/mobile-nav.js. --}}
@php
    $signedIn = auth()->check();
    $mobileNav = [
        ['label' => 'Trang chủ', 'icon' => 'house-door', 'iconOn' => 'house-door-fill', 'href' => url('/'), 'on' => request()->is('/')],
        ['label' => 'Cổ phiếu', 'icon' => 'graph-up', 'iconOn' => 'graph-up-arrow', 'href' => url('/stock'), 'on' => request()->is('stock*', 'etf*', 'company*')],
        ['label' => 'Theo dõi', 'icon' => 'star', 'iconOn' => 'star-fill', 'href' => route('watchlist.index'), 'on' => request()->is('watchlist*')],
        ['label' => 'Danh mục', 'icon' => 'briefcase', 'iconOn' => 'briefcase-fill', 'href' => route('portfolio.index'), 'on' => request()->is('portfolio*')],
        $signedIn
            ? ['label' => 'Tài khoản', 'icon' => 'person-circle', 'iconOn' => 'person-circle', 'href' => route('profile.show'), 'on' => request()->is('profile*')]
            : ['label' => 'Đăng nhập', 'icon' => 'box-arrow-in-right', 'iconOn' => 'box-arrow-in-right', 'href' => route('login'), 'on' => request()->is('login', 'register', 'forgot-password*', 'reset-password*')],
    ];
@endphp
<nav class="mnav d-md-none" id="mobileNav" aria-label="Điều hướng nhanh">
    @foreach($mobileNav as $item)
        <a class="mnav-item {{ $item['on'] ? 'is-active' : '' }}" href="{{ $item['href'] }}" @if($item['on']) aria-current="page" @endif>
            <i class="bi bi-{{ $item['on'] ? $item['iconOn'] : $item['icon'] }}" aria-hidden="true"></i>
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>

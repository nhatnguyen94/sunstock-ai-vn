{{-- Global command palette (Ctrl+K): the dialog is built by js/shared/palette.js; the page list below is server-side so it
     follows the visitor's state (a guest sees Đăng nhập, a signed-in user sees their account). Styles: css/shared/palette.css. --}}
@php
    $paletteItem = fn (string $title, string $icon, string $url, string $keywords = '') => ['title' => $title, 'icon' => $icon, 'url' => $url, 'keywords' => $keywords, 'group' => 'Trang'];
    $palettePages = [
        $paletteItem('Trang chủ', 'house-door', route('home', [], false), 'home tong quan thi truong ban do nhiet'),
        $paletteItem('Tra cứu cổ phiếu', 'search', route('stock.index', [], false), 'stock ma co phieu bieu do'),
        $paletteItem('So sánh cổ phiếu', 'intersect', route('stock.compare', [], false), 'compare so sanh'),
        $paletteItem('Stock Screener', 'funnel', route('stock.screener', [], false), 'loc co phieu screener pe roe'),
        $paletteItem('Quỹ ETF', 'bar-chart-steps', route('etf.index', [], false), 'etf quy hoan doi'),
        $paletteItem('Quỹ mở', 'pie-chart', route('funds.index', [], false), 'quy mo fund nav'),
        $paletteItem('Giá vàng', 'coin', route('gold.index', [], false), 'vang sjc btmc gold'),
        $paletteItem('Tỷ giá ngoại tệ', 'currency-exchange', route('exchange-rate.index', [], false), 'ty gia usd vcb ngoai te'),
        $paletteItem('Tin tức', 'newspaper', route('news.index', [], false), 'tin tuc news'),
        $paletteItem('Danh sách theo dõi', 'star', route('watchlist.index', [], false), 'watchlist theo doi yeu thich'),
        $paletteItem('Danh mục đầu tư', 'briefcase', route('portfolio.index', [], false), 'portfolio danh muc lai lo'),
    ];
    $palettePages[] = auth()->check()
        ? $paletteItem('Thông tin cá nhân', 'person-circle', route('profile.show', [], false), 'profile tai khoan ho so')
        : $paletteItem('Đăng nhập', 'box-arrow-in-right', route('login', [], false), 'login dang nhap');
    if (! auth()->check()) {
        $palettePages[] = $paletteItem('Đăng ký tài khoản', 'person-plus', route('register', [], false), 'register dang ky tao tai khoan');
    }
@endphp
<script type="application/json" id="paletteData">@json(['pages' => $palettePages])</script>

<div class="cp" id="cmdPalette" hidden role="dialog" aria-modal="true" aria-label="Tìm nhanh">
    <div class="cp-backdrop" data-cp-close></div>
    <div class="cp-panel">
        <div class="cp-search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input id="cpInput" type="text" placeholder="Tìm mã cổ phiếu, trang, hoặc hỏi AI…" autocomplete="off" spellcheck="false"
                   role="combobox" aria-expanded="true" aria-controls="cpList" aria-autocomplete="list" aria-label="Tìm nhanh">
            <kbd>Esc</kbd>
        </div>
        <ul class="cp-list" id="cpList" role="listbox" aria-label="Kết quả"></ul>
        <div class="cp-foot">
            <span><kbd>↑</kbd><kbd>↓</kbd> chọn</span>
            <span><kbd>↵</kbd> mở</span>
            <span class="cp-foot-r"><kbd>Ctrl</kbd><kbd>K</kbd> bật/tắt</span>
        </div>
    </div>
</div>

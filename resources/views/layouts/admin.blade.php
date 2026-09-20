@php
    use Illuminate\Support\Facades\Cache;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Gate;

    $adUser = Auth::user();

    // Failed jobs badge on the queue link (cheap, cached 30 s, never breaks the page)
    $failedJobs = 0;
    if (Gate::allows('manage-queue')) {
        try { $failedJobs = (int) Cache::remember('admin:failed-jobs-count', 30, fn () => DB::table('failed_jobs')->count()); } catch (\Throwable) { $failedJobs = 0; }
    }

    // One navigation definition drives the sidebar AND the command palette
    $navGroups = [
        ['label' => 'Tổng quan', 'items' => array_filter([
            ['title' => 'Dashboard', 'icon' => 'ti-layout-dashboard', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard'],
            Gate::allows('view-timeline') ? ['title' => 'Timeline', 'icon' => 'ti-timeline-event', 'route' => 'admin.timeline', 'active' => 'admin.timeline*', 'keywords' => 'hoạt động nhật ký log'] : null,
        ])],
        ['label' => 'Hệ thống', 'items' => array_filter([
            Gate::allows('manage-users') ? ['title' => 'Quản lý Users', 'icon' => 'ti-users', 'route' => 'admin.users.index', 'active' => 'admin.users*', 'keywords' => 'người dùng tài khoản email'] : null,
            Gate::allows('manage-roles') ? ['title' => 'Vai trò', 'icon' => 'ti-shield-lock', 'route' => 'admin.roles.index', 'active' => 'admin.roles*', 'keywords' => 'role phân quyền'] : null,
            Gate::allows('manage-permissions') ? ['title' => 'Quyền hạn', 'icon' => 'ti-key', 'route' => 'admin.permissions.index', 'active' => 'admin.permissions*', 'keywords' => 'permission'] : null,
            Gate::allows('manage-queue') ? ['title' => 'Giám sát Queue', 'icon' => 'ti-activity-heartbeat', 'route' => 'admin.queue.index', 'active' => 'admin.queue*', 'count' => $failedJobs, 'keywords' => 'job hàng đợi redis failed'] : null,
        ])],
        ['label' => 'Nội dung & dữ liệu', 'items' => Gate::allows('manage-features') ? [
            ['title' => 'Quản lý Stock', 'icon' => 'ti-chart-candle', 'route' => 'admin.stocks.index', 'active' => 'admin.stocks*', 'keywords' => 'cổ phiếu mã giá'],
            ['title' => 'Quản lý News', 'icon' => 'ti-news', 'route' => 'admin.news.index', 'active' => 'admin.news.*', 'keywords' => 'tin tức bài viết rss'],
            ['title' => 'Danh mục Tin tức', 'icon' => 'ti-tags', 'route' => 'admin.news-categories.index', 'active' => 'admin.news-categories*', 'keywords' => 'category'],
            ['title' => 'Quản lý Portfolio', 'icon' => 'ti-briefcase', 'route' => 'admin.portfolios.index', 'active' => 'admin.portfolios*', 'keywords' => 'danh mục đầu tư'],
            ['title' => 'Sync Status', 'icon' => 'ti-refresh-dot', 'route' => 'admin.sync-status', 'active' => 'admin.sync-status*', 'keywords' => 'đồng bộ dữ liệu nguồn'],
        ] : []],
    ];
    $navGroups = array_values(array_filter($navGroups, fn ($g) => count($g['items'])));

    $paletteItems = [];
    foreach ($navGroups as $g) {
        foreach ($g['items'] as $it) {
            $paletteItems[] = ['title' => $it['title'], 'group' => $g['label'], 'icon' => $it['icon'], 'href' => route($it['route']), 'keywords' => $it['keywords'] ?? ''];
        }
    }
    $paletteItems[] = ['title' => 'Hồ sơ cá nhân', 'group' => 'Tài khoản', 'icon' => 'ti-user-circle', 'href' => route('profile.show'), 'keywords' => 'profile'];
    $paletteItems[] = ['title' => 'Đổi mật khẩu', 'group' => 'Tài khoản', 'icon' => 'ti-lock', 'href' => route('admin.account.edit'), 'keywords' => 'password'];
    $paletteItems[] = ['title' => 'Xem website', 'group' => 'Liên kết', 'icon' => 'ti-external-link', 'href' => route('home'), 'keywords' => 'frontend trang chủ'];

    $initial = mb_strtoupper(mb_substr($adUser->name, 0, 1));
    $roleNames = $adUser->roles->pluck('display_name')->filter()->implode(', ');
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="color-scheme" content="light dark">
  <title>@yield('title', 'Admin') · Sun Stock AI</title>
  {{-- Apply the saved theme / sidebar state BEFORE first paint (no light flash, no layout jump) --}}
  <script>
    (function () {
      try {
        var t = localStorage.getItem('tabler-theme') || 'auto';
        var dark = t === 'dark' || (t === 'auto' && matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
        if (localStorage.getItem('ad-sidebar') === 'collapsed') document.documentElement.classList.add('sidebar-collapsed');
      } catch (e) {}
    })();
  </script>
  @vite('resources/frontend/css/admin/app.css')
  @stack('styles')
</head>
<body class="antialiased">
<div class="page">

  {{-- ===== SIDEBAR ===== --}}
  <aside class="navbar navbar-vertical navbar-expand-lg ad-sidebar" aria-label="Điều hướng quản trị">
    <div class="container-fluid flex-lg-column align-items-stretch" style="min-height:100%">
      <div class="d-flex align-items-center justify-content-between">
        <a href="{{ route('admin.dashboard') }}" class="ad-brand">
          <span class="ad-brand-mark"><i class="ti ti-chart-arrows-vertical"></i></span>
          <span class="ad-brand-text"><b>Sun Stock AI</b><small>Bảng quản trị</small></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adNav" aria-controls="adNav" aria-expanded="false" aria-label="Mở menu">
          <span class="navbar-toggler-icon"></span>
        </button>
      </div>

      <div class="collapse navbar-collapse flex-column align-items-stretch" id="adNav">
        <div class="d-lg-none px-1 pb-2">
          <button type="button" class="ad-search w-100" data-palette-open><i class="ti ti-search"></i> Tìm trang…</button>
        </div>
        @foreach($navGroups as $group)
          <div class="ad-nav-label">{{ $group['label'] }}</div>
          <ul class="navbar-nav flex-column">
            @foreach($group['items'] as $item)
              @php $active = Request::routeIs($item['active']); @endphp
              <li class="nav-item">
                <a class="nav-link {{ $active ? 'active' : '' }}" href="{{ route($item['route']) }}" @if($active) aria-current="page" @endif
                   data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="{{ $item['title'] }}" data-bs-trigger="hover">
                  <i class="ti {{ $item['icon'] }}"></i>
                  <span>{{ $item['title'] }}</span>
                  @if(!empty($item['count']))<span class="ad-nav-count" title="Job thất bại">{{ $item['count'] > 99 ? '99+' : $item['count'] }}</span>@endif
                </a>
              </li>
            @endforeach
          </ul>
        @endforeach

        <div class="ad-nav-label">Tài khoản</div>
        <ul class="navbar-nav flex-column">
          <li class="nav-item"><a class="nav-link" href="{{ route('profile.show') }}"><i class="ti ti-user-circle"></i><span>Hồ sơ cá nhân</span></a></li>
          <li class="nav-item"><a class="nav-link {{ Request::routeIs('admin.account*') ? 'active' : '' }}" href="{{ route('admin.account.edit') }}"><i class="ti ti-lock"></i><span>Đổi mật khẩu</span></a></li>
          <li class="nav-item"><a class="nav-link" href="{{ route('home') }}" target="_blank" rel="noopener"><i class="ti ti-external-link"></i><span>Xem website</span></a></li>
        </ul>

        <div class="ad-sidebar-foot">
          <span class="ad-avatar">{{ $initial }}</span>
          <div class="ad-me"><b>{{ $adUser->name }}</b><small>{{ $roleNames ?: 'Quản trị' }}</small></div>
          <button type="button" class="ad-icon-btn ad-collapse-btn d-none d-lg-inline-grid" data-sidebar-toggle title="Thu gọn / mở rộng menu" aria-label="Thu gọn menu"><i class="ti ti-layout-sidebar-left-collapse"></i></button>
        </div>
      </div>
    </div>
  </aside>

  {{-- ===== MAIN ===== --}}
  <div class="page-wrapper">

    <header class="ad-topbar d-print-none">
      <div class="container-xl">
        <button type="button" class="ad-search d-none d-lg-inline-flex" data-palette-open aria-label="Tìm trang hoặc lệnh">
          <i class="ti ti-search"></i><span>Tìm trang, chức năng…</span><kbd>Ctrl K</kbd>
        </button>
        <div class="ms-auto d-flex align-items-center gap-1">
          <a href="{{ route('home') }}" class="ad-icon-btn" target="_blank" rel="noopener" title="Xem website" aria-label="Xem website"><i class="ti ti-world"></i></a>
          <div class="dropdown">
            <button type="button" class="ad-icon-btn" data-bs-toggle="dropdown" aria-label="Giao diện sáng / tối" title="Giao diện"><i class="ti ti-sun" data-theme-icon></i></button>
            <div class="dropdown-menu dropdown-menu-end">
              <button class="dropdown-item" type="button" data-theme-choice="light"><i class="ti ti-sun me-2"></i>Sáng</button>
              <button class="dropdown-item" type="button" data-theme-choice="dark"><i class="ti ti-moon me-2"></i>Tối</button>
              <button class="dropdown-item" type="button" data-theme-choice="auto"><i class="ti ti-device-desktop me-2"></i>Theo hệ thống</button>
            </div>
          </div>
          <div class="dropdown">
            <a href="#" class="ad-user" data-bs-toggle="dropdown" aria-label="Menu tài khoản">
              <span class="ad-avatar">{{ $initial }}</span>
              <span class="ad-me d-none d-xl-block"><b>{{ $adUser->name }}</b><small>{{ $roleNames ?: 'Quản trị' }}</small></span>
              <i class="ti ti-chevron-down text-secondary d-none d-xl-inline"></i>
            </a>
            <div class="dropdown-menu dropdown-menu-end" style="min-width:13rem">
              <div class="px-2 py-2 mb-1 border-bottom"><div class="fw-bold">{{ $adUser->name }}</div><div class="text-secondary small">{{ $adUser->email }}</div></div>
              <a href="{{ route('profile.show') }}" class="dropdown-item"><i class="ti ti-user-circle me-2"></i>Hồ sơ cá nhân</a>
              <a href="{{ route('admin.account.edit') }}" class="dropdown-item"><i class="ti ti-lock me-2"></i>Đổi mật khẩu</a>
              <a href="{{ route('home') }}" class="dropdown-item"><i class="ti ti-arrow-back-up me-2"></i>Quay về Frontend</a>
              <div class="dropdown-divider"></div>
              <form action="{{ route('admin.logout') }}" method="POST">
                @csrf
                <button type="submit" class="dropdown-item text-danger"><i class="ti ti-logout me-2"></i>Đăng xuất</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </header>

    <div class="page-body">
      <div class="container-xl">

        {{-- Flash messages: shown as toasts by admin/app.js; the text is also in the page for no-JS / tests --}}
        @if(session('success'))<div data-flash="success" hidden>{{ session('success') }}</div>@endif
        @if(session('error'))<div data-flash="error" hidden>{{ session('error') }}</div>@endif
        @if(session('info'))<div data-flash="info" hidden>{{ session('info') }}</div>@endif
        @if(session('warning'))<div data-flash="warning" hidden>{{ session('warning') }}</div>@endif

        @hasSection('page_title')
          <div class="page-header d-print-none ad-fade-in">
            <div class="row align-items-end g-3">
              <div class="col">
                <div class="ad-crumbs">
                  <a href="{{ route('admin.dashboard') }}"><i class="ti ti-home-2"></i> Admin</a>
                  @hasSection('breadcrumbs')
                    @yield('breadcrumbs')
                  @endif
                </div>
                @hasSection('page_pretitle')<div class="page-pretitle">@yield('page_pretitle')</div>@endif
                <h1 class="page-title mb-0">@yield('page_title')</h1>
              </div>
              <div class="col-auto ms-auto d-print-none d-flex gap-2 flex-wrap">
                @yield('page_actions')
              </div>
            </div>
          </div>
        @endif

        @yield('content')

      </div>
    </div>

    <footer class="footer footer-transparent d-print-none pb-4">
      <div class="container-xl d-flex justify-content-between flex-wrap gap-2 small text-secondary">
        <span>&copy; {{ date('Y') }} Sun Stock AI · Bảng quản trị</span>
        <span><kbd>Ctrl</kbd> + <kbd>K</kbd> để tìm nhanh trang &middot; <a href="{{ route('home') }}" class="link-secondary">Về trang web</a></span>
      </div>
    </footer>
  </div>
</div>

{{-- Toasts --}}
<div class="ad-toasts" id="adToasts" aria-live="polite"></div>

{{-- Confirm dialog (window.adConfirm / [data-confirm]) --}}
<div class="modal modal-blur fade" id="adConfirm" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-body text-center py-4">
        <i class="ti ti-help-circle ad-confirm-icon" data-confirm-icon style="font-size:2.6rem"></i>
        <h3 class="mt-2 mb-1" data-confirm-title>Xác nhận</h3>
        <div class="text-secondary" data-confirm-message></div>
      </div>
      <div class="modal-footer justify-content-center border-0 pt-0 pb-4 gap-2">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="button" class="btn btn-danger" data-confirm-ok>Đồng ý</button>
      </div>
    </div>
  </div>
</div>

{{-- Command palette --}}
<div class="ad-palette" id="adPalette" role="dialog" aria-modal="true" aria-label="Tìm nhanh">
  <div class="ad-palette-box">
    <div class="ad-palette-input"><i class="ti ti-search text-secondary"></i><input type="text" placeholder="Gõ tên trang… (không cần dấu)" autocomplete="off" aria-label="Tìm trang"></div>
    <div class="ad-palette-list"></div>
    <div class="ad-palette-foot"><span><kbd>↑</kbd> <kbd>↓</kbd> chọn</span><span><kbd>Enter</kbd> mở</span><span><kbd>Esc</kbd> đóng</span></div>
  </div>
</div>
<script type="application/json" id="adNavData">@json($paletteItems)</script>

@vite('resources/frontend/js/admin/app.js')
@stack('scripts')
</body>
</html>

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
  <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Admin') - {{ config('app.name', 'Stock App') }}</title>
  <link href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0/dist/css/tabler.min.css" rel="stylesheet"/>
  <style>
    .card-footer .pagination { margin-bottom: 0; }

    /* Sidebar sub-menus: Tabler's `.nav` is a horizontal flex row, so the items of an expanded group used
       to sit side by side and wrap. They are a vertical, indented list with a small dot bullet. */
    .navbar-vertical .nav-sub { display: flex; flex-direction: column; flex-wrap: nowrap; padding: 0.15rem 0 0.35rem; }
    .navbar-vertical .nav-sub.collapsing, .navbar-vertical .nav-sub.show { display: flex; }
    .navbar-vertical .nav-sub:not(.show):not(.collapsing) { display: none; }
    .navbar-vertical .nav-sub .nav-link {
      display: flex; align-items: center; padding: 0.45rem 1rem 0.45rem 2.6rem;
      font-size: 0.875rem; color: rgba(255, 255, 255, 0.65); border-radius: 6px; margin: 1px 0.5rem;
    }
    .navbar-vertical .nav-sub .nav-link:hover { color: #fff; background: rgba(255, 255, 255, 0.06); }
    .navbar-vertical .nav-sub .nav-link.active { color: #fff; font-weight: 600; background: rgba(66, 153, 225, 0.22); }
    .nav-link-bullet {
      flex: 0 0 auto; width: 6px; height: 6px; margin-right: 0.75rem; border-radius: 50%;
      background: currentColor; opacity: 0.45; transition: opacity .15s ease, transform .15s ease;
    }
    .nav-sub .nav-link:hover .nav-link-bullet { opacity: 0.8; }
    .nav-sub .nav-link.active .nav-link-bullet { opacity: 1; background: #4299e1; transform: scale(1.25); }

    /* Top-right account block: avatar + name over role badges, never overlapping */
    .account-toggle { display: flex; align-items: center; gap: 0.6rem; padding: 0 !important; }
    .account-meta { line-height: 1.25; text-align: left; }
    .account-meta .account-name { font-weight: 600; font-size: 0.875rem; color: var(--tblr-body-color); white-space: nowrap; }
    .account-meta .account-roles { display: flex; flex-wrap: wrap; gap: 0.25rem; margin-top: 0.15rem; }
    /* Tabler makes `.navbar .badge` position:absolute (it is meant to be a notification dot on a nav icon),
       which threw the role badge on top of the avatar. Here it is an ordinary inline label. */
    .navbar-nav .nav-link.account-toggle .account-meta .badge { position: static !important; transform: none !important; top: auto !important; right: auto !important; }
  </style>
  @stack('styles')
</head>
<body class="antialiased">
<div class="wrapper">

  {{-- ===== SIDEBAR ===== --}}
  <aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">
    <div class="container-fluid">
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-menu">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="navbar-brand navbar-brand-autodark">
        <a href="{{ route('admin.dashboard') }}" class="d-flex align-items-center text-white text-decoration-none">
          <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="28" height="28" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><line x1="4" y1="19" x2="20" y2="19"/><polyline points="4,15 8,9 12,11 16,6 20,10"/></svg>
          <span class="fw-bold fs-4">Stock Admin</span>
        </a>
      </div>
      <div class="collapse navbar-collapse" id="navbar-menu">
        <ul class="navbar-nav pt-lg-3">

          {{-- Dashboard --}}
          <li class="nav-item">
            <a class="nav-link {{ Request::routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
              <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><rect x="4" y="4" width="6" height="8" rx="1"/><rect x="4" y="16" width="6" height="4" rx="1"/><rect x="14" y="12" width="6" height="8" rx="1"/><rect x="14" y="4" width="6" height="4" rx="1"/></svg>
              </span>
              <span class="nav-link-title">Dashboard</span>
            </a>
          </li>

          {{-- Timeline --}}
          @can('view-timeline')
          <li class="nav-item">
            <a class="nav-link {{ Request::routeIs('admin.timeline*') ? 'active' : '' }}" href="{{ route('admin.timeline') }}">
              <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
              </span>
              <span class="nav-link-title">Timeline</span>
            </a>
          </li>
          @endcan

          {{-- Hệ thống — visible to anyone holding at least one of its permissions; open on any of its pages --}}
          @php
            $systemOpen   = Request::routeIs('admin.users*', 'admin.roles*', 'admin.permissions*', 'admin.queue*');
            $featuresOpen = Request::routeIs('admin.stocks*', 'admin.news*', 'admin.portfolios*', 'admin.sync-status*');
          @endphp
          @canany(['manage-users', 'manage-roles', 'manage-permissions', 'manage-queue'])
          <li class="nav-item">
            <a class="nav-link {{ $systemOpen ? '' : 'collapsed' }}"
               href="#sidebar-system" data-bs-toggle="collapse" role="button"
               aria-expanded="{{ $systemOpen ? 'true' : 'false' }}">
              <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065z"/><circle cx="12" cy="12" r="3"/></svg>
              </span>
              <span class="nav-link-title">Hệ thống</span>
            </a>
            <div class="nav nav-sub collapse {{ $systemOpen ? 'show' : '' }}" id="sidebar-system">
              @can('manage-users')
              <a class="nav-link {{ Request::routeIs('admin.users*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
                <span class="nav-link-bullet"></span>
                <span class="nav-link-title">Quản lý Users</span>
              </a>
              @endcan
              @can('manage-roles')
              <a class="nav-link {{ Request::routeIs('admin.roles*') ? 'active' : '' }}" href="{{ route('admin.roles.index') }}">
                <span class="nav-link-bullet"></span>
                <span class="nav-link-title">Vai trò</span>
              </a>
              @endcan
              @can('manage-permissions')
              <a class="nav-link {{ Request::routeIs('admin.permissions*') ? 'active' : '' }}" href="{{ route('admin.permissions.index') }}">
                <span class="nav-link-bullet"></span>
                <span class="nav-link-title">Quyền hạn</span>
              </a>
              @endcan
              @can('manage-queue')
              <a class="nav-link {{ Request::routeIs('admin.queue*') ? 'active' : '' }}" href="{{ route('admin.queue.index') }}">
                <span class="nav-link-bullet"></span>
                <span class="nav-link-title">Giám sát Queue</span>
              </a>
              @endcan
            </div>
          </li>
          @endcanany

          {{-- Tính năng --}}
          @can('manage-features')
          <li class="nav-item">
            <a class="nav-link {{ $featuresOpen ? '' : 'collapsed' }}"
               href="#sidebar-features" data-bs-toggle="collapse" role="button"
               aria-expanded="{{ $featuresOpen ? 'true' : 'false' }}">
              <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/></svg>
              </span>
              <span class="nav-link-title">Tính năng</span>
            </a>
            <div class="nav nav-sub collapse {{ $featuresOpen ? 'show' : '' }}" id="sidebar-features">
              <a class="nav-link {{ Request::routeIs('admin.stocks*') ? 'active' : '' }}" href="{{ route('admin.stocks.index') }}">
                <span class="nav-link-bullet"></span>
                <span class="nav-link-title">Quản lý Stock</span>
              </a>
              <a class="nav-link {{ Request::routeIs('admin.news.*') ? 'active' : '' }}" href="{{ route('admin.news.index') }}">
                <span class="nav-link-bullet"></span>
                <span class="nav-link-title">Quản lý News</span>
              </a>
              <a class="nav-link {{ Request::routeIs('admin.news-categories.*') ? 'active' : '' }}" href="{{ route('admin.news-categories.index') }}">
                <span class="nav-link-bullet"></span>
                <span class="nav-link-title">Danh mục Tin tức</span>
              </a>
              <a class="nav-link {{ Request::routeIs('admin.portfolios*') ? 'active' : '' }}" href="{{ route('admin.portfolios.index') }}">
                <span class="nav-link-bullet"></span>
                <span class="nav-link-title">Quản lý Portfolio</span>
              </a>
              <a class="nav-link {{ Request::routeIs('admin.sync-status*') ? 'active' : '' }}" href="{{ route('admin.sync-status') }}">
                <span class="nav-link-bullet"></span>
                <span class="nav-link-title">Sync Status</span>
              </a>
            </div>
          </li>
          @endcan

          <li class="nav-item">
            <div class="hr-text">Tài khoản</div>
          </li>

          {{-- Profile --}}
          <li class="nav-item">
            <a class="nav-link" href="{{ route('profile.show') }}">
              <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><circle cx="12" cy="7" r="4"/><path d="M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/></svg>
              </span>
              <span class="nav-link-title">Hồ sơ cá nhân</span>
            </a>
          </li>

          {{-- Change password --}}
          <li class="nav-item">
            <a class="nav-link {{ Request::routeIs('admin.account*') ? 'active' : '' }}" href="{{ route('admin.account.edit') }}">
              <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><rect x="5" y="11" width="14" height="10" rx="2"/><circle cx="12" cy="16" r="1"/><path d="M8 11v-4a4 4 0 0 1 8 0v4"/></svg>
              </span>
              <span class="nav-link-title">Đổi mật khẩu</span>
            </a>
          </li>

          {{-- Logout --}}
          <li class="nav-item">
            <form action="{{ route('admin.logout') }}" method="POST">
              @csrf
              <button type="submit" class="nav-link bg-transparent border-0 w-100 text-start">
                <span class="nav-link-icon d-md-none d-lg-inline-block">
                  <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2"/><path d="M7 12h14l-3 -3m0 6l3 -3"/></svg>
                </span>
                <span class="nav-link-title">Đăng xuất</span>
              </button>
            </form>
          </li>

        </ul>
      </div>
    </div>
  </aside>

  {{-- ===== MAIN CONTENT ===== --}}
  <div class="page-wrapper">

    {{-- Top header --}}
    <header class="navbar navbar-expand-md d-none d-lg-flex d-print-none">
      <div class="container-xl">
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb breadcrumb-dots mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
            @yield('breadcrumbs')
          </ol>
        </nav>
        <div class="navbar-nav flex-row order-md-last">
          <div class="nav-item dropdown">
            <a href="#" class="nav-link account-toggle text-reset" data-bs-toggle="dropdown" aria-label="Open user menu">
              <span class="avatar avatar-sm bg-blue-lt">{{ mb_strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}</span>
              <div class="account-meta d-none d-xl-block">
                <div class="account-name">{{ Auth::user()->name }}</div>
                <div class="account-roles">
                  @foreach(Auth::user()->roles as $role)
                    <span class="badge bg-blue-lt">{{ $role->display_name }}</span>
                  @endforeach
                </div>
              </div>
            </a>
            <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
              <a href="{{ route('profile.show') }}" class="dropdown-item">Hồ sơ cá nhân</a>
              <a href="{{ route('admin.account.edit') }}" class="dropdown-item">Đổi mật khẩu</a>
              <a href="{{ route('home') }}" class="dropdown-item">Quay về Frontend</a>
              <div class="dropdown-divider"></div>
              <form action="{{ route('admin.logout') }}" method="POST">
                @csrf
                <button type="submit" class="dropdown-item">Đăng xuất</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </header>

    {{-- Page body --}}
    <div class="page-body">
      <div class="container-xl">

        @if(session('success'))
          <div class="alert alert-success alert-dismissible mt-3" role="alert">
            <div class="d-flex">
              <div><svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><circle cx="12" cy="12" r="9"/><path d="M9 12l2 2l4 -4"/></svg></div>
              <div>{{ session('success') }}</div>
            </div>
            <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
          </div>
        @endif

        @if(session('error'))
          <div class="alert alert-danger alert-dismissible mt-3" role="alert">
            <div class="d-flex">
              <div><svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
              <div>{{ session('error') }}</div>
            </div>
            <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
          </div>
        @endif

        @hasSection('page_title')
          <div class="page-header d-print-none">
            <div class="row align-items-center">
              <div class="col">
                <div class="page-pretitle">@yield('page_pretitle')</div>
                <h2 class="page-title">@yield('page_title')</h2>
              </div>
              <div class="col-auto ms-auto d-print-none">
                @yield('page_actions')
              </div>
            </div>
          </div>
        @endif

        @yield('content')

      </div>
    </div>

    {{-- Footer --}}
    <footer class="footer footer-transparent d-print-none">
      <div class="container-xl">
        <div class="row text-center align-items-center flex-row-reverse">
          <div class="col-lg-auto ms-lg-auto">
            <ul class="list-inline list-inline-dots mb-0">
              <li class="list-inline-item"><a href="{{ route('home') }}" class="link-secondary">Frontend</a></li>
            </ul>
          </div>
          <div class="col-12 col-lg-auto mt-3 mt-lg-0">
            <ul class="list-inline list-inline-dots mb-0">
              <li class="list-inline-item">&copy; {{ date('Y') }} <a href="{{ route('home') }}" class="link-secondary">Stock App</a></li>
            </ul>
          </div>
        </div>
      </div>
    </footer>

  </div>{{-- /page-wrapper --}}
</div>{{-- /wrapper --}}

<script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0/dist/js/tabler.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.alert-dismissible').forEach(function (el) {
      setTimeout(function () { var b = el.querySelector('.btn-close'); if (b) b.click(); }, 5000);
    });
  });
</script>
@stack('scripts')
</body>
</html>
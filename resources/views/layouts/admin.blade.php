<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>@yield('title', 'Admin Dashboard') - {{ config('app.name', 'Stock App') }}</title>
    
    <!-- Tabler CSS -->
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/css/tabler.min.css" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/@tabler/icons@latest/icons-sprite.svg" rel="preload" as="image"/>
    
    <!-- Custom Admin CSS -->
    <style>
        .navbar-brand-image {
            height: 2rem;
        }
        .sidebar-item.active {
            background-color: rgba(13, 110, 253, 0.1);
            border-right: 3px solid #0d6efd;
        }
        .sidebar-item:hover {
            background-color: rgba(13, 110, 253, 0.05);
        }
        .breadcrumb-item + .breadcrumb-item::before {
            content: "›";
            color: #6c757d;
        }
        .stats-card {
            transition: all 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 25px rgba(0,0,0,0.1);
        }
    </style>
    
    @stack('styles')
</head>
<body class="layout-fluid">
    <div class="page">
        <!-- Sidebar -->
        <aside class="navbar navbar-vertical navbar-expand-lg navbar-dark">
            <div class="container-fluid">
                <!-- Brand -->
                <div class="navbar-brand navbar-brand-autodark">
                    <a href="{{ route('admin.dashboard') }}" class="navbar-brand-image">
                        <h2 class="text-white mb-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-chart-line me-2" width="32" height="32" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                <line x1="4" y1="19" x2="20" y2="19"/>
                                <polyline points="4,15 8,9 12,11 16,6 20,10"/>
                            </svg>
                            Stock Admin
                        </h2>
                    </a>
                </div>
                
                <!-- Navigation -->
                <div class="navbar-nav flex-column">
                    <!-- Dashboard -->
                    <div class="nav-item">
                        <a class="nav-link {{ Request::routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <rect x="4" y="4" width="6" height="8" rx="1"/>
                                    <rect x="4" y="16" width="6" height="4" rx="1"/>
                                    <rect x="14" y="12" width="6" height="8" rx="1"/>
                                    <rect x="14" y="4" width="6" height="4" rx="1"/>
                                </svg>
                            </span>
                            <span class="nav-link-title">Dashboard</span>
                        </a>
                    </div>

                    <!-- Timeline - Admin, Webadmin, AdminSupport -->
                    @can('view-timeline')
                    <div class="nav-item">
                        <a class="nav-link {{ Request::routeIs('admin.timeline*') ? 'active' : '' }}" href="{{ route('admin.timeline') }}">
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <circle cx="12" cy="12" r="2"/>
                                    <path d="M12 1v6m0 6v6"/>
                                    <path d="M9 9l1.5 1.5"/>
                                    <path d="M13.5 13.5l1.5 1.5"/>
                                    <path d="M9 15l1.5 -1.5"/>
                                    <path d="M13.5 10.5l1.5 -1.5"/>
                                </svg>
                            </span>
                            <span class="nav-link-title">Timeline</span>
                        </a>
                    </div>
                    @endcan

                    <!-- Hệ thống - Chỉ Admin -->
                    @can('manage-users')
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ Request::routeIs('admin.users*') ? 'active show' : '' }}" href="#navbar-system" data-bs-toggle="dropdown" data-bs-auto-close="false" role="button" aria-expanded="{{ Request::routeIs('admin.users*') ? 'true' : 'false' }}">
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </span>
                            <span class="nav-link-title">Hệ thống</span>
                        </a>
                        <div class="dropdown-menu {{ Request::routeIs('admin.users*') ? 'show' : '' }}">
                            <a class="dropdown-item {{ Request::routeIs('admin.users.index') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon dropdown-item-icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <circle cx="12" cy="7" r="4"/>
                                    <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"/>
                                </svg>
                                Quản lý Users
                            </a>
                        </div>
                    </div>
                    @endcan

                    <!-- Tính năng - Admin và AdminSupport -->
                    @can('manage-features')
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ Request::routeIs('admin.stocks*', 'admin.news*', 'admin.portfolios*') ? 'active show' : '' }}" href="#navbar-features" data-bs-toggle="dropdown" data-bs-auto-close="false" role="button" aria-expanded="{{ Request::routeIs('admin.stocks*', 'admin.news*', 'admin.portfolios*') ? 'true' : 'false' }}">
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <rect x="4" y="4" width="6" height="6" rx="1"/>
                                    <rect x="14" y="4" width="6" height="6" rx="1"/>
                                    <rect x="4" y="14" width="6" height="6" rx="1"/>
                                    <rect x="14" y="14" width="6" height="6" rx="1"/>
                                </svg>
                            </span>
                            <span class="nav-link-title">Tính năng</span>
                        </a>
                        <div class="dropdown-menu {{ Request::routeIs('admin.stocks*', 'admin.news*', 'admin.portfolios*') ? 'show' : '' }}">
                            <a class="dropdown-item {{ Request::routeIs('admin.stocks*') ? 'active' : '' }}" href="{{ route('admin.stocks.index') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon dropdown-item-icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <polyline points="3,17 9,11 13,15 21,7"/>
                                    <polyline points="14,7 21,7 21,14"/>
                                </svg>
                                Quản lý Stock
                            </a>
                            <a class="dropdown-item {{ Request::routeIs('admin.news*') ? 'active' : '' }}" href="{{ route('admin.news.index') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon dropdown-item-icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <rect x="3" y="5" width="18" height="14" rx="2"/>
                                    <line x1="7" y1="15" x2="15" y2="15"/>
                                    <line x1="7" y1="11" x2="17" y2="11"/>
                                    <line x1="7" y1="7" x2="13" y2="7"/>
                                </svg>
                                Quản lý News
                            </a>
                            <a class="dropdown-item {{ Request::routeIs('admin.portfolios*') ? 'active' : '' }}" href="{{ route('admin.portfolios.index') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon dropdown-item-icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <rect x="3" y="4" width="18" height="12" rx="1"/>
                                    <line x1="7" y1="20" x2="17" y2="20"/>
                                    <line x1="9" y1="16" x2="15" y2="16"/>
                                </svg>
                                Quản lý Portfolio
                            </a>
                        </div>
                    </div>
                    @endcan

                    <!-- Divider -->
                    <div class="hr-text">Tài khoản</div>

                    <!-- User Profile -->
                    <div class="nav-item">
                        <a class="nav-link" href="{{ route('profile.show') }}">
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="3"/>
                                    <path d="M12 1v6m0 6v6"/>
                                </svg>
                            </span>
                            <span class="nav-link-title">Hồ sơ cá nhân</span>
                        </a>
                    </div>

                    <!-- Logout -->
                    <div class="nav-item">
                        <form action="{{ route('admin.logout') }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="nav-link bg-transparent border-0 w-100 text-start">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2"/>
                                        <path d="M7 12h14l-3 -3m0 6l3 -3"/>
                                    </svg>
                                </span>
                                <span class="nav-link-title">Đăng xuất</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </aside>
        
        <!-- Main Content -->
        <div class="page-wrapper">
            <!-- Header -->
            <header class="navbar navbar-expand-md navbar-light d-none d-lg-flex d-print-none">
                <div class="container-xl">
                    <!-- Breadcrumb -->
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb breadcrumb-dots my-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
                            @yield('breadcrumbs')
                        </ol>
                    </nav>

                    <!-- Right Header -->
                    <div class="navbar-nav flex-row order-md-last">
                        <!-- User Info -->
                        <div class="nav-item dropdown">
                            <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown" aria-label="Open user menu">
                                <span class="avatar avatar-sm">
                                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                </span>
                                <div class="d-none d-xl-block ps-2">
                                    <div>{{ Auth::user()->name }}</div>
                                    <div class="mt-1 small text-muted">
                                        @foreach(Auth::user()->roles as $role)
                                            <span class="badge badge-outline text-blue">{{ $role->display_name }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                                <a href="{{ route('profile.show') }}" class="dropdown-item">Hồ sơ cá nhân</a>
                                <a href="{{ route('home') }}" class="dropdown-item">Quay về Frontend</a>
                                <div class="dropdown-divider"></div>
                                <form action="{{ route('admin.logout') }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="dropdown-item">Đăng xuất</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="page-body">
                <div class="container-xl">
                    <!-- Flash Messages -->
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible" role="alert">
                            <div class="d-flex">
                                <div>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <circle cx="12" cy="12" r="9"/>
                                        <path d="M9 12l2 2l4 -4"/>
                                    </svg>
                                </div>
                                <div>{{ session('success') }}</div>
                            </div>
                            <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible" role="alert">
                            <div class="d-flex">
                                <div>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <circle cx="12" cy="12" r="9"/>
                                        <line x1="12" y1="8" x2="12" y2="12"/>
                                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                                    </svg>
                                </div>
                                <div>{{ session('error') }}</div>
                            </div>
                            <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
                        </div>
                    @endif

                    <!-- Page Title -->
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

                    <!-- Main Content -->
                    @yield('content')
                </div>
            </div>

            <!-- Footer -->
            <footer class="footer footer-transparent d-print-none">
                <div class="container-xl">
                    <div class="row text-center align-items-center flex-row-reverse">
                        <div class="col-lg-auto ms-lg-auto">
                            <ul class="list-inline list-inline-dots mb-0">
                                <li class="list-inline-item">
                                    <a href="{{ route('home') }}" class="link-secondary">Frontend</a>
                                </li>
                                <li class="list-inline-item">
                                    <a href="#" class="link-secondary">Hỗ trợ</a>
                                </li>
                            </ul>
                        </div>
                        <div class="col-12 col-lg-auto mt-3 mt-lg-0">
                            <ul class="list-inline list-inline-dots mb-0">
                                <li class="list-inline-item">
                                    © {{ date('Y') }}
                                    <a href="{{ route('home') }}" class="link-secondary">Stock App</a>.
                                    All rights reserved.
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- Tabler JS -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>
    
    <!-- Custom Admin JS -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto dismiss alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const closeBtn = alert.querySelector('.btn-close');
                    if (closeBtn) closeBtn.click();
                }, 5000);
            });
        });
    </script>
    
    @stack('scripts')
</body>
</html>
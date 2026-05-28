<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Sun Stock AI – Ứng dụng theo dõi, phân tích cổ phiếu Việt Nam thông minh với AI. Xem biểu đồ giá, tỷ giá, tin tức thị trường realtime.">
    <meta property="og:title" content="Sun Stock AI – Vietnam's Smart Stock App">
    <meta property="og:description" content="Phân tích cổ phiếu Việt Nam thông minh cùng AI. Tỷ giá, biểu đồ, danh mục đầu tư.">
    <meta property="og:type" content="website">
    <title>@yield('title', 'Sun Stock AI – Vietnam\'s Smart Stock App')</title>
    <!-- Preconnect for speed -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- AOS – Animate On Scroll -->
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css">
    <!-- NProgress – page loading indicator -->
    <link rel="stylesheet" href="https://unpkg.com/nprogress@0.2.0/nprogress.css">
    <style>
        :root {
            --primary-blue: #2563eb;
            --secondary-blue: #3b82f6;
            --light-blue: #eff6ff;
            --dark-blue: #1e40af;
            --success-green: #10b981;
            --danger-red: #ef4444;
            --warning-orange: #f59e0b;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;
            --bg-light: #f8fafc;
            --bg-dark: #1f2937;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: var(--text-primary);
            line-height: 1.6;
        }

        /* Header Navbar */
        .main-navbar {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            box-shadow: var(--shadow-lg);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding: 0.75rem 0;
        }

        .navbar-brand {
            font-size: 1.75rem;
            font-weight: 700;
            color: white !important;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .navbar-brand:hover {
            color: rgba(255,255,255,0.9) !important;
            text-decoration: none;
        }

        .navbar-brand .brand-icon {
            font-size: 2rem;
            background: linear-gradient(45deg, #fbbf24, #f59e0b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .navbar-nav .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            padding: 0.75rem 1.25rem !important;
            border-radius: 8px;
            transition: all 0.3s ease;
            margin: 0 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .navbar-nav .nav-link:hover {
            background: rgba(255,255,255,0.15);
            color: white !important;
            transform: translateY(-1px);
        }

        .navbar-nav .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white !important;
        }

        .navbar-toggler {
            border: none;
            padding: 0.375rem 0.5rem;
        }

        .navbar-toggler:focus {
            box-shadow: none;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            padding: 3rem 0;
            margin-bottom: 3rem;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><polygon fill="rgba(255,255,255,0.05)" points="0,1000 1000,0 1000,1000"/></svg>');
            pointer-events: none;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            text-align: center;
            color: white;
        }

        .hero-title {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .hero-subtitle {
            font-size: 1.25rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        /* Search Container */
        .search-container {
            background: white;
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: var(--shadow-xl);
            margin: -2rem auto 3rem;
            max-width: 900px;
            border: 1px solid var(--border-color);
            position: relative;
            z-index: 3;
        }

        .search-form {
            display: flex;
            gap: 12px;
            align-items: stretch;
        }

        .search-input-wrapper {
            flex: 1;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 1.25rem 1.5rem 1.25rem 3.5rem;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            background: white;
            font-weight: 500;
        }

        .search-input:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            outline: none;
        }

        .search-icon {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 1.25rem;
        }

        .search-btn {
            padding: 1.25rem 2.5rem;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.1rem;
            min-width: 140px;
            justify-content: center;
        }

        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
        }

        /* Cards */
        .custom-card {
            background: white !important;
            color: var(--text-primary) !important;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .custom-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }

        .card-header-custom {
            background: linear-gradient(135deg, var(--light-blue), white);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-color);
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-body-custom {
            padding: 2rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .stat-card {
            background: linear-gradient(135deg, white, var(--light-blue));
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-blue), var(--secondary-blue));
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            font-size: 3rem;
            color: var(--primary-blue);
            margin-bottom: 1rem;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 1rem;
            font-weight: 500;
        }

        /* Tables */
        .data-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
        }

        .data-table table {
            margin: 0;
            width: 100%;
        }

        .data-table thead th {
            background: linear-gradient(135deg, var(--light-blue), #f1f5f9);
            border: none;
            font-weight: 600;
            color: var(--text-primary);
            padding: 1.25rem;
            text-align: center;
        }

        .data-table tbody td {
            border: none;
            padding: 1rem 1.25rem;
            vertical-align: middle;
            text-align: center;
        }

        .data-table tbody tr:hover {
            background: var(--bg-light);
        }

        /* Price Colors */
        .price-up { 
            color: var(--success-green); 
            font-weight: 600;
        }
        
        .price-down { 
            color: var(--danger-red); 
            font-weight: 600;
        }
        
        .price-neutral { 
            color: var(--text-secondary); 
            font-weight: 600;
        }

        /* Badges */
        .badge-custom {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.875rem;
        }

        .badge-primary { 
            background: var(--primary-blue); 
            color: white; 
        }
        
        .badge-success { 
            background: var(--success-green); 
            color: white; 
        }
        
        .badge-warning { 
            background: var(--warning-orange); 
            color: white; 
        }

        /* Buttons */
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            border: none;
            padding: 0.875rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.3);
            color: white;
            text-decoration: none;
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 18px; height: 18px;
            border: 2px solid rgba(37, 99, 235, 0.25);
            border-radius: 50%;
            border-top-color: var(--primary-blue);
            animation: spin 0.8s ease-in-out infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr; gap: 1rem; }
            .custom-card { margin: 0 0 1.5rem; }
        }

        /* ── NProgress override ─────────────────────────── */
        #nprogress .bar { background: #fbbf24 !important; height: 3px !important; }
        #nprogress .peg  { box-shadow: 0 0 10px #fbbf24, 0 0 5px #fbbf24 !important; }
        #nprogress .spinner-icon { border-top-color: #fbbf24 !important; border-left-color: #fbbf24 !important; }

        /* ── Market Ticker Tape ─────────────────────────── */
        .ticker-wrap {
            background: #0f172a;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            overflow: hidden;
            padding: 8px 0;
            white-space: nowrap;
        }
        .ticker-content {
            display: inline-block;
            animation: ticker-scroll 60s linear infinite;
        }
        .ticker-content:hover { animation-play-state: paused; }
        @keyframes ticker-scroll {
            0%   { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        .ticker-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin: 0 2rem;
            font-size: 0.82rem;
            font-weight: 600;
            color: rgba(255,255,255,0.85);
            cursor: default;
        }
        .ticker-item .sym { color: #fbbf24; }
        .ticker-item .up  { color: #34d399; }
        .ticker-item .dn  { color: #f87171; }
        .ticker-item .neu { color: #94a3b8; }
        .ticker-sep { color: rgba(255,255,255,0.2); margin: 0 1.5rem; }

        /* ── Back-to-top ────────────────────────────────── */
        #backToTop {
            position: fixed; bottom: 110px; right: 30px; z-index: 9990;
            width: 44px; height: 44px;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white; border: none; border-radius: 50%;
            box-shadow: var(--shadow-lg);
            font-size: 1.1rem;
            cursor: pointer;
            opacity: 0; pointer-events: none;
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
        #backToTop.visible { opacity: 1; pointer-events: auto; }
        #backToTop:hover { transform: translateY(-3px); }

        /* ── Toast Notifications ────────────────────────── */
        #toastContainer {
            position: fixed; top: 80px; right: 20px; z-index: 99999;
            display: flex; flex-direction: column; gap: 10px;
        }
        .toast-item {
            background: white; color: var(--text-primary);
            border-radius: 12px; padding: 1rem 1.25rem;
            box-shadow: var(--shadow-xl); border-left: 4px solid var(--primary-blue);
            display: flex; align-items: center; gap: 10px;
            font-weight: 500; font-size: 0.9rem;
            min-width: 280px; max-width: 360px;
            animation: toastIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
        }
        .toast-item.success { border-color: var(--success-green); }
        .toast-item.error   { border-color: var(--danger-red); }
        .toast-item.warning { border-color: var(--warning-orange); }
        @keyframes toastIn {
            from { opacity: 0; transform: translateX(100px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        /* ── AI Chat Pulse ──────────────────────────────── */
        #aiChatOpenBtn {
            position: relative;
        }
        .ai-pulse {
            position: absolute; top: 0; right: 0;
            width: 14px; height: 14px;
            background: #34d399; border-radius: 50%;
            border: 2px solid white;
        }
        .ai-pulse::before {
            content: '';
            position: absolute; inset: -4px;
            border-radius: 50%;
            background: rgba(52, 211, 153, 0.5);
            animation: pulse-ring 1.5s ease-out infinite;
        }
        @keyframes pulse-ring {
            0%   { transform: scale(0.8); opacity: 1; }
            100% { transform: scale(2);   opacity: 0; }
        }

        /* ── Footer redesign ────────────────────────────── */
        .custom-footer {
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            color: white;
            margin-top: 5rem;
            padding: 0;
            border-top: 3px solid var(--primary-blue);
        }
        .footer-top {
            padding: 4rem 0 3rem;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .footer-brand-name {
            font-size: 1.6rem; font-weight: 800;
            background: linear-gradient(135deg, #60a5fa, #a78bfa);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .footer-brand-tagline {
            color: rgba(255,255,255,0.55); font-size: 0.9rem; margin-top: 0.25rem;
        }
        .footer-col-title {
            color: #94a3b8; font-size: 0.75rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 1.25rem;
        }
        .footer-link {
            color: rgba(255,255,255,0.7); text-decoration: none;
            display: flex; align-items: center; gap: 8px;
            padding: 0.3rem 0; font-size: 0.9rem;
            transition: color 0.2s ease;
        }
        .footer-link:hover { color: #60a5fa; text-decoration: none; }
        .footer-link i { font-size: 0.9rem; width: 18px; }
        .footer-social {
            display: flex; gap: 10px; margin-top: 1.5rem;
        }
        .footer-social-btn {
            width: 38px; height: 38px;
            background: rgba(255,255,255,0.07); border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: rgba(255,255,255,0.7); text-decoration: none;
            transition: all 0.25s ease; font-size: 1rem;
        }
        .footer-social-btn:hover {
            background: var(--primary-blue); color: white;
            transform: translateY(-2px); text-decoration: none;
        }
        .footer-bottom {
            padding: 1.25rem 0;
            color: rgba(255,255,255,0.4); font-size: 0.8rem;
        }
        .footer-badge {
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px; padding: 0.25rem 0.75rem;
            font-size: 0.75rem; color: rgba(255,255,255,0.5);
        }

        /* ── Announcement bar ───────────────────────────── */
        .announcement-bar {
            background: linear-gradient(90deg, #7c3aed, #2563eb, #0891b2);
            background-size: 200% 100%;
            animation: gradient-shift 6s ease infinite;
            color: white; text-align: center;
            padding: 0.5rem 1rem; font-size: 0.82rem; font-weight: 500;
        }
        @keyframes gradient-shift {
            0%   { background-position: 0% 50%; }
            50%  { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .announcement-bar a { color: #fbbf24; text-decoration: none; font-weight: 600; }

        /* ── Improved nav user dropdown ─────────────────── */
        .user-avatar {
            width: 32px; height: 32px; border-radius: 50%;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            display: inline-flex; align-items: center; justify-content: center;
            font-weight: 700; color: white; font-size: 0.85rem;
            margin-right: 6px; flex-shrink: 0;
        }
        .dropdown-menu {
            border-radius: 12px !important;
            box-shadow: var(--shadow-xl) !important;
            border: 1px solid var(--border-color) !important;
            padding: 0.5rem !important;
            min-width: 200px !important;
        }
        .dropdown-item {
            border-radius: 8px !important;
            padding: 0.625rem 1rem !important;
            font-weight: 500 !important;
            transition: background 0.2s ease !important;
        }
        .dropdown-item:hover { background: var(--light-blue) !important; color: var(--primary-blue) !important; }
    </style>
    @yield('head')
</head>
<body>
    <!-- Toast container -->
    <div id="toastContainer"></div>

    <!-- Announcement Bar -->
    <div class="announcement-bar d-none d-md-block">
        <i class="bi bi-stars" style="margin-right:6px;"></i>
        Sun Stock AI · Phân tích cổ phiếu thông minh · Dữ liệu từ <a href="#">VNStock</a> &amp; Vietcombank &nbsp;·&nbsp;
        <i class="bi bi-shield-check" style="color:#34d399;"></i> Dữ liệu được cập nhật tự động hàng ngày
    </div>

    <!-- Main Navigation -->
    <nav class="navbar navbar-expand-lg main-navbar">
        <div class="container">
            <a class="navbar-brand" href="{{ url('/') }}">
                <i class="bi bi-graph-up-arrow brand-icon"></i>
                Sun Stock AI
            </a>
            
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <i class="bi bi-list text-white" style="font-size: 1.5rem;"></i>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto align-items-lg-center">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->is('/') ? 'active' : '' }}" href="{{ url('/') }}">
                            <i class="bi bi-house-door"></i> Trang chủ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->is('stock*') ? 'active' : '' }}" href="{{ url('/stock') }}">
                            <i class="bi bi-graph-up"></i> Cổ phiếu
                        </a>
                    </li>
                    @auth
                        <li class="nav-item">
                            <a class="nav-link {{ request()->is('portfolio*') ? 'active' : '' }}" href="{{ route('portfolio.index') }}">
                                <i class="bi bi-briefcase"></i> Danh mục
                            </a>
                        </li>
                    @endauth
                    <li class="nav-item">
                        <a class="nav-link {{ request()->is('exchange-rate*') ? 'active' : '' }}" href="{{ url('/exchange-rate') }}">
                            <i class="bi bi-currency-exchange"></i> Tỷ giá
                        </a>
                    </li>
                    
                    @guest
                        <li class="nav-item ml-lg-2">
                            <a class="nav-link" href="{{ route('login') }}"
                               style="background:rgba(255,255,255,0.15); border-radius:8px;">
                                <i class="bi bi-box-arrow-in-right"></i> Đăng nhập
                            </a>
                        </li>
                        <li class="nav-item ml-lg-1">
                            <a href="{{ route('register') }}"
                               style="background:#fbbf24; color:#1e3a5f !important; border-radius:8px; padding:0.5rem 1rem; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:6px; font-size:0.95rem; transition:all 0.2s ease;"
                               onmouseover="this.style.background='#f59e0b'"
                               onmouseout="this.style.background='#fbbf24'">
                                <i class="bi bi-person-plus"></i> Đăng ký
                            </a>
                        </li>
                    @else
                        <li class="nav-item dropdown ml-lg-2">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" data-toggle="dropdown">
                                <span class="user-avatar">{{ strtoupper(substr(Auth::user()->profile->username ?? Auth::user()->name, 0, 1)) }}</span>
                                {{ Auth::user()->profile->username ?? Auth::user()->name }}
                            </a>
                            <div class="dropdown-menu dropdown-menu-right">
                                <div class="px-3 py-2 mb-1" style="border-bottom:1px solid var(--border-color);">
                                    <div style="font-weight:600; font-size:0.9rem; color:var(--text-primary);">{{ Auth::user()->profile->username ?? Auth::user()->name }}</div>
                                    <div style="font-size:0.8rem; color:var(--text-secondary);">{{ Auth::user()->email }}</div>
                                </div>
                                <a class="dropdown-item" href="{{ route('profile.show') }}">
                                    <i class="bi bi-person text-primary"></i> Thông tin cá nhân
                                </a>
                                <a class="dropdown-item" href="{{ route('portfolio.index') }}">
                                    <i class="bi bi-briefcase text-primary"></i> Danh mục đầu tư
                                </a>
                                <div class="dropdown-divider"></div>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="dropdown-item text-danger" type="submit">
                                        <i class="bi bi-box-arrow-right"></i> Đăng xuất
                                    </button>
                                </form>
                            </div>
                        </li>
                    @endguest
                </ul>
            </div>
        </div>
    </nav>

    <!-- Market Ticker Tape -->
    <div class="ticker-wrap">
        <div class="ticker-content" id="tickerContent">
            <!-- Duplicated for seamless loop -->
            <span class="ticker-item"><i class="bi bi-reception-4 up"></i> <span class="sym">VN-INDEX</span></span>
            <span class="ticker-sep">|</span>
            <span class="ticker-item"><span class="sym">VCB</span> <span class="up"><i class="bi bi-caret-up-fill"></i></span></span>
            <span class="ticker-sep">|</span>
            <span class="ticker-item"><span class="sym">FPT</span> <span class="up"><i class="bi bi-caret-up-fill"></i></span></span>
            <span class="ticker-sep">|</span>
            <span class="ticker-item"><span class="sym">VNM</span> <span class="neu"><i class="bi bi-dash"></i></span></span>
            <span class="ticker-sep">|</span>
            <span class="ticker-item"><span class="sym">ACB</span> <span class="up"><i class="bi bi-caret-up-fill"></i></span></span>
            <span class="ticker-sep">|</span>
            <span class="ticker-item"><span class="sym">HPG</span> <span class="dn"><i class="bi bi-caret-down-fill"></i></span></span>
            <span class="ticker-sep">|</span>
            <span class="ticker-item"><span class="sym">TCB</span> <span class="up"><i class="bi bi-caret-up-fill"></i></span></span>
            <span class="ticker-sep">|</span>
            <span class="ticker-item"><span class="sym">BID</span> <span class="up"><i class="bi bi-caret-up-fill"></i></span></span>
            <span class="ticker-sep">|</span>
            <span class="ticker-item"><span class="sym">MBB</span> <span class="dn"><i class="bi bi-caret-down-fill"></i></span></span>
            <span class="ticker-sep">|</span>
            <span class="ticker-item"><i class="bi bi-clock" style="color:#94a3b8;"></i> <span style="color:#94a3b8;">Giờ GD: 9:00 – 14:45</span></span>
            <span class="ticker-sep">|</span>
            <span class="ticker-item"><i class="bi bi-lightning-charge up"></i> <span style="color:rgba(255,255,255,0.6);">Dữ liệu được cập nhật tự động mỗi ngày</span></span>
            <span class="ticker-sep">|</span>
            <!-- Duplicate for seamless loop -->
            <span class="ticker-item"><i class="bi bi-reception-4 up"></i> <span class="sym">VN-INDEX</span></span>
            <span class="ticker-sep">|</span>
            <span class="ticker-item"><span class="sym">VCB</span> <span class="up"><i class="bi bi-caret-up-fill"></i></span></span>
            <span class="ticker-sep">|</span>
            <span class="ticker-item"><span class="sym">FPT</span> <span class="up"><i class="bi bi-caret-up-fill"></i></span></span>
            <span class="ticker-sep">|</span>
            <span class="ticker-item"><span class="sym">VNM</span> <span class="neu"><i class="bi bi-dash"></i></span></span>
            <span class="ticker-sep">|</span>
            <span class="ticker-item"><span class="sym">ACB</span> <span class="up"><i class="bi bi-caret-up-fill"></i></span></span>
            <span class="ticker-sep">|</span>
            <span class="ticker-item"><span class="sym">HPG</span> <span class="dn"><i class="bi bi-caret-down-fill"></i></span></span>
            <span class="ticker-sep">|</span>
            <span class="ticker-item"><span class="sym">TCB</span> <span class="up"><i class="bi bi-caret-up-fill"></i></span></span>
            <span class="ticker-sep">|</span>
            <span class="ticker-item"><span class="sym">BID</span> <span class="up"><i class="bi bi-caret-up-fill"></i></span></span>
            <span class="ticker-sep">|</span>
            <span class="ticker-item"><span class="sym">MBB</span> <span class="dn"><i class="bi bi-caret-down-fill"></i></span></span>
            <span class="ticker-sep">|</span>
            <span class="ticker-item"><i class="bi bi-clock" style="color:#94a3b8;"></i> <span style="color:#94a3b8;">Giờ GD: 9:00 – 14:45</span></span>
            <span class="ticker-sep">|</span>
            <span class="ticker-item"><i class="bi bi-lightning-charge up"></i> <span style="color:rgba(255,255,255,0.6);">Dữ liệu được cập nhật tự động mỗi ngày</span></span>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        @yield('content')
    </div>

    <!-- Footer -->
    <footer class="custom-footer">
        <div class="footer-top">
            <div class="container">
                <div class="row">
                    <!-- Brand column -->
                    <div class="col-lg-4 mb-4 mb-lg-0">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-graph-up-arrow" style="font-size:1.8rem; color:#60a5fa;"></i>
                            <span class="footer-brand-name">Sun Stock AI</span>
                        </div>
                        <p class="footer-brand-tagline">Vietnam's Smart Stock Analysis Platform</p>
                        <p style="color:rgba(255,255,255,0.5); font-size:0.85rem; margin-top:1rem; line-height:1.7;">
                            Nền tảng phân tích cổ phiếu Việt Nam thông minh với AI. Dữ liệu cập nhật tự động từ VNStock &amp; Vietcombank.
                        </p>
                        <div class="footer-social">
                            <a href="https://github.com/nhatnguyen94" target="_blank" class="footer-social-btn" title="GitHub">
                                <i class="bi bi-github"></i>
                            </a>
                            <a href="https://www.linkedin.com/in/sunnguyen3011/" target="_blank" class="footer-social-btn" title="LinkedIn">
                                <i class="bi bi-linkedin"></i>
                            </a>
                            <a href="mailto:nhat.nguyenminh94@gmail.com" class="footer-social-btn" title="Email">
                                <i class="bi bi-envelope"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Quick links -->
                    <div class="col-6 col-lg-2 mb-4 mb-lg-0">
                        <div class="footer-col-title">Tính năng</div>
                        <a href="{{ url('/stock') }}" class="footer-link"><i class="bi bi-graph-up"></i> Tra cứu cổ phiếu</a>
                        <a href="{{ url('/exchange-rate') }}" class="footer-link"><i class="bi bi-currency-exchange"></i> Tỷ giá ngoại tệ</a>
                        @auth
                        <a href="{{ route('portfolio.index') }}" class="footer-link"><i class="bi bi-briefcase"></i> Danh mục đầu tư</a>
                        @endauth
                        <a href="{{ url('/stock?symbol=VN30F1M') }}" class="footer-link"><i class="bi bi-bar-chart"></i> VN30 Futures</a>
                        <a href="{{ url('/stock/compare') }}" class="footer-link"><i class="bi bi-intersect"></i> So sánh cổ phiếu</a>
                    </div>

                    <!-- Popular stocks -->
                    <div class="col-6 col-lg-3 mb-4 mb-lg-0">
                        <div class="footer-col-title">Cổ phiếu phổ biến</div>
                        @foreach(['VCB','FPT','VNM','ACB','HPG','TCB','MBB','MSN'] as $sym)
                        <a href="{{ url('/stock?symbol='.$sym) }}" class="footer-link">
                            <i class="bi bi-dot" style="color:#fbbf24;"></i> {{ $sym }}
                        </a>
                        @endforeach
                    </div>

                    <!-- Contact / Info -->
                    <div class="col-lg-3">
                        <div class="footer-col-title">Thông tin</div>
                        <div class="footer-link" style="cursor:default;">
                            <i class="bi bi-clock" style="color:#60a5fa;"></i>
                            Dữ liệu cập nhật: 07:30 hàng ngày
                        </div>
                        <div class="footer-link" style="cursor:default;">
                            <i class="bi bi-database" style="color:#60a5fa;"></i>
                            Nguồn: VNStock API 4.x
                        </div>
                        <div class="footer-link" style="cursor:default;">
                            <i class="bi bi-bank" style="color:#60a5fa;"></i>
                            Tỷ giá: Vietcombank
                        </div>
                        <div style="margin-top:1.25rem; padding:0.875rem; background:rgba(239,68,68,0.1); border-radius:10px; border:1px solid rgba(239,68,68,0.2);">
                            <p style="color:rgba(255,255,255,0.5); font-size:0.75rem; margin:0; line-height:1.6;">
                                <i class="bi bi-exclamation-triangle" style="color:#f87171;"></i>
                                <strong style="color:#f87171;">Tuyên bố miễn trách:</strong>
                                Thông tin trên website chỉ mang tính tham khảo, không phải lời khuyên đầu tư.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span>© {{ date('Y') }} Sun Stock AI · Made with <i class="bi bi-heart-fill" style="color:#f87171;"></i> by Sun Nguyen</span>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="footer-badge"><i class="bi bi-shield-check"></i> Bảo mật SSL</span>
                    <span class="footer-badge"><i class="bi bi-lightning-charge"></i> Powered by Laravel 12</span>
                    <span class="footer-badge"><i class="bi bi-robot"></i> AI Enabled</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Back to top -->
    <button id="backToTop" title="Lên đầu trang"><i class="bi bi-arrow-up"></i></button>

    <!-- AI Chat Bubble -->
    <div id="aiChatBubble" style="position:fixed;bottom:30px;right:30px;z-index:9999;">
        <button id="aiChatOpenBtn" style="background:linear-gradient(135deg,var(--primary-blue),var(--secondary-blue));color:#fff;border:none;border-radius:50%;width:64px;height:64px;box-shadow:0 8px 30px rgba(37,99,235,0.45);font-size:1.5rem;cursor:pointer;transition:all 0.3s ease;position:relative;">
            <i class="bi bi-robot"></i>
            <span class="ai-pulse"></span>
        </button>
        
        <div id="aiChatPopup" style="display:none;position:absolute;bottom:80px;right:0;width:420px;max-width:95vw;background:white;border-radius:20px;box-shadow:0 25px 60px rgba(0,0,0,0.18);border:1px solid var(--border-color);overflow:hidden;animation:toastIn 0.3s ease;">
            <div style="background:linear-gradient(135deg,var(--primary-blue),var(--secondary-blue));color:white;padding:1.25rem 1.5rem;display:flex;justify-content:space-between;align-items:center;">
                <div style="font-weight:700;font-size:1.1rem;display:flex;align-items:center;gap:10px;">
                    <div style="background:rgba(255,255,255,0.2);border-radius:50%;width:36px;height:36px;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-robot"></i>
                    </div>
                    Sun Stock AI Chat
                    <span style="background:rgba(52,211,153,0.3);color:#34d399;font-size:0.7rem;padding:2px 8px;border-radius:20px;font-weight:600;">ONLINE</span>
                </div>
                <button onclick="closeAiChat()" style="background:rgba(255,255,255,0.15);border:none;color:white;border-radius:50%;width:32px;height:32px;cursor:pointer;transition:all 0.2s ease;display:flex;align-items:center;justify-content:center;" onmouseover="this.style.background='rgba(255,255,255,0.25)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- Suggested questions -->
            <div style="padding:0.875rem 1.25rem;background:#f8fafc;border-bottom:1px solid var(--border-color);display:flex;gap:6px;flex-wrap:wrap;">
                <button onclick="setAiQuestion('VN-Index hôm nay thế nào?')" style="background:var(--light-blue);color:var(--primary-blue);border:1px solid rgba(37,99,235,0.2);border-radius:20px;padding:4px 12px;font-size:0.78rem;font-weight:600;cursor:pointer;transition:all 0.2s ease;" onmouseover="this.style.background='var(--primary-blue);color:white'" onmouseout="this.style.background='var(--light-blue)'">📈 VN-Index</button>
                <button onclick="setAiQuestion('Nên mua cổ phiếu ngân hàng nào?')" style="background:var(--light-blue);color:var(--primary-blue);border:1px solid rgba(37,99,235,0.2);border-radius:20px;padding:4px 12px;font-size:0.78rem;font-weight:600;cursor:pointer;">🏦 Cổ phiếu NH</button>
                <button onclick="setAiQuestion('Phân tích cổ phiếu FPT')" style="background:var(--light-blue);color:var(--primary-blue);border:1px solid rgba(37,99,235,0.2);border-radius:20px;padding:4px 12px;font-size:0.78rem;font-weight:600;cursor:pointer;">💻 Phân tích FPT</button>
            </div>
            
            <div style="padding:1.25rem 1.5rem;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                    <span id="aiFlagIcon"><img src="https://flagcdn.com/24x18/vn.png" style="width:26px;height:20px;border-radius:4px;"></span>
                    <select id="aiLangSelect" style="flex:1;border-radius:10px;padding:7px 12px;border:2px solid var(--border-color);background:white;font-weight:500;font-size:0.9rem;">
                        <option value="vi">Tiếng Việt</option>
                        <option value="en">English</option>
                    </select>
                </div>
                
                <div id="aiChatMessages" style="height:300px;overflow-y:auto;background:var(--bg-light);border-radius:12px;padding:12px;margin-bottom:12px;border:1px solid var(--border-color);scroll-behavior:smooth;">
                    <div style="text-align:center;color:var(--text-secondary);font-size:0.85rem;padding:1rem;">
                        <i class="bi bi-robot" style="font-size:2rem;color:var(--primary-blue);display:block;margin-bottom:8px;"></i>
                        Xin chào! Tôi là Sun Stock AI.<br>Hỏi tôi bất cứ điều gì về thị trường chứng khoán!
                    </div>
                </div>
                
                <div style="display:flex;gap:8px;">
                    <input type="text" id="aiChatInput" placeholder="Hỏi về cổ phiếu, thị trường..." style="flex:1;border-radius:10px;border:2px solid var(--border-color);padding:10px 14px;background:white;font-size:0.9rem;transition:border-color 0.2s;" onfocus="this.style.borderColor='var(--primary-blue)'" onblur="this.style.borderColor='var(--border-color)'">
                    <button onclick="sendAiChat()" class="btn-primary-custom" style="padding:10px 14px;min-width:auto;border-radius:10px;">
                        <i class="bi bi-send-fill"></i>
                    </button>
                    <button onclick="clearAiChat()" style="background:#fee2e2;color:var(--danger-red);border:none;border-radius:10px;padding:10px 12px;cursor:pointer;transition:all 0.2s;" title="Xóa chat">
                        <i class="bi bi-trash3"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- AOS -->
    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
    <!-- NProgress -->
    <script src="https://unpkg.com/nprogress@0.2.0/nprogress.js"></script>

    <script>
        // ── AOS Init ──────────────────────────────────────
        AOS.init({ duration: 650, once: true, offset: 60, easing: 'ease-out-cubic' });

        // ── NProgress ─────────────────────────────────────
        NProgress.configure({ showSpinner: false, trickleSpeed: 200 });
        document.addEventListener('click', function(e) {
            const a = e.target.closest('a[href]');
            if (a && !a.getAttribute('href').startsWith('#') && !a.getAttribute('target') && !a.getAttribute('onclick')) {
                NProgress.start();
            }
        });
        window.addEventListener('pageshow', function() { NProgress.done(); });
        window.addEventListener('load', function() { NProgress.done(); });

        // ── Back to top ───────────────────────────────────
        const btt = document.getElementById('backToTop');
        window.addEventListener('scroll', function() {
            btt.classList.toggle('visible', window.scrollY > 400);
        });
        btt.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // ── Toast system ──────────────────────────────────
        function showToast(message, type = 'info', duration = 3500) {
            const icons = { success: 'check-circle-fill', error: 'x-circle-fill', warning: 'exclamation-triangle-fill', info: 'info-circle-fill' };
            const colors = { success: 'var(--success-green)', error: 'var(--danger-red)', warning: 'var(--warning-orange)', info: 'var(--primary-blue)' };
            const container = document.getElementById('toastContainer');
            const t = document.createElement('div');
            t.className = `toast-item ${type}`;
            t.innerHTML = `<i class="bi bi-${icons[type]||icons.info}" style="color:${colors[type]};font-size:1.2rem;flex-shrink:0;"></i><span style="flex:1;">${message}</span><i class="bi bi-x" style="color:var(--text-secondary);flex-shrink:0;"></i>`;
            t.addEventListener('click', () => t.remove());
            container.appendChild(t);
            setTimeout(() => { t.style.opacity='0'; t.style.transform='translateX(100px)'; t.style.transition='all 0.3s ease'; setTimeout(() => t.remove(), 300); }, duration);
        }

        // ── AI Chat ───────────────────────────────────────
        document.getElementById('aiChatOpenBtn').onclick = function () {
            const popup = document.getElementById('aiChatPopup');
            popup.style.display = popup.style.display === 'none' ? 'block' : 'none';
            this.style.transform = 'scale(0.9)';
            setTimeout(() => this.style.transform = 'scale(1)', 150);
        }
        
        function closeAiChat() {
            document.getElementById('aiChatPopup').style.display = 'none';
        }
        
        function setAiQuestion(q) {
            document.getElementById('aiChatInput').value = q;
            document.getElementById('aiChatInput').focus();
        }
        
        function sendAiChat() {
            let msg = document.getElementById('aiChatInput').value.trim();
            let lang = document.getElementById('aiLangSelect').value;
            if (!msg) return;
            
            let box = document.getElementById('aiChatMessages');
            box.innerHTML += `<div style="margin-bottom:10px;text-align:right;">
                <span style="background:linear-gradient(135deg,var(--primary-blue),var(--secondary-blue));color:white;border-radius:18px 18px 4px 18px;padding:10px 15px;display:inline-block;max-width:85%;font-weight:500;font-size:0.9rem;">${msg}</span>
            </div>`;
            document.getElementById('aiChatInput').value = '';
            
            box.innerHTML += `<div id="aiLoading" style="margin-bottom:10px;">
                <span style="background:white;border:1px solid var(--border-color);border-radius:18px 18px 18px 4px;padding:10px 15px;display:inline-flex;align-items:center;gap:8px;font-size:0.9rem;">
                    <span class="loading" style="border-top-color:var(--primary-blue);border-color:rgba(37,99,235,0.2);border-top-color:var(--primary-blue);"></span> AI đang phân tích...
                </span>
            </div>`;
            box.scrollTop = box.scrollHeight;
            
            fetch('/ai-chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ message: msg, lang: lang })
            }).then(res => res.json()).then(data => {
                const loading = document.getElementById('aiLoading');
                if (loading) loading.remove();
                box.innerHTML += `<div style="margin-bottom:10px;">
                    <div style="display:flex;align-items:flex-start;gap:8px;">
                        <div style="width:30px;height:30px;background:linear-gradient(135deg,var(--primary-blue),var(--secondary-blue));border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:4px;">
                            <i class="bi bi-robot" style="color:white;font-size:0.85rem;"></i>
                        </div>
                        <span style="background:white;border:1px solid var(--border-color);border-radius:4px 18px 18px 18px;padding:10px 15px;display:inline-block;max-width:85%;font-size:0.9rem;line-height:1.5;">${data.answer}</span>
                    </div>
                </div>`;
                box.scrollTop = box.scrollHeight;
            }).catch(() => {
                const loading = document.getElementById('aiLoading');
                if (loading) loading.remove();
                box.innerHTML += `<div style="margin-bottom:10px;"><span style="background:#fee2e2;color:#dc2626;border-radius:4px 18px 18px 18px;padding:10px 15px;display:inline-block;font-size:0.9rem;"><i class="bi bi-exclamation-triangle"></i> Có lỗi, vui lòng thử lại!</span></div>`;
                box.scrollTop = box.scrollHeight;
            });
        }
        
        function clearAiChat() {
            document.getElementById('aiChatMessages').innerHTML = `<div style="text-align:center;color:var(--text-secondary);font-size:0.85rem;padding:1rem;">
                <i class="bi bi-robot" style="font-size:2rem;color:var(--primary-blue);display:block;margin-bottom:8px;"></i>
                Xin chào! Tôi là Sun Stock AI.<br>Hỏi tôi bất cứ điều gì về thị trường chứng khoán!
            </div>`;
        }
        
        document.getElementById('aiLangSelect').onchange = function() {
            document.getElementById('aiFlagIcon').innerHTML = `<img src="https://flagcdn.com/24x18/${this.value === 'vi' ? 'vn' : 'us'}.png" style="width:26px;height:20px;border-radius:4px;">`;
        };
        document.getElementById('aiChatInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') sendAiChat();
        });
        document.addEventListener('click', function(e) {
            const bubble = document.getElementById('aiChatBubble');
            const popup = document.getElementById('aiChatPopup');
            if (!bubble.contains(e.target)) popup.style.display = 'none';
        });

        // ── Animate numbers (counter-up) ─────────────────
        function animateCounter(el) {
            const target = parseFloat(el.dataset.target || el.textContent.replace(/[^0-9.]/g,''));
            const isFloat = String(el.dataset.target||'').includes('.');
            const decimals = isFloat ? 1 : 0;
            const duration = 1500, step = 16;
            let current = 0, steps = duration / step;
            const inc = target / steps;
            const timer = setInterval(() => {
                current += inc;
                if (current >= target) { current = target; clearInterval(timer); }
                el.textContent = (isFloat ? current.toFixed(decimals) : Math.floor(current)).toLocaleString('vi-VN');
            }, step);
        }
        const counterObserver = new IntersectionObserver((entries) => {
            entries.forEach(e => { if (e.isIntersecting && !e.target.dataset.animated) { e.target.dataset.animated = '1'; animateCounter(e.target); } });
        }, { threshold: 0.5 });
        document.querySelectorAll('[data-counter]').forEach(el => counterObserver.observe(el));
    </script>

    @yield('scripts')
</body>
</html>
        
        <div id="aiChatPopup" style="display:none;position:absolute;bottom:80px;right:0;width:400px;max-width:95vw;background:white;border-radius:20px;box-shadow:var(--shadow-xl);border:1px solid var(--border-color);overflow:hidden;">
            <div style="background:linear-gradient(135deg,var(--primary-blue),var(--secondary-blue));color:white;padding:1.5rem;display:flex;justify-content:space-between;align-items:center;">
                <div style="font-weight:600;font-size:1.2rem;display:flex;align-items:center;gap:10px;">
                    <i class="bi bi-stars" style="color:#fbbf24;"></i>
                    Sun Stock AI Chat
                </div>
                <button onclick="closeAiChat()" style="background:rgba(255,255,255,0.2);border:none;color:white;border-radius:50%;width:36px;height:36px;cursor:pointer;transition:all 0.3s ease;">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            
            <div style="padding:1.5rem;">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:15px;">
                    <span id="aiFlagIcon" style="width:26px;height:20px;">
                        <img src="https://flagcdn.com/24x18/vn.png" style="width:26px;height:20px;border-radius:4px;">
                    </span>
                    <select id="aiLangSelect" style="flex:1;border-radius:10px;padding:8px 12px;border:2px solid var(--border-color);background:white;">
                        <option value="vi">Tiếng Việt</option>
                        <option value="en">English</option>
                    </select>
                </div>
                
                <div id="aiChatMessages" style="height:320px;overflow-y:auto;background:var(--bg-light);border-radius:12px;padding:15px;margin-bottom:15px;border:1px solid var(--border-color);"></div>
                
                <div style="display:flex;gap:10px;">
                    <input type="text" id="aiChatInput" placeholder="Hỏi AI về cổ phiếu, tài chính..." style="flex:1;border-radius:10px;border:2px solid var(--border-color);padding:12px 15px;background:white;">
                    <button onclick="sendAiChat()" class="btn-primary-custom" style="padding:12px 16px;min-width:auto;">
                        <i class="bi bi-send"></i>
                    </button>
                    <button onclick="clearAiChat()" style="background:var(--danger-red);color:white;border:none;border-radius:10px;padding:12px 16px;cursor:pointer;transition:all 0.3s ease;">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // AI Chat Functions
        document.getElementById('aiChatOpenBtn').onclick = function () {
            document.getElementById('aiChatPopup').style.display = 'block';
            this.style.transform = 'scale(0.9)';
            setTimeout(() => this.style.transform = 'scale(1)', 150);
        }
        
        function closeAiChat() {
            document.getElementById('aiChatPopup').style.display = 'none';
        }
        
        function sendAiChat() {
            let msg = document.getElementById('aiChatInput').value;
            let lang = document.getElementById('aiLangSelect').value;
            if (!msg.trim()) return;
            
            let box = document.getElementById('aiChatMessages');
            box.innerHTML += `<div style="margin-bottom:12px;text-align:right;">
    <span style="background:var(--primary-blue);color:white;border-radius:18px 18px 6px 18px;padding:10px 15px;display:inline-block;max-width:90%;font-weight:500;">
        <i class="bi bi-person-circle" style="color:#fbbf24;margin-right:8px;"></i>
        <b>Sun User:</b> ${msg}
    </span>
</div>`;
            
            document.getElementById('aiChatInput').value = '';
            
            // Show loading
            box.innerHTML += `<div id="aiLoading" style="margin-bottom:12px;">
                <span style="background:var(--light-blue);border-radius:18px 18px 18px 6px;padding:10px 15px;display:inline-block;">
                    <span class="loading"></span> AI đang suy nghĩ...
                </span>
            </div>`;
            box.scrollTop = box.scrollHeight;
            
            fetch('/ai-chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ message: msg, lang: lang })
            }).then(res => res.json()).then(data => {
                document.getElementById('aiLoading').remove();
                box.innerHTML += `<div style="margin-bottom:12px;">
        <span style="background:var(--light-blue);border-radius:18px 18px 18px 6px;padding:10px 15px;display:inline-block;max-width:85%;font-weight:500;">
            <i class="bi bi-robot" style="color:var(--primary-blue);margin-right:8px;"></i>
            <b>Sun Stock AI Bot:</b> ${data.answer}
        </span>
    </div>`;
                box.scrollTop = box.scrollHeight;
            }).catch(err => {
                document.getElementById('aiLoading').remove();
                box.innerHTML += `<div style="margin-bottom:12px;">
                    <span style="background:#fee2e2;color:#dc2626;border-radius:18px 18px 18px 6px;padding:10px 15px;display:inline-block;max-width:85%;">
                        <i class="bi bi-exclamation-triangle margin-right:8px;"></i>Có lỗi xảy ra, vui lòng thử lại!
                    </span>
                </div>`;
                box.scrollTop = box.scrollHeight;
            });
        }
        
        function clearAiChat() {
            document.getElementById('aiChatMessages').innerHTML = '';
        }
        
        document.getElementById('aiLangSelect').onchange = function() {
            var flag = this.value === 'vi' 
                ? 'https://flagcdn.com/24x18/vn.png'
                : 'https://flagcdn.com/24x18/us.png';
            document.getElementById('aiFlagIcon').innerHTML = `<img src="${flag}" style="width:26px;height:20px;border-radius:4px;">`;
        };

        // Enter key for chat
        document.getElementById('aiChatInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendAiChat();
            }
        });

        // Click outside to close chat
        document.addEventListener('click', function(e) {
            const chatBubble = document.getElementById('aiChatBubble');
            const chatPopup = document.getElementById('aiChatPopup');
            if (!chatBubble.contains(e.target)) {
                chatPopup.style.display = 'none';
            }
        });
    </script>

    @yield('scripts')
</body>
</html>
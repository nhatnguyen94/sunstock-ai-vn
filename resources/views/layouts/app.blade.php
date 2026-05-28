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
    @vite('resources/frontend/css/layouts/app.css')
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

    @vite('resources/frontend/js/layouts/app.js')


    @yield('scripts')
</body>
</html>

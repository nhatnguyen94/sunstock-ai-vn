<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sun Stock AI – Vietnam's Smart Stock App</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

        /* Footer */
        .custom-footer {
            background: linear-gradient(135deg, var(--bg-dark), #374151);
            color: white;
            margin-top: 5rem;
            padding: 3rem 0;
            border-top: 4px solid var(--primary-blue);
        }

        .footer-content {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .footer-link {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 0.75rem 1.25rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .footer-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            text-decoration: none;
            transform: translateY(-1px);
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(37, 99, 235, 0.3);
            border-radius: 50%;
            border-top-color: var(--primary-blue);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2rem;
            }
            
            .search-form {
                flex-direction: column;
            }
            
            .search-btn {
                justify-content: center;
            }
            
            .footer-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .search-container {
                margin: -2rem 1rem 2rem;
                padding: 2rem 1.5rem;
            }
            
            .custom-card {
                margin: 0 0.5rem 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .custom-card {
                background: #1f2937;
                border-color: #374151;
                color: #f9fafb;
            }
            
            .search-container {
                background: #1f2937;
                border-color: #374151;
            }
            
            .search-input {
                background: #374151;
                border-color: #4b5563;
                color: #f9fafb;
            }
        }
    </style>
    @yield('head')
</head>
<body>
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
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->is('/') ? 'active' : '' }}" href="{{ url('/') }}">
                            <i class="bi bi-house-door"></i>
                            Trang chủ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->is('stock*') ? 'active' : '' }}" href="{{ url('/stock') }}">
                            <i class="bi bi-graph-up"></i>
                            Tra cứu cổ phiếu
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->is('exchange-rate*') ? 'active' : '' }}" href="{{ url('/exchange-rate') }}">
                            <i class="bi bi-currency-exchange"></i>
                            Tỷ giá
                        </a>
                    </li>
                    @guest
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}">
                                <i class="bi bi-box-arrow-in-right"></i> Đăng nhập
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('register') }}">
                                <i class="bi bi-person-plus"></i> Đăng ký
                            </a>
                        </li>
                    @else
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" data-toggle="dropdown">
                                <i class="bi bi-person-circle"></i> {{ Auth::user()->profile->username ?? Auth::user()->email }}
                            </a>
                            <div class="dropdown-menu dropdown-menu-right">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="dropdown-item" type="submit">
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

    <!-- Main Content -->
    <div class="main-content">
        @yield('content')
    </div>

    <!-- Footer -->
    <footer class="custom-footer">
        <div class="container">
            <div class="footer-content">
                <a href="mailto:nhat.nguyenminh94@gmail.com" class="footer-link">
                    <i class="bi bi-envelope"></i>
                    Email
                </a>
                <a href="https://github.com/nhatnguyen94" target="_blank" class="footer-link">
                    <i class="bi bi-github"></i>
                    GitHub
                </a>
                <a href="https://www.linkedin.com/in/sunnguyen3011/" target="_blank" class="footer-link">
                    <i class="bi bi-linkedin"></i>
                    LinkedIn
                </a>
                <span class="text-light opacity-75">© {{ date('Y') }} Sun Stock AI</span>
            </div>
        </div>
    </footer>

    <!-- AI Chat Bubble -->
    <div id="aiChatBubble" style="position:fixed;bottom:30px;right:30px;z-index:9999;">
        <button id="aiChatOpenBtn" style="background:linear-gradient(135deg,var(--primary-blue),var(--secondary-blue));color:#fff;border:none;border-radius:50%;width:64px;height:64px;box-shadow:var(--shadow-lg);font-size:1.5rem;cursor:pointer;transition:all 0.3s ease;">
            <i class="bi bi-robot"></i>
        </button>
        
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
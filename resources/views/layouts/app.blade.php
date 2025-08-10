<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Stock Info</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Bootstrap Icons CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body {
            background: #f8f9fa;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        footer {
            font-size: 15px;
            background: #fff;
            border-top: 1px solid #eee;
            margin-top: 40px;
            padding: 18px 0 10px 0;
        }
        footer a { color: #007bff; text-decoration: none; }
        footer a:hover { text-decoration: underline; }
        .footer-icon { font-size: 1.1em; vertical-align: middle; margin-right: 4px; }

        .homepage-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 900px;
            margin: 32px auto 0 auto;
            padding: 18px 24px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        }
        .homepage-title {
            font-size: 1.7rem;
            font-weight: 700;
            color: #007bff;
            margin-bottom: 0;
        }
        .homepage-menu a {
            font-weight: 500;
            color: #333;
            margin-left: 24px;
            text-decoration: none;
            transition: color 0.2s;
            font-size: 1.05rem;
        }
        .homepage-menu a:hover {
            color: #007bff;
        }
        @media (max-width: 900px) {
            .homepage-header { max-width: 100%; flex-direction: column; align-items: flex-start; padding: 14px 8px; }
            .homepage-title { font-size: 1.2rem; }
            .homepage-menu { margin-top: 10px; }
            .homepage-menu a { margin-left: 12px; }
        }
    </style>
    @yield('head')
</head>
<body>
    {{-- Header dùng Bootstrap --}}
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
        <div class="container">
            <a class="navbar-brand font-weight-bold text-primary" href="{{ url('/') }}">
                VN Stock App
            </a>
            <span class="d-none d-md-inline text-muted ml-2">
                Tra cứu giá cổ phiếu Việt Nam nhanh chóng, trực quan
            </span>
            <div class="ml-auto">
                <a class="nav-link d-inline-block" href="{{ url('/') }}">Trang chủ</a>
                <a class="nav-link d-inline-block" href="{{ url('/stock') }}">Tra cứu chi tiết mã cổ phiếu</a>
                <a class="nav-link d-inline-block" href="{{ url('/exchange-rate') }}">Tỷ giá ngoại tệ</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        @yield('content')
    </div>

    {{-- Footer dùng Bootstrap --}}
    <footer class="bg-white border-top mt-5 py-3">
        <div class="container text-center">
            <div class="mb-2">
                <span class="footer-icon bi-person-circle"></span>
                <strong>Sun Nguyen</strong> &middot;
                <span class="footer-icon bi-envelope"></span>
                <a href="mailto:nhat.nguyenminh94@gmail.com">nhat.nguyenminh94@gmail.com</a> &middot;
                <span class="footer-icon bi-github"></span>
                <a href="https://github.com/nhatnguyen94" target="_blank">GitHub</a> &middot;
                <span class="footer-icon bi-linkedin"></span>
                <a href="https://www.linkedin.com/in/sunnguyen3011/" target="_blank">LinkedIn</a>
            </div>
            <div class="text-muted" style="font-size:13px;">
                &copy; {{ date('Y') }} Sun Nguyen. All rights reserved.
            </div>
        </div>
    </footer>

    {{-- Bootstrap & jQuery --}}
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

    {{-- Scripts riêng --}}
    @yield('scripts')
</body>
</html>
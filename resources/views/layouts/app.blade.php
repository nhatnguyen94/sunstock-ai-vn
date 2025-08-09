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
    </style>
    @yield('head')
</head>
<body>
    <div class="container mt-5">
        @yield('content')
    </div>

    <footer class="text-center">
        <div>
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
    </footer>

    {{-- Bootstrap & jQuery --}}
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

    {{-- Scripts riêng --}}
    @yield('scripts')
</body>
</html>
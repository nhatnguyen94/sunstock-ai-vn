<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Sun Stock AI – Vietnam’s Smart Stock App</title>
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

    <div id="aiChatBubble" style="position:fixed;bottom:32px;right:32px;z-index:9999;">
        <button id="aiChatOpenBtn"
            style="background:#007bff;color:#fff;border:none;border-radius:50%;width:56px;height:56px;box-shadow:0 4px 16px rgba(0,0,0,0.18);font-size:2rem;cursor:pointer;transition:box-shadow 0.2s;">
            <span class="bi bi-chat-dots"></span>
        </button>
        <div id="aiChatPopup"
            style="display:none;position:absolute;bottom:70px;right:0;width:370px;max-width:95vw;background:linear-gradient(135deg,#fff 80%,#e3f0ff 100%);border-radius:22px;box-shadow:0 8px 32px rgba(0,0,0,0.18);padding:22px 18px 18px 18px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <div style="font-weight:700;font-size:1.15rem;color:#007bff;">
                    <span class="bi bi-stars" style="color:#ffc107;font-size:1.3em;margin-right:4px;"></span>
                    Sun Stock AI Chat
                </div>
                <button onclick="closeAiChat()" class="btn btn-light btn-sm" style="border-radius:50%;padding:4px 8px;">
                    <span class="bi bi-x-lg"></span>
                </button>
            </div>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                <span id="aiFlagIcon" style="width:22px;height:16px;display:inline-block;">
                    <img src="https://flagcdn.com/24x18/vn.png" style="width:22px;height:16px;">
                </span>
                <select id="aiLangSelect" class="form-control"
                    style="width:130px;border-radius:18px;padding:4px 16px;font-size:1rem;border:1px solid #007bff;background:#f4f8ff;">
                    <option value="vi">Tiếng Việt</option>
                    <option value="en">English</option>
                </select>
            </div>
            <div id="aiChatMessages"
                style="height:320px;overflow-y:auto;background:#f8f9fa;border-radius:16px;padding:12px 10px;margin-bottom:10px;font-size:1rem;box-shadow:0 2px 8px rgba(0,0,0,0.06);"></div>
            <div style="display:flex;gap:8px;">
                <input type="text" id="aiChatInput" class="form-control"
                    placeholder="Hỏi AI về cổ phiếu, ngành, tỷ giá..." style="font-size:1rem;border-radius:18px;" />
                <button onclick="sendAiChat()" class="btn btn-primary btn-sm" style="border-radius:18px;">
                    <span class="bi bi-send"></span>
                </button>
                <button onclick="clearAiChat()" class="btn btn-danger btn-sm" style="border-radius:18px;">
                    <span class="bi bi-trash"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- Bootstrap & jQuery --}}
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.getElementById('aiChatOpenBtn').onclick = function () {
            document.getElementById('aiChatPopup').style.display = 'block';
        }
        function closeAiChat() {
            document.getElementById('aiChatPopup').style.display = 'none';
        }
        function sendAiChat() {
            let msg = document.getElementById('aiChatInput').value;
            let lang = document.getElementById('aiLangSelect').value;
            if (!msg.trim()) return;
            let box = document.getElementById('aiChatMessages');
            box.innerHTML += `<div style="margin-bottom:6px;text-align:right;"><span style="background:#e3f0ff;border-radius:12px;padding:6px 12px;display:inline-block;">🧑‍💻 <b>Bạn:</b> ${msg}</span></div>`;
            document.getElementById('aiChatInput').value = '';
            fetch('/ai-chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ message: msg, lang: lang })
            }).then(res => res.json()).then(data => {
                box.innerHTML += `<div style="margin-bottom:10px;text-align:left;"><span style="background:#fffbe6;border-radius:12px;padding:6px 12px;display:inline-block;"><span class="bi bi-robot" style="color:#007bff;font-size:1.1em;margin-right:4px;"></span><b>Sun AI:</b> ${data.answer}</span></div>`;
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
            document.getElementById('aiFlagIcon').innerHTML = `<img src="${flag}" style="width:22px;height:16px;">`;
        };
    </script>

    {{-- Scripts riêng --}}
    @yield('scripts')
</body>
</html>
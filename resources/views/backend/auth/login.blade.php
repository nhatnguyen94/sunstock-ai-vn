<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Đăng nhập quản trị · Sun Stock AI</title>
    <script>
        (function () {
            try {
                var t = localStorage.getItem('tabler-theme') || 'auto';
                var dark = t === 'dark' || (t === 'auto' && matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
            } catch (e) {}
        })();
    </script>
    @vite('resources/frontend/css/admin/app.css')
</head>
<body class="antialiased">
<div class="ad-auth">

    {{-- Brand panel --}}
    <aside class="ad-auth-hero" aria-hidden="true">
        <div class="ad-brand" style="padding:0;color:#fff">
            <span class="ad-brand-mark"><i class="ti ti-chart-arrows-vertical"></i></span>
            <span class="ad-brand-text"><b style="color:#fff">Sun Stock AI</b><small style="color:rgba(255,255,255,.7)">Bảng quản trị</small></span>
        </div>
        <div>
            <h2>Quản lý dữ liệu thị trường, người dùng và hệ thống ở một nơi.</h2>
            <ul>
                <li><i class="ti ti-activity-heartbeat"></i><span>Theo dõi hàng đợi, nguồn dữ liệu và các lần đồng bộ theo thời gian thực.</span></li>
                <li><i class="ti ti-shield-lock"></i><span>Phân quyền theo vai trò — mỗi người chỉ thấy đúng phần việc của mình.</span></li>
                <li><i class="ti ti-keyboard"></i><span>Nhấn <kbd style="background:rgba(255,255,255,.15);border:0;color:#fff">Ctrl</kbd> + <kbd style="background:rgba(255,255,255,.15);border:0;color:#fff">K</kbd> để nhảy tới bất kỳ trang nào.</span></li>
            </ul>
        </div>
        <small style="color:rgba(255,255,255,.55)">&copy; {{ date('Y') }} Sun Stock AI</small>
    </aside>

    {{-- Form panel --}}
    <main class="ad-auth-panel">
        <div class="ad-auth-card ad-fade-in">
            <div class="d-lg-none mb-4 d-flex align-items-center gap-2">
                <span class="ad-brand-mark" style="width:38px;height:38px"><i class="ti ti-chart-arrows-vertical"></i></span>
                <b class="fs-4">Sun Stock AI</b>
            </div>

            <h1 class="h2 fw-bold mb-1">Chào mừng trở lại</h1>
            <p class="text-secondary mb-4">Đăng nhập bằng tài khoản quản trị để tiếp tục.</p>

            @if(session('success'))
                <div class="alert alert-success" role="alert"><i class="ti ti-circle-check me-1"></i>{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger" role="alert">
                    <i class="ti ti-alert-circle me-1"></i>
                    @foreach($errors->all() as $error)<div class="d-inline">{{ $error }}</div>@if(!$loop->last)<br>@endif @endforeach
                </div>
            @endif

            <form action="{{ route('admin.login') }}" method="POST" autocomplete="off" novalidate>
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="email">Email quản trị</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="ti ti-mail"></i></span>
                        <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror"
                               placeholder="admin@example.com" value="{{ old('email') }}" autocomplete="username" autofocus required>
                    </div>
                    @error('email')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label" for="password">Mật khẩu</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="ti ti-lock"></i></span>
                        <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror"
                               placeholder="Nhập mật khẩu" autocomplete="current-password" required>
                        <button type="button" class="btn btn-outline-secondary btn-icon" id="togglePwd" title="Hiện / ẩn mật khẩu" aria-label="Hiện hoặc ẩn mật khẩu"><i class="ti ti-eye"></i></button>
                    </div>
                    @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>

                <label class="form-check mb-4">
                    <input type="checkbox" name="remember" class="form-check-input">
                    <span class="form-check-label">Ghi nhớ đăng nhập</span>
                </label>

                <button type="submit" class="btn btn-primary w-100 py-2" id="loginBtn"><i class="ti ti-login-2 me-1"></i> Đăng nhập</button>
            </form>

            <div class="text-center text-secondary small mt-4">
                <a href="{{ url('/') }}" class="link-secondary"><i class="ti ti-arrow-left"></i> Về trang chính</a>
            </div>
        </div>
    </main>
</div>

<script>
    document.getElementById('togglePwd').addEventListener('click', function () {
        var input = document.getElementById('password');
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        this.querySelector('i').className = 'ti ' + (show ? 'ti-eye-off' : 'ti-eye');
    });
    document.querySelector('form').addEventListener('submit', function () {
        var b = document.getElementById('loginBtn');
        b.disabled = true;
        b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang đăng nhập…';
    });
</script>
</body>
</html>

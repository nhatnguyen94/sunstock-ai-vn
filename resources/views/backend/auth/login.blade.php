<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Đăng nhập Quản trị - {{ config('app.name', 'Laravel') }}</title>
    
    <!-- CSS files -->
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/css/tabler.min.css" rel="stylesheet">
    <style>
        .btn-outline-primary:hover { background-color: #0056b3; }
        .card-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .login-page { min-height: 100vh; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); }
    </style>
</head>

<body class="d-flex flex-column login-page">
    <div class="page page-center">
        <div class="container container-tight py-4">
            
            <!-- Logo/Header -->
            <div class="text-center mb-4">
                <a href="{{ url('/') }}" class="navbar-brand">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-chart-line" width="48" height="48" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <line x1="4" y1="19" x2="20" y2="19"/>
                        <polyline points="4,15 8,9 12,11 16,6 20,10"/>
                    </svg>
                    Stock App
                </a>
                <div class="text-muted mt-2">Khu vực Quản trị</div>
            </div>

            <!-- Login Form -->
            <div class="card card-md">
                <div class="card-header text-white">
                    <h2 class="card-title text-center text-white mb-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-shield-lock me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                            <path d="M12 3a12 12 0 0 0 8.5 3a12 12 0 0 1 -8.5 15a12 12 0 0 1 -8.5 -15a12 12 0 0 0 8.5 -3"/>
                            <circle cx="12" cy="11" r="1"/>
                            <line x1="12" y1="12" x2="12" y2="14.5"/>
                        </svg>
                        Đăng nhập Quản trị
                    </h2>
                </div>
                
                <div class="card-body">
                    <!-- Flash Messages -->
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible" role="alert">
                            <div class="d-flex">
                                <div class="alert-icon">
                                    <svg class="icon alert-icon-prepend" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <path d="M5 12l5 5l10 -10"/>
                                    </svg>
                                </div>
                                <div>{{ session('success') }}</div>
                            </div>
                            <a class="btn-close" data-bs-dismiss="alert"></a>
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger alert-dismissible" role="alert">
                            <div class="d-flex">
                                <div class="alert-icon">
                                    <svg class="icon alert-icon-prepend" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <path d="M12 9v2m0 4v.01"/>
                                        <path d="M12 3a9 9 0 1 1 0 18a9 9 0 1 1 0-18"/>
                                    </svg>
                                </div>
                                <div>
                                    @foreach($errors->all() as $error)
                                        <div>{{ $error }}</div>
                                    @endforeach
                                </div>
                            </div>
                            <a class="btn-close" data-bs-dismiss="alert"></a>
                        </div>
                    @endif

                    <!-- Login Form -->
                    <form action="{{ route('admin.login') }}" method="POST" autocomplete="off" novalidate>
                        @csrf
                        
                        <div class="mb-3">
                            <label class="form-label required">Email quản trị</label>
                            <input type="email" name="email" 
                                   class="form-control @error('email') is-invalid @enderror" 
                                   placeholder="admin@example.com"
                                   value="{{ old('email') }}" 
                                   autocomplete="username"
                                   required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label required">
                                Mật khẩu
                                <span class="form-label-description">
                                    <a href="#" tabindex="-1">Quên mật khẩu?</a>
                                </span>
                            </label>
                            <div class="input-group input-group-flat">
                                <input type="password" name="password" 
                                       class="form-control @error('password') is-invalid @enderror" 
                                       placeholder="Nhập mật khẩu"
                                       autocomplete="current-password" 
                                       required>
                                <span class="input-group-text">
                                    <a href="#" class="link-secondary toggle-password" title="Hiện/Ẩn mật khẩu" data-bs-toggle="tooltip">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                            <circle cx="12" cy="12" r="2"/>
                                            <path d="M22 12c-2.667 4-6 6-10 6s-7.333 -2 -10 -6c2.667 -4 6 -6 10 -6s7.333 2 10 6"/>
                                        </svg>
                                    </a>
                                </span>
                            </div>
                            @error('password')
                                <div class="text-danger mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-check">
                                <input type="checkbox" name="remember" class="form-check-input">
                                <span class="form-check-label">Ghi nhớ đăng nhập</span>
                            </label>
                        </div>
                        
                        <div class="form-footer">
                            <button type="submit" class="btn btn-primary w-100">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-login me-2" width="20" height="20" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2"/>
                                    <path d="M20 12h-13l3 -3m0 6l-3 -3"/>
                                </svg>
                                Đăng nhập
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center text-muted mt-3">
                <small>
                    <a href="{{ url('/') }}" class="text-decoration-none">
                        ← Về trang chính
                    </a>
                    &bull; 
                    © {{ date('Y') }} {{ config('app.name') }}
                </small>
            </div>
        </div>
    </div>

    <!-- JS files -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>
    
    <script>
        // Toggle password visibility
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.querySelector('.toggle-password');
            if (togglePassword) {
                togglePassword.addEventListener('click', function(e) {
                    e.preventDefault();
                    const passwordInput = this.closest('.input-group').querySelector('input[type="password"], input[type="text"]');
                    const isPassword = passwordInput.type === 'password';
                    
                    passwordInput.type = isPassword ? 'text' : 'password';
                    
                    // Update icon
                    const icon = this.querySelector('svg');
                    if (isPassword) {
                        icon.innerHTML = '<path stroke="none" d="M0 0h24v24H0z" fill="none"/><line x1="3" y1="3" x2="21" y2="21"/><path d="M10.584 10.587a2 2 0 0 0 2.828 2.83"/><path d="M9.363 5.365a9.466 9.466 0 0 1 2.637 -.365c4 0 7.333 2 10 6c-.778 1.361 -1.612 2.524 -2.503 3.488m-2.14 1.861c-1.631 1.1 -3.415 1.651 -5.357 1.651c-4 0 -7.333 -2 -10 -6c1.369 -2.395 2.913 -4.175 4.632 -5.341"/>';
                    } else {
                        icon.innerHTML = '<path stroke="none" d="M0 0h24v24H0z" fill="none"/><circle cx="12" cy="12" r="2"/><path d="M22 12c-2.667 4-6 6-10 6s-7.333 -2 -10 -6c2.667 -4 6 -6 10 -6s7.333 2 10 6"/>';
                    }
                });
            }
        });
    </script>
</body>
</html>
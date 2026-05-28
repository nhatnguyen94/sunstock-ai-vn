@extends('layouts.app')

@section('title', 'Xác thực Email')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header text-center bg-warning text-dark">
                    <h4>
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="4" width="20" height="16" rx="2"/>
                            <path d="m22 7-10 5L2 7"/>
                        </svg>
                        Xác thực Email
                    </h4>
                </div>

                <div class="card-body text-center">
                    @if (session('message'))
                        <div class="alert alert-success mb-4" role="alert">
                            <div class="d-flex">
                                <div class="alert-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon-prepend" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <path d="M5 12l5 5l10 -10"/>
                                    </svg>
                                </div>
                                <div>{{ session('message') }}</div>
                            </div>
                        </div>
                    @endif

                    <div class="mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-lg text-warning mb-3" width="64" height="64" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="4" width="20" height="16" rx="2"/>
                            <path d="m22 7-10 5L2 7"/>
                            <circle cx="16" cy="6" r="2" fill="currentColor"/>
                        </svg>
                    </div>

                    <h5 class="card-title mb-3">Cần xác thực email</h5>
                    
                    <p class="card-text mb-4">
                        Cảm ơn bạn đã đăng ký! Trước khi bắt đầu, bạn có thể xác thực địa chỉ email của mình bằng cách nhấp vào liên kết mà chúng tôi vừa gửi qua email cho bạn không? 
                    </p>

                    <p class="text-muted mb-4">
                        Nếu bạn không nhận được email, chúng tôi sẵn sàng gửi cho bạn một email khác.
                    </p>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                        <form method="POST" action="{{ route('verification.send') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="20" height="20" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4"/>
                                    <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/>
                                </svg>
                                Gửi lại email xác thực
                            </button>
                        </form>

                        <form method="POST" action="{{ route('logout') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="20" height="20" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2"/>
                                    <path d="M7 12h14l-3 -3m0 6l3 -3"/>
                                </svg>
                                Đăng xuất
                            </button>
                        </form>
                    </div>

                    <hr class="my-4">

                    <div class="text-muted">
                        <small>
                            <strong>Lưu ý:</strong> Kiểm tra cả thư mục spam nếu bạn không thấy email trong hộp thư đến. 
                            Email xác thực có thể mất vài phút để được gửi đi.
                        </small>
                    </div>

                    <div class="mt-3">
                        <a href="{{ route('login') }}" class="text-decoration-none">
                            ← Quay lại đăng nhập
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@vite('resources/frontend/js/auth/verify-email.js')
@endsection
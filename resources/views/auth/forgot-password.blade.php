@extends('layouts.app')
@section('head')
@vite('resources/frontend/css/auth/password-reset.css')
@endsection

@section('content')
<div class="auth-wrap">
    <div class="auth-left">
        <div style="position:relative;z-index:1;">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:2.5rem;">
                <div style="width:48px;height:48px;border-radius:14px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:1.5rem;">
                    <i class="bi bi-key" style="color:#fbbf24;"></i>
                </div>
                <div>
                    <div style="font-size:1.3rem;font-weight:800;">Sun Stock AI</div>
                    <div style="font-size:0.75rem;opacity:0.7;">Nền tảng phân tích cổ phiếu</div>
                </div>
            </div>

            <h2 style="font-size:1.9rem;font-weight:800;line-height:1.3;margin-bottom:0.75rem;">
                Quên mật khẩu?<br>Không sao cả.
            </h2>
            <p style="opacity:0.8;font-size:0.95rem;margin-bottom:2rem;line-height:1.7;">
                Nhập email bạn đã dùng để đăng ký, chúng tôi sẽ gửi cho bạn một link để đặt lại mật khẩu mới.
            </p>

            <div style="margin-top:2.5rem;padding-top:2rem;border-top:1px solid rgba(255,255,255,0.15);font-size:0.8rem;opacity:0.6;">
                © {{ date('Y') }} Sun Stock AI &nbsp;·&nbsp; Miễn phí 100%
            </div>
        </div>
    </div>

    <div class="auth-right">
        <div style="max-width:380px;width:100%;margin:0 auto;">
            <h3 style="font-size:1.7rem;font-weight:800;color:#111827;margin-bottom:0.5rem;">
                Quên mật khẩu
            </h3>
            <p style="color:#6b7280;margin-bottom:2rem;font-size:0.9rem;">
                Nhập email đã đăng ký để nhận link đặt lại mật khẩu
            </p>

            @if(session('status'))
            <div class="auth-status-box">
                <i class="bi bi-check-circle" style="margin-top:2px;"></i>
                <span>{{ session('status') }}</span>
            </div>
            @endif

            @if($errors->any())
            <div class="auth-error-box">
                <i class="bi bi-exclamation-circle"></i>
                {{ $errors->first() }}
            </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}">
                @csrf
                <div class="mb-3">
                    <label class="auth-label">
                        <i class="bi bi-envelope" style="color:#2563eb;"></i> Email
                    </label>
                    <div style="position:relative;">
                        <i class="bi bi-envelope" style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:#9ca3af;pointer-events:none;"></i>
                        <input type="email" name="email" class="auth-input" required autofocus
                               value="{{ old('email') }}" placeholder="ban@example.com">
                    </div>
                </div>

                <button type="submit" class="auth-submit-btn mt-2">
                    <i class="bi bi-send"></i>
                    Gửi link đặt lại mật khẩu
                </button>
            </form>

            <div style="text-align:center;margin-top:1.75rem;padding-top:1.75rem;border-top:1px solid #f3f4f6;">
                <a href="{{ route('login') }}" style="color:#2563eb;font-weight:700;text-decoration:none;font-size:0.875rem;">
                    <i class="bi bi-arrow-left"></i> Quay lại đăng nhập
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

@extends('layouts.app')
@section('head')
@vite('resources/frontend/css/auth/login.css')
@endsection

@section('content')
<div class="auth-wrap">
    <!-- Left branding panel -->
    <div class="auth-left">
        <div style="position:relative;z-index:1;">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:2.5rem;">
                <div style="width:48px;height:48px;border-radius:14px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:1.5rem;">
                    <i class="bi bi-graph-up-arrow" style="color:#fbbf24;"></i>
                </div>
                <div>
                    <div style="font-size:1.3rem;font-weight:800;">Sun Stock AI</div>
                    <div style="font-size:0.75rem;opacity:0.7;">Nền tảng phân tích cổ phiếu</div>
                </div>
            </div>

            <h2 style="font-size:1.9rem;font-weight:800;line-height:1.3;margin-bottom:0.75rem;">
                Phân tích thị trường <br>chứng khoán Việt Nam
            </h2>
            <p style="opacity:0.8;font-size:0.95rem;margin-bottom:2rem;line-height:1.7;">
                Đăng nhập để truy cập đầy đủ dữ liệu cổ phiếu, tỷ giá, AI dự đoán và quản lý danh mục đầu tư cá nhân.
            </p>

            <div>
                <div class="auth-feature-item">
                    <div class="auth-feature-icon"><i class="bi bi-graph-up"></i></div>
                    <div>
                        <div style="font-weight:700;font-size:0.9rem;">Biểu đồ kỹ thuật</div>
                        <div style="font-size:0.8rem;opacity:0.75;">Nến Nhật, volume, MA, lịch sử nhiều năm</div>
                    </div>
                </div>
                <div class="auth-feature-item">
                    <div class="auth-feature-icon"><i class="bi bi-cpu"></i></div>
                    <div>
                        <div style="font-weight:700;font-size:0.9rem;">AI phân tích thị trường</div>
                        <div style="font-size:0.8rem;opacity:0.75;">Hỏi AI bất kỳ điều gì về cổ phiếu</div>
                    </div>
                </div>
                <div class="auth-feature-item">
                    <div class="auth-feature-icon"><i class="bi bi-briefcase"></i></div>
                    <div>
                        <div style="font-weight:700;font-size:0.9rem;">Danh mục cá nhân</div>
                        <div style="font-size:0.8rem;opacity:0.75;">Theo dõi lãi/lỗ, cảnh báo giá mục tiêu</div>
                    </div>
                </div>
                <div class="auth-feature-item">
                    <div class="auth-feature-icon"><i class="bi bi-currency-exchange"></i></div>
                    <div>
                        <div style="font-weight:700;font-size:0.9rem;">Tỷ giá ngoại tệ</div>
                        <div style="font-size:0.8rem;opacity:0.75;">Cập nhật hàng ngày từ Vietcombank</div>
                    </div>
                </div>
            </div>

            <div style="margin-top:2.5rem;padding-top:2rem;border-top:1px solid rgba(255,255,255,0.15);font-size:0.8rem;opacity:0.6;">
                © {{ date('Y') }} Sun Stock AI &nbsp;·&nbsp; Miễn phí 100%
            </div>
        </div>
    </div>

    <!-- Right form panel -->
    <div class="auth-right">
        <div style="max-width:380px;width:100%;margin:0 auto;">
            <h3 style="font-size:1.7rem;font-weight:800;color:#111827;margin-bottom:0.5rem;">
                Chào mừng trở lại
            </h3>
            <p style="color:#6b7280;margin-bottom:2rem;font-size:0.9rem;">
                Đăng nhập để tiếp tục sử dụng Sun Stock AI
            </p>

            <form method="POST" action="{{ url('/login') }}">
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
                <div class="mb-3">
                    <label class="auth-label">
                        <i class="bi bi-lock" style="color:#2563eb;"></i> Mật khẩu
                    </label>
                    <div style="position:relative;">
                        <i class="bi bi-lock" style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:#9ca3af;pointer-events:none;"></i>
                        <input type="password" name="password" id="loginPassword" class="auth-input" required placeholder="••••••••">
                        <button type="button" onclick="togglePwd('loginPassword',this)" style="position:absolute;right:1rem;top:50%;transform:translateY(-50%);background:none;border:none;color:#9ca3af;cursor:pointer;padding:0;">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                @if($errors->any())
                <div style="background:#fef2f2;color:#dc2626;padding:0.875rem 1rem;border-radius:10px;margin-bottom:1rem;font-size:0.875rem;border:1px solid #fecaca;display:flex;align-items:center;gap:8px;">
                    <i class="bi bi-exclamation-circle"></i>
                    {{ $errors->first() }}
                </div>
                @endif

                <button type="submit" class="auth-submit-btn mt-2">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Đăng nhập
                </button>
            </form>

            <div style="text-align:center;margin-top:1.75rem;padding-top:1.75rem;border-top:1px solid #f3f4f6;">
                <span style="color:#6b7280;font-size:0.875rem;">Chưa có tài khoản?</span>
                <a href="{{ route('register') }}" style="color:#2563eb;font-weight:700;text-decoration:none;font-size:0.875rem;margin-left:6px;">
                    Đăng ký miễn phí <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <div style="text-align:center;margin-top:1rem;">
                <a href="{{ url('/') }}" style="color:#9ca3af;font-size:0.8rem;text-decoration:none;">
                    <i class="bi bi-house"></i> Về trang chủ
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function togglePwd(id, btn) {
    const inp = document.getElementById(id);
    const icon = btn.querySelector('i');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        inp.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
</script>
@endsection
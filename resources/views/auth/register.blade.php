@extends('layouts.app')
@section('head')
<style>
.auth-wrap { display:flex; min-height:calc(100vh - 140px); }
.auth-left {
    flex:0 0 38%;
    background:linear-gradient(145deg,#7c3aed 0%,#2563eb 60%,#0891b2 100%);
    padding:3.5rem 2.75rem;
    display:flex;
    flex-direction:column;
    justify-content:center;
    color:white;
    position:relative;
    overflow:hidden;
}
.auth-left::before {
    content:'';position:absolute;top:-60px;right:-60px;
    width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,0.06);
}
.auth-left::after {
    content:'';position:absolute;bottom:-40px;left:-40px;
    width:160px;height:160px;border-radius:50%;background:rgba(255,255,255,0.04);
}
.auth-right { flex:1;padding:3rem 3rem;background:white;display:flex;flex-direction:column;justify-content:center;overflow-y:auto; }
.auth-feature-item { display:flex;align-items:flex-start;gap:12px;margin-bottom:1.1rem; }
.auth-feature-icon { width:34px;height:34px;border-radius:9px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.auth-input {
    width:100%;padding:0.8rem 1rem 0.8rem 2.5rem;
    border:2px solid #e5e7eb;border-radius:10px;font-size:0.9rem;
    transition:all 0.3s;outline:none;box-sizing:border-box;
}
.auth-input:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,0.1); }
.auth-label { font-weight:600;color:#374151;font-size:0.8rem;margin-bottom:0.4rem;display:flex;align-items:center;gap:5px; }
.auth-submit-btn {
    width:100%;padding:0.9rem;background:linear-gradient(135deg,#7c3aed,#2563eb);
    color:white;border:none;border-radius:12px;font-size:0.975rem;font-weight:700;
    cursor:pointer;transition:all 0.3s;display:flex;align-items:center;justify-content:center;gap:8px;
}
.auth-submit-btn:hover { transform:translateY(-2px);box-shadow:0 8px 25px rgba(124,58,237,0.35); }
@media(max-width:768px){ .auth-left{display:none;} .auth-right{padding:2rem 1.5rem;} .auth-wrap{min-height:auto;} }
</style>
@endsection

@section('content')
<div class="auth-wrap">
    <!-- Left branding panel -->
    <div class="auth-left">
        <div style="position:relative;z-index:1;">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:2.5rem;">
                <div style="width:46px;height:46px;border-radius:14px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:1.4rem;">
                    <i class="bi bi-graph-up-arrow" style="color:#fbbf24;"></i>
                </div>
                <div>
                    <div style="font-size:1.25rem;font-weight:800;">Sun Stock AI</div>
                    <div style="font-size:0.72rem;opacity:0.7;">Miễn phí · Không giới hạn</div>
                </div>
            </div>

            <h2 style="font-size:1.75rem;font-weight:800;line-height:1.3;margin-bottom:0.75rem;">
                Bắt đầu hành trình <br>đầu tư thông minh
            </h2>
            <p style="opacity:0.8;font-size:0.9rem;margin-bottom:1.75rem;line-height:1.7;">
                Tạo tài khoản miễn phí và truy cập ngay đầy đủ công cụ phân tích cổ phiếu Việt Nam.
            </p>

            <div>
                <div class="auth-feature-item">
                    <div class="auth-feature-icon"><i class="bi bi-briefcase"></i></div>
                    <div>
                        <div style="font-weight:700;font-size:0.875rem;">Danh mục cá nhân</div>
                        <div style="font-size:0.78rem;opacity:0.75;">Quản lý cổ phiếu, tính lãi/lỗ tự động</div>
                    </div>
                </div>
                <div class="auth-feature-item">
                    <div class="auth-feature-icon"><i class="bi bi-bell"></i></div>
                    <div>
                        <div style="font-weight:700;font-size:0.875rem;">Cảnh báo giá mục tiêu</div>
                        <div style="font-size:0.78rem;opacity:0.75;">Đặt take-profit & stop-loss cho mỗi cổ phiếu</div>
                    </div>
                </div>
                <div class="auth-feature-item">
                    <div class="auth-feature-icon"><i class="bi bi-cpu"></i></div>
                    <div>
                        <div style="font-weight:700;font-size:0.875rem;">AI phân tích không giới hạn</div>
                        <div style="font-size:0.78rem;opacity:0.75;">Hỏi AI bất kỳ điều gì về thị trường</div>
                    </div>
                </div>
                <div class="auth-feature-item">
                    <div class="auth-feature-icon"><i class="bi bi-shield-check"></i></div>
                    <div>
                        <div style="font-weight:700;font-size:0.875rem;">Bảo mật tốt nhất</div>
                        <div style="font-size:0.78rem;opacity:0.75;">Dữ liệu mã hóa, không chia sẻ</div>
                    </div>
                </div>
            </div>

            <div style="margin-top:2rem;padding-top:1.5rem;border-top:1px solid rgba(255,255,255,0.15);">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="display:flex;">
                        @foreach(['VCB','FPT','VNM'] as $s)
                        <div style="width:30px;height:30px;border-radius:50%;background:rgba(255,255,255,0.2);border:2px solid rgba(255,255,255,0.4);display:flex;align-items:center;justify-content:center;font-size:0.6rem;font-weight:700;margin-left:-8px;first:margin-left:0;">{{ $s }}</div>
                        @endforeach
                    </div>
                    <div style="font-size:0.78rem;opacity:0.8;">Tham gia cùng hàng nghìn nhà đầu tư</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right form panel -->
    <div class="auth-right">
        <div style="max-width:440px;width:100%;margin:0 auto;">
            <h3 style="font-size:1.6rem;font-weight:800;color:#111827;margin-bottom:0.4rem;">
                Tạo tài khoản
            </h3>
            <p style="color:#6b7280;margin-bottom:1.75rem;font-size:0.875rem;">
                Điền thông tin bên dưới để đăng ký miễn phí
            </p>

            <form method="POST" action="{{ url('/register') }}">
                @csrf
                <div class="row">
                    <div class="col-6">
                        <div class="mb-3">
                            <label class="auth-label"><i class="bi bi-person" style="color:#7c3aed;"></i> Username</label>
                            <div style="position:relative;">
                                <i class="bi bi-person" style="position:absolute;left:0.875rem;top:50%;transform:translateY(-50%);color:#9ca3af;pointer-events:none;font-size:0.9rem;"></i>
                                <input type="text" name="username" class="auth-input" required value="{{ old('username') }}" placeholder="username123">
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="mb-3">
                            <label class="auth-label"><i class="bi bi-envelope" style="color:#7c3aed;"></i> Email</label>
                            <div style="position:relative;">
                                <i class="bi bi-envelope" style="position:absolute;left:0.875rem;top:50%;transform:translateY(-50%);color:#9ca3af;pointer-events:none;font-size:0.9rem;"></i>
                                <input type="email" name="email" class="auth-input" required value="{{ old('email') }}" placeholder="ban@email.com">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="auth-label"><i class="bi bi-phone" style="color:#7c3aed;"></i> Số điện thoại <span style="color:#9ca3af;font-weight:400;">(tuỳ chọn)</span></label>
                    <div style="position:relative;">
                        <i class="bi bi-phone" style="position:absolute;left:0.875rem;top:50%;transform:translateY(-50%);color:#9ca3af;pointer-events:none;font-size:0.9rem;"></i>
                        <input type="text" name="mobile" class="auth-input" value="{{ old('mobile') }}" placeholder="09xxxxxxxx">
                    </div>
                </div>

                <div class="row">
                    <div class="col-6">
                        <div class="mb-3">
                            <label class="auth-label"><i class="bi bi-lock" style="color:#7c3aed;"></i> Mật khẩu</label>
                            <div style="position:relative;">
                                <i class="bi bi-lock" style="position:absolute;left:0.875rem;top:50%;transform:translateY(-50%);color:#9ca3af;pointer-events:none;font-size:0.9rem;"></i>
                                <input type="password" name="password" id="regPwd" class="auth-input" required placeholder="Tối thiểu 8 ký tự">
                                <button type="button" onclick="togglePwd('regPwd',this)" style="position:absolute;right:0.875rem;top:50%;transform:translateY(-50%);background:none;border:none;color:#9ca3af;cursor:pointer;padding:0;">
                                    <i class="bi bi-eye" style="font-size:0.9rem;"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="mb-3">
                            <label class="auth-label"><i class="bi bi-lock-fill" style="color:#7c3aed;"></i> Xác nhận mật khẩu</label>
                            <div style="position:relative;">
                                <i class="bi bi-lock-fill" style="position:absolute;left:0.875rem;top:50%;transform:translateY(-50%);color:#9ca3af;pointer-events:none;font-size:0.9rem;"></i>
                                <input type="password" name="password_confirmation" id="regPwd2" class="auth-input" required placeholder="Nhập lại mật khẩu">
                                <button type="button" onclick="togglePwd('regPwd2',this)" style="position:absolute;right:0.875rem;top:50%;transform:translateY(-50%);background:none;border:none;color:#9ca3af;cursor:pointer;padding:0;">
                                    <i class="bi bi-eye" style="font-size:0.9rem;"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                @if($errors->any())
                <div style="background:#fef2f2;color:#dc2626;padding:0.75rem 1rem;border-radius:10px;margin-bottom:1rem;font-size:0.875rem;border:1px solid #fecaca;display:flex;align-items:center;gap:8px;">
                    <i class="bi bi-exclamation-circle"></i>
                    {{ $errors->first() }}
                </div>
                @endif

                <button type="submit" class="auth-submit-btn">
                    <i class="bi bi-person-plus-fill"></i>
                    Tạo tài khoản miễn phí
                </button>

                <p style="font-size:0.75rem;color:#9ca3af;text-align:center;margin-top:0.875rem;">
                    Bằng cách đăng ký, bạn đồng ý với điều khoản sử dụng của chúng tôi.
                </p>
            </form>

            <div style="text-align:center;margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid #f3f4f6;">
                <span style="color:#6b7280;font-size:0.875rem;">Đã có tài khoản?</span>
                <a href="{{ route('login') }}" style="color:#7c3aed;font-weight:700;text-decoration:none;font-size:0.875rem;margin-left:6px;">
                    Đăng nhập <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <div style="text-align:center;margin-top:0.875rem;">
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
    if (inp.type === 'password') { inp.type = 'text'; icon.className = 'bi bi-eye-slash'; }
    else { inp.type = 'password'; icon.className = 'bi bi-eye'; }
}
</script>
@endsection

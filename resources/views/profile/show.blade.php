@extends('layouts.app')
@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="custom-card">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">
                        <i class="bi bi-person-circle" style="color:var(--primary-blue);"></i>
                        Thông tin cá nhân
                    </h3>
                    <a href="{{ route('profile.edit') }}" class="btn btn-primary-custom">
                        <i class="bi bi-pencil"></i> Chỉnh sửa
                    </a>
                </div>
                <div class="card-body-custom" style="padding:2rem; color:var(--text-primary); background:white;">
                    
                    @if(session('success'))
                        <div class="alert" style="background:linear-gradient(135deg, #d1fae5, #a7f3d0); color:var(--success-green); 
                                    padding:1rem 1.5rem; border-radius:12px; margin-bottom:2rem; font-weight:500; 
                                    border:1px solid rgba(34, 197, 94, 0.3);">
                            <i class="bi bi-check-circle" style="margin-right:8px;"></i>
                            {{ session('success') }}
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-item mb-4">
                                <label class="info-label">
                                    <i class="bi bi-person" style="color:var(--primary-blue); margin-right:8px;"></i>
                                    Tên người dùng
                                </label>
                                <div class="info-value">{{ $profile->username ?? 'Chưa cập nhật' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item mb-4">
                                <label class="info-label">
                                    <i class="bi bi-envelope" style="color:var(--primary-blue); margin-right:8px;"></i>
                                    Email
                                </label>
                                <div class="info-value">{{ $user->email }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-item mb-4">
                                <label class="info-label">
                                    <i class="bi bi-phone" style="color:var(--primary-blue); margin-right:8px;"></i>
                                    Số điện thoại
                                </label>
                                <div class="info-value">{{ $profile->mobile ?? 'Chưa cập nhật' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item mb-4">
                                <label class="info-label">
                                    <i class="bi bi-calendar" style="color:var(--primary-blue); margin-right:8px;"></i>
                                    Ngày đăng ký
                                </label>
                                <div class="info-value">{{ $user->created_at->format('d/m/Y') }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="info-item mb-4">
                                <label class="info-label">
                                    <i class="bi bi-shield-check" style="color:var(--primary-blue); margin-right:8px;"></i>
                                    Trạng thái tài khoản
                                </label>
                                <div class="info-value">
                                    @if($user->email_verified_at)
                                        <span class="badge" style="background:var(--success-green); color:white; padding:0.5rem 1rem; border-radius:20px;">
                                            <i class="bi bi-check-circle"></i> Đã xác thực
                                        </span>
                                    @else
                                        <span class="badge" style="background:var(--warning-orange); color:white; padding:0.5rem 1rem; border-radius:20px;">
                                            <i class="bi bi-exclamation-circle"></i> Chưa xác thực
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-4" style="border-top:1px solid var(--border-color);">
                        <a href="/" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Về trang chủ
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.info-label {
    font-weight: 600;
    color: var(--text-secondary);
    font-size: 0.9rem;
    display: block;
    margin-bottom: 0.5rem;
}

.info-value {
    font-size: 1.1rem;
    color: var(--text-primary);
    font-weight: 500;
    background: var(--light-gray);
    padding: 1rem 1.5rem;
    border-radius: 10px;
    border: 1px solid var(--border-color);
}
</style>
@endsection
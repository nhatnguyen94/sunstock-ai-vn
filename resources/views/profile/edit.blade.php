@extends('layouts.app')
@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="custom-card">
                <div class="card-header-custom">
                    <h3 class="card-title">
                        <i class="bi bi-pencil-square" style="color:var(--primary-blue);"></i>
                        Chỉnh sửa thông tin cá nhân
                    </h3>
                </div>
                <div class="card-body-custom" style="padding:2rem; color:var(--text-primary); background:white;">
                    <form method="POST" action="{{ route('profile.update') }}">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label style="font-weight:500; color:var(--text-primary);">
                                        <i class="bi bi-person" style="margin-right:8px; color:var(--primary-blue);"></i>
                                        Tên người dùng
                                    </label>
                                    <input type="text" name="username" class="form-control search-input" required
                                           style="background:white; color:var(--text-primary); padding:1rem 1rem 1rem 2.5rem; border-radius:12px; border:2px solid var(--border-color);"
                                           value="{{ old('username', $profile->username) }}">
                                    @error('username')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label style="font-weight:500; color:var(--text-primary);">
                                        <i class="bi bi-envelope" style="margin-right:8px; color:var(--primary-blue);"></i>
                                        Email (không thể thay đổi)
                                    </label>
                                    <input type="email" class="form-control search-input" disabled
                                           style="background:var(--light-gray); color:var(--text-secondary); padding:1rem 1rem 1rem 2.5rem; border-radius:12px; border:2px solid var(--border-color);"
                                           value="{{ $user->email }}">
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-4">
                            <label style="font-weight:500; color:var(--text-primary);">
                                <i class="bi bi-phone" style="margin-right:8px; color:var(--primary-blue);"></i>
                                Số điện thoại
                            </label>
                            <input type="text" name="mobile" class="form-control search-input"
                                   style="background:white; color:var(--text-primary); padding:1rem 1rem 1rem 2.5rem; border-radius:12px; border:2px solid var(--border-color);"
                                   value="{{ old('mobile', $profile->mobile) }}">
                            @error('mobile')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="password-section mt-5 pt-4" style="border-top:2px solid var(--border-color);">
                            <h5 style="color:var(--text-primary); margin-bottom:1.5rem;">
                                <i class="bi bi-shield-lock" style="color:var(--primary-blue); margin-right:8px;"></i>
                                Đổi mật khẩu (tùy chọn)
                            </h5>
                            
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group mb-3">
                                        <label style="font-weight:500; color:var(--text-primary);">
                                            <i class="bi bi-lock" style="margin-right:8px; color:var(--primary-blue);"></i>
                                            Mật khẩu hiện tại
                                        </label>
                                        <input type="password" name="current_password" class="form-control search-input"
                                               style="background:white; color:var(--text-primary); padding:1rem 1rem 1rem 2.5rem; border-radius:12px; border:2px solid var(--border-color);">
                                        @error('current_password')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label style="font-weight:500; color:var(--text-primary);">
                                            <i class="bi bi-lock-fill" style="margin-right:8px; color:var(--primary-blue);"></i>
                                            Mật khẩu mới
                                        </label>
                                        <input type="password" name="password" class="form-control search-input"
                                               style="background:white; color:var(--text-primary); padding:1rem 1rem 1rem 2.5rem; border-radius:12px; border:2px solid var(--border-color);">
                                        @error('password')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label style="font-weight:500; color:var(--text-primary);">
                                            <i class="bi bi-lock-fill" style="margin-right:8px; color:var(--primary-blue);"></i>
                                            Nhập lại mật khẩu mới
                                        </label>
                                        <input type="password" name="password_confirmation" class="form-control search-input"
                                               style="background:white; color:var(--text-primary); padding:1rem 1rem 1rem 2.5rem; border-radius:12px; border:2px solid var(--border-color);">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        @if($errors->any())
                            <div class="alert mt-4" style="background:linear-gradient(135deg, #fee2e2, #fecaca); color:var(--danger-red); 
                                        padding:1rem 1.5rem; border-radius:12px; font-weight:500; 
                                        border:1px solid rgba(239, 68, 68, 0.3);">
                                <i class="bi bi-exclamation-triangle" style="margin-right:8px;"></i>
                                @foreach($errors->all() as $error)
                                    <div>{{ $error }}</div>
                                @endforeach
                            </div>
                        @endif
                        
                        <div class="d-flex gap-3 mt-4">
                            <button type="submit" class="btn btn-primary-custom" 
                                    style="padding:1rem 2rem;">
                                <i class="bi bi-check-circle"></i>
                                Cập nhật thông tin
                            </button>
                            
                            <a href="{{ route('profile.show') }}" class="btn btn-outline-secondary"
                               style="padding:1rem 2rem;">
                                <i class="bi bi-x-circle"></i>
                                Hủy bỏ
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
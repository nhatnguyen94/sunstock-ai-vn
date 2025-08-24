@extends('layouts.app')
@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">
            <div class="custom-card">
                <div class="card-header-custom text-center">
                    <h3 class="card-title">
                        <i class="bi bi-person-plus" style="color:var(--primary-blue);"></i>
                        Đăng ký / Register
                    </h3>
                </div>
                <div class="card-body-custom" style="padding:2rem; color:var(--text-primary); background:white;">
                    <form method="POST" action="{{ url('/register') }}">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label style="font-weight:500; color:var(--text-primary);">
                                        <i class="bi bi-person" style="margin-right:8px; color:var(--primary-blue);"></i>
                                        Username
                                    </label>
                                    <input type="text" name="username" class="form-control search-input" required
                                           style="background:white; color:var(--text-primary); padding:1rem 1rem 1rem 2.5rem; border-radius:12px; border:2px solid var(--border-color);"
                                           value="{{ old('username') }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label style="font-weight:500; color:var(--text-primary);">
                                        <i class="bi bi-envelope" style="margin-right:8px; color:var(--primary-blue);"></i>
                                        Email
                                    </label>
                                    <input type="email" name="email" class="form-control search-input" required
                                           style="background:white; color:var(--text-primary); padding:1rem 1rem 1rem 2.5rem; border-radius:12px; border:2px solid var(--border-color);"
                                           value="{{ old('email') }}">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group mt-3">
                            <label style="font-weight:500; color:var(--text-primary);">
                                <i class="bi bi-phone" style="margin-right:8px; color:var(--primary-blue);"></i>
                                Số điện thoại / Mobile
                            </label>
                            <input type="text" name="mobile" class="form-control search-input"
                                   style="background:white; color:var(--text-primary); padding:1rem 1rem 1rem 2.5rem; border-radius:12px; border:2px solid var(--border-color);"
                                   value="{{ old('mobile') }}">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mt-3">
                                    <label style="font-weight:500; color:var(--text-primary);">
                                        <i class="bi bi-lock" style="margin-right:8px; color:var(--primary-blue);"></i>
                                        Mật khẩu / Password
                                    </label>
                                    <input type="password" name="password" class="form-control search-input" required
                                           style="background:white; color:var(--text-primary); padding:1rem 1rem 1rem 2.5rem; border-radius:12px; border:2px solid var(--border-color);">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mt-3">
                                    <label style="font-weight:500; color:var(--text-primary);">
                                        <i class="bi bi-lock-fill" style="margin-right:8px; color:var(--primary-blue);"></i>
                                        Nhập lại mật khẩu
                                    </label>
                                    <input type="password" name="password_confirmation" class="form-control search-input" required
                                           style="background:white; color:var(--text-primary); padding:1rem 1rem 1rem 2.5rem; border-radius:12px; border:2px solid var(--border-color);">
                                </div>
                            </div>
                        </div>
                        
                        @if($errors->any())
                            <div class="alert" style="background:linear-gradient(135deg, #fee2e2, #fecaca); color:var(--danger-red); 
                                        padding:1rem 1.5rem; border-radius:12px; margin-top:1rem; font-weight:500; 
                                        border:1px solid rgba(239, 68, 68, 0.3);">
                                <i class="bi bi-exclamation-triangle" style="margin-right:8px;"></i>
                                {{ $errors->first() }}
                            </div>
                        @endif
                        
                        <button type="submit" class="btn btn-primary-custom w-100 mt-4" 
                                style="padding:1.25rem; font-size:1.1rem;">
                            <i class="bi bi-person-plus"></i>
                            Đăng ký / Register
                        </button>
                        
                        <div class="mt-4 text-center">
                            <a href="{{ route('login') }}" 
                               style="color:var(--primary-blue); font-weight:500; text-decoration:none; 
                                      padding:0.5rem 1rem; border-radius:20px; transition:all 0.3s ease;
                                      display:inline-flex; align-items:center; gap:8px;"
                               onmouseover="this.style.background='var(--light-blue)'"
                               onmouseout="this.style.background='transparent'">
                                <i class="bi bi-box-arrow-in-right"></i>
                                Đã có tài khoản? Đăng nhập / Login
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@extends('layouts.admin')

@section('title', 'Đổi mật khẩu')
@section('page_pretitle', 'Tài khoản')
@section('page_title', 'Đổi mật khẩu')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Đổi mật khẩu</li>
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-6">
            <form method="POST" action="{{ route('admin.account.update') }}">
                @csrf
                @method('PUT')
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Đổi mật khẩu đăng nhập admin</h3>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label required">Mật khẩu hiện tại</label>
                            <input type="password" class="form-control @error('current_password') is-invalid @enderror"
                                   name="current_password" required autofocus>
                            @error('current_password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label required">Mật khẩu mới</label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror"
                                   name="password" placeholder="Ít nhất 8 ký tự" required>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label required">Xác nhận mật khẩu mới</label>
                            <input type="password" class="form-control" name="password_confirmation" required>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <circle cx="12" cy="12" r="2"/>
                                    <path d="M12 1v6m0 6v6"/>
                                </svg>
                                Đổi mật khẩu
                            </button>
                            <a href="{{ route('admin.dashboard') }}" class="btn btn-link ms-auto">Huỷ</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@extends('layouts.admin')

@section('title', 'Đổi mật khẩu')
@section('page_pretitle', 'Tài khoản')
@section('page_title', 'Đổi mật khẩu')

@section('breadcrumbs')
    <i class="ti ti-chevron-right"></i><span class="current">Đổi mật khẩu</span>
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
                                <i class="ti ti-eye me-2"></i>
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

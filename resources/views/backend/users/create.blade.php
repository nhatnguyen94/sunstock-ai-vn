@extends('layouts.admin')

@section('title', 'Tạo User mới')
@section('page_pretitle', 'Hệ thống')
@section('page_title', 'Tạo User mới')

@section('breadcrumbs')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
    </li>
    <li class="breadcrumb-item">
        <a href="{{ route('admin.users.index') }}">Users</a>
    </li>
    <li class="breadcrumb-item active">Tạo mới</li>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.users.store') }}">
        @csrf
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Thông tin User</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label required">Tên đầy đủ</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                   name="name" value="{{ old('name') }}" placeholder="Nhập tên đầy đủ" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label required">Email</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                   name="email" value="{{ old('email') }}" placeholder="user@example.com" required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label required">Mật khẩu</label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror" 
                                   name="password" placeholder="Ít nhất 8 ký tự" required>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label required">Xác nhận mật khẩu</label>
                            <input type="password" class="form-control" 
                                   name="password_confirmation" placeholder="Nhập lại mật khẩu" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="form-label required">Vai trò (có thể chọn nhiều)</label>
                            <div class="form-selectgroup form-selectgroup-boxes d-flex flex-wrap gap-2">
                                @foreach($roles as $role)
                                    <label class="form-selectgroup-item flex-fill">
                                        <input type="checkbox" name="roles[]" value="{{ $role->name }}" 
                                               class="form-selectgroup-input @error('roles') is-invalid @enderror"
                                               {{ in_array($role->name, old('roles', [])) ? 'checked' : '' }}>
                                        <span class="form-selectgroup-label d-flex align-items-center p-3">
                                            <span class="me-3">
                                                @switch($role->name)
                                                    @case('admin')
                                                        <span class="badge bg-red-lt">Admin</span>
                                                        @break
                                                    @case('webadmin')
                                                        <span class="badge bg-blue-lt">Web Admin</span>
                                                        @break
                                                    @case('admin_support')
                                                        <span class="badge bg-yellow-lt">Admin Support</span>
                                                        @break
                                                    @default
                                                        <span class="badge bg-gray-lt">User</span>
                                                @endswitch
                                            </span>
                                            <span>
                                                <strong>{{ $role->display_name }}</strong><br>
                                                <small class="text-muted">
                                                    @switch($role->name)
                                                        @case('admin')
                                                            Toàn quyền hệ thống
                                                            @break
                                                        @case('webadmin')
                                                            Quản lý nội dung
                                                            @break
                                                        @case('admin_support')
                                                            Hỗ trợ quản trị
                                                            @break
                                                        @default
                                                            Người dùng thông thường
                                                    @endswitch
                                                </small>
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('roles')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            @error('roles.*')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <small class="form-hint">
                                Chọn ít nhất một vai trò cho người dùng. Mỗi vai trò sẽ có quyền hạn riêng biệt.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="d-flex">
                    <button type="submit" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                            <circle cx="12" cy="7" r="4"/>
                            <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"/>
                            <line x1="19" y1="7" x2="19" y2="14"/>
                            <line x1="22" y1="10.5" x2="16" y2="10.5"/>
                        </svg>
                        Tạo User
                    </button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-link ms-auto">Hủy</a>
                </div>
            </div>
        </div>
    </form>
@endsection
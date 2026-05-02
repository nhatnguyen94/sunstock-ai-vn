@extends('layouts.admin')

@section('title', 'Chỉnh sửa User')
@section('page_pretitle', 'Hệ thống')
@section('page_title', 'Chỉnh sửa User: ' . $user->name)

@section('breadcrumbs')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
    </li>
    <li class="breadcrumb-item">
        <a href="{{ route('admin.users.index') }}">Users</a>
    </li>
    <li class="breadcrumb-item">
        <a href="{{ route('admin.users.show', $user) }}">{{ $user->name }}</a>
    </li>
    <li class="breadcrumb-item active">Chỉnh sửa</li>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.users.update', $user) }}">
        @csrf
        @method('PUT')
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
                                   name="name" value="{{ old('name', $user->name) }}" placeholder="Nhập tên đầy đủ" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label required">Email</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                   name="email" value="{{ old('email', $user->email) }}" placeholder="user@example.com" required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Mật khẩu mới (để trống nếu không đổi)</label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror" 
                                   name="password" placeholder="Ít nhất 8 ký tự">
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-hint">Để trống nếu không muốn thay đổi mật khẩu.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Xác nhận mật khẩu mới</label>
                            <input type="password" class="form-control" 
                                   name="password_confirmation" placeholder="Nhập lại mật khẩu">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="form-label required">Vai trò (có thể chọn nhiều)</label>
                            <div class="form-selectgroup form-selectgroup-boxes d-flex flex-wrap gap-2">
                                @foreach($roles as $role)
                                    @php
                                        $currentRoleNames = old('roles', $user->roles->pluck('name')->toArray());
                                        $isChecked = in_array($role->name, $currentRoleNames);
                                    @endphp
                                    <label class="form-selectgroup-item flex-fill">
                                        <input type="checkbox" name="roles[]" value="{{ $role->name }}" 
                                               class="form-selectgroup-input @error('roles') is-invalid @enderror"
                                               {{ $isChecked ? 'checked' : '' }}>
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

                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="form-label">Trạng thái tài khoản</label>
                            <div class="form-selectgroup">
                                <label class="form-selectgroup-item">
                                    <input type="radio" name="status" value="active" 
                                           class="form-selectgroup-input" {{ $user->email_verified_at ? 'checked' : '' }}>
                                    <span class="form-selectgroup-label">
                                        <span class="form-selectgroup-check"></span>
                                        Hoạt động
                                    </span>
                                </label>
                                <label class="form-selectgroup-item">
                                    <input type="radio" name="status" value="inactive" 
                                           class="form-selectgroup-input" {{ !$user->email_verified_at ? 'checked' : '' }}>
                                    <span class="form-selectgroup-label">
                                        <span class="form-selectgroup-check"></span>
                                        Chưa kích hoạt
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Warning for self-edit -->
                @if($user->id === auth()->id())
                <div class="alert alert-warning" role="alert">
                    <div class="d-flex">
                        <div>
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="9"/>
                                <line x1="12" y1="8" x2="12" y2="12"/>
                                <line x1="12" y1="16" x2="12.01" y2="16"/>
                            </svg>
                        </div>
                        <div>
                            <h4 class="alert-title">Cảnh báo!</h4>
                            <div class="text-muted">Bạn đang chỉnh sửa tài khoản của chính mình. Hãy cẩn thận khi thay đổi vai trò hoặc mật khẩu.</div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
            <div class="card-footer">
                <div class="d-flex">
                    <button type="submit" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                            <circle cx="12" cy="12" r="2"/>
                            <path d="M12 1v6m0 6v6"/>
                        </svg>
                        Cập nhật User
                    </button>
                    <a href="{{ route('admin.users.show', $user) }}" class="btn btn-link ms-auto">Hủy</a>
                </div>
            </div>
        </div>
    </form>
@endsection
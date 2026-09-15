@extends('layouts.admin')

@section('title', 'Chỉnh sửa Vai trò')
@section('page_pretitle', 'Hệ thống')
@section('page_title', 'Chỉnh sửa Vai trò: ' . $role->display_name)

@section('breadcrumbs')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
    </li>
    <li class="breadcrumb-item">
        <a href="{{ route('admin.roles.index') }}">Vai trò</a>
    </li>
    <li class="breadcrumb-item active">Chỉnh sửa</li>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.roles.update', $role) }}">
        @csrf
        @method('PUT')
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Thông tin Vai trò</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label required">Tên hệ thống (slug)</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                   name="name" value="{{ old('name', $role->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label required">Tên hiển thị</label>
                            <input type="text" class="form-control @error('display_name') is-invalid @enderror"
                                   name="display_name" value="{{ old('display_name', $role->display_name) }}" required>
                            @error('display_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="form-label">Quyền hạn</label>
                            @php
                                $currentPermissionIds = old('permissions', $role->permissions->pluck('id')->toArray());
                            @endphp
                            @forelse($permissions as $group => $items)
                                <div class="mb-2">
                                    <div class="text-muted text-uppercase small fw-bold mb-2">{{ $group }}</div>
                                    <div class="form-selectgroup form-selectgroup-boxes d-flex flex-wrap gap-2 mb-3">
                                        @foreach($items as $permission)
                                            <label class="form-selectgroup-item">
                                                <input type="checkbox" name="permissions[]" value="{{ $permission->id }}"
                                                       class="form-selectgroup-input"
                                                       {{ in_array($permission->id, $currentPermissionIds) ? 'checked' : '' }}>
                                                <span class="form-selectgroup-label d-flex align-items-center p-2">
                                                    <span>
                                                        <strong>{{ $permission->display_name }}</strong><br>
                                                        <small class="text-muted"><code>{{ $permission->name }}</code></small>
                                                    </span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @empty
                                <div class="text-muted">
                                    Chưa có quyền hạn nào. <a href="{{ route('admin.permissions.create') }}">Tạo quyền hạn mới</a> trước.
                                </div>
                            @endforelse
                            @error('permissions')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <small class="form-hint">Có thể chọn nhiều quyền — nhiều vai trò được phép cùng chia sẻ một quyền.</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="text-muted small">
                            Đang được gán cho <strong>{{ $role->users->count() }}</strong> user.
                        </div>
                    </div>
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
                        Cập nhật Vai trò
                    </button>
                    <a href="{{ route('admin.roles.index') }}" class="btn btn-link ms-auto">Hủy</a>
                </div>
            </div>
        </div>
    </form>
@endsection

@extends('layouts.admin')

@section('title', 'Chỉnh sửa Quyền hạn')
@section('page_pretitle', 'Hệ thống')
@section('page_title', 'Chỉnh sửa Quyền hạn: ' . $permission->display_name)

@section('breadcrumbs')
    <i class="ti ti-chevron-right"></i><a href="{{ route('admin.permissions.index') }}">Quyền hạn</a>
    <i class="ti ti-chevron-right"></i><span class="current">Chỉnh sửa</span>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.permissions.update', $permission) }}">
        @csrf
        @method('PUT')
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Thông tin Quyền hạn</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label required">Tên hệ thống (slug)</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                   name="name" value="{{ old('name', $permission->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-hint">Đổi tên ở đây sẽ làm mất tác dụng với mọi route đang dùng tên cũ trong <code>can:&lt;tên&gt;</code>.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label required">Tên hiển thị</label>
                            <input type="text" class="form-control @error('display_name') is-invalid @enderror"
                                   name="display_name" value="{{ old('display_name', $permission->display_name) }}" required>
                            @error('display_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Nhóm</label>
                            <input type="text" class="form-control @error('group') is-invalid @enderror"
                                   name="group" value="{{ old('group', $permission->group) }}" list="permission-groups">
                            <datalist id="permission-groups">
                                <option value="Hệ thống">
                                <option value="Tính năng">
                            </datalist>
                            @error('group')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="text-muted small">
                            Đang được gán cho @if($permission->roles->isEmpty()) <strong>0</strong> vai trò. @else
                            @foreach($permission->roles as $role)
                                <span class="badge badge-outline text-blue">{{ $role->display_name }}</span>
                            @endforeach
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="d-flex">
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-eye me-2"></i>
                        Cập nhật Quyền hạn
                    </button>
                    <a href="{{ route('admin.permissions.index') }}" class="btn btn-link ms-auto">Hủy</a>
                </div>
            </div>
        </div>
    </form>
@endsection

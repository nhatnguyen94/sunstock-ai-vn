@extends('layouts.admin')

@section('title', 'Tạo Vai trò mới')
@section('page_pretitle', 'Hệ thống')
@section('page_title', 'Tạo Vai trò mới')

@section('breadcrumbs')
    <i class="ti ti-chevron-right"></i><a href="{{ route('admin.roles.index') }}">Vai trò</a>
    <i class="ti ti-chevron-right"></i><span class="current">Tạo mới</span>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.roles.store') }}">
        @csrf
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
                                   name="name" value="{{ old('name') }}" placeholder="vd: moderator" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-hint">Chữ, số, gạch ngang/gạch dưới, không dấu, không khoảng trắng. Không đổi được ý nghĩa sau khi role đã được dùng ở nơi khác trong code.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label required">Tên hiển thị</label>
                            <input type="text" class="form-control @error('display_name') is-invalid @enderror"
                                   name="display_name" value="{{ old('display_name') }}" placeholder="vd: Điều phối viên" required>
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
                            @forelse($permissions as $group => $items)
                                <div class="mb-2">
                                    <div class="text-muted text-uppercase small fw-bold mb-2">{{ $group }}</div>
                                    <div class="form-selectgroup form-selectgroup-boxes d-flex flex-wrap gap-2 mb-3">
                                        @foreach($items as $permission)
                                            <label class="form-selectgroup-item">
                                                <input type="checkbox" name="permissions[]" value="{{ $permission->id }}"
                                                       class="form-selectgroup-input"
                                                       {{ in_array($permission->id, old('permissions', [])) ? 'checked' : '' }}>
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
            </div>
            <div class="card-footer">
                <div class="d-flex">
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-plus me-2"></i>
                        Tạo Vai trò
                    </button>
                    <a href="{{ route('admin.roles.index') }}" class="btn btn-link ms-auto">Hủy</a>
                </div>
            </div>
        </div>
    </form>
@endsection

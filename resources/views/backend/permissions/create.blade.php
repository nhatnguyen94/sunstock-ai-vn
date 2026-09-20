@extends('layouts.admin')

@section('title', 'Tạo Quyền hạn mới')
@section('page_pretitle', 'Hệ thống')
@section('page_title', 'Tạo Quyền hạn mới')

@section('breadcrumbs')
    <i class="ti ti-chevron-right"></i><a href="{{ route('admin.permissions.index') }}">Quyền hạn</a>
    <i class="ti ti-chevron-right"></i><span class="current">Tạo mới</span>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.permissions.store') }}">
        @csrf
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
                                   name="name" value="{{ old('name') }}" placeholder="vd: manage-alerts" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-hint">Dùng nguyên văn trong route: <code>can:&lt;tên&gt;</code>. Chữ, số, gạch ngang, không dấu, không khoảng trắng.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label required">Tên hiển thị</label>
                            <input type="text" class="form-control @error('display_name') is-invalid @enderror"
                                   name="display_name" value="{{ old('display_name') }}" placeholder="vd: Quản lý Cảnh báo" required>
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
                                   name="group" value="{{ old('group') }}" placeholder="vd: Tính năng" list="permission-groups">
                            <datalist id="permission-groups">
                                <option value="Hệ thống">
                                <option value="Tính năng">
                            </datalist>
                            @error('group')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-hint">Chỉ dùng để gom nhóm hiển thị ở form gán quyền cho vai trò, không bắt buộc.</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="d-flex">
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-plus me-2"></i>
                        Tạo Quyền hạn
                    </button>
                    <a href="{{ route('admin.permissions.index') }}" class="btn btn-link ms-auto">Hủy</a>
                </div>
            </div>
        </div>
    </form>
@endsection

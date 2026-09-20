@extends('layouts.admin')

@section('title', 'Quản lý Quyền hạn')
@section('page_pretitle', 'Hệ thống')
@section('page_title', 'Quản lý Quyền hạn')

@section('breadcrumbs')
    <i class="ti ti-chevron-right"></i><span class="current">Quyền hạn</span>
@endsection

@section('page_actions')
    <a href="{{ route('admin.permissions.create') }}" class="btn btn-primary">
        <i class="ti ti-plus me-2"></i>
        Tạo Quyền hạn mới
    </a>
@endsection

@section('content')
    <div class="alert alert-info" role="alert">
        <div class="d-flex">
            <div>
                <i class="ti ti-info-circle"></i>
            </div>
            <div>
                Tên quyền (vd: <code>manage-alerts</code>) dùng trực tiếp trong route/middleware dạng <code>can:manage-alerts</code>
                hoặc Blade <code>@@can('manage-alerts')</code>. Tạo quyền ở đây rồi gán vào <a href="{{ route('admin.roles.index') }}">Vai trò</a> —
                không cần deploy lại code cho những route đã sẵn dùng permission-based gate.
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Danh sách Quyền hạn</h3>
            <div class="card-actions">
                <span class="text-muted">{{ $permissions->count() }} quyền</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Quyền hạn</th>
                        <th>Tên hệ thống</th>
                        <th>Nhóm</th>
                        <th>Dùng bởi</th>
                        <th class="w-1">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($permissions as $permission)
                    <tr>
                        <td class="fw-medium">{{ $permission->display_name }}</td>
                        <td><code>{{ $permission->name }}</code></td>
                        <td>
                            @if($permission->group)
                                <span class="badge bg-azure-lt">{{ $permission->group }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-green-lt">{{ $permission->roles_count }} vai trò</span>
                        </td>
                        <td>
                            <div class="table-row-actions">
                                <a href="{{ route('admin.permissions.edit', $permission) }}" class="btn btn-sm btn-icon btn-ghost-secondary">
                                    <i class="ti ti-pencil"></i>
                                </a>
                                <form method="POST" action="{{ route('admin.permissions.destroy', $permission) }}" class="d-inline"
                                      data-confirm="Bạn có chắc chắn muốn xoá quyền hạn này?" data-confirm-title="Xác nhận xóa" data-confirm-ok="Xóa">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-icon btn-ghost-secondary">
                                        <i class="ti ti-trash text-danger"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">Chưa có quyền hạn nào.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

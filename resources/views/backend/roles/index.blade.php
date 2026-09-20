@extends('layouts.admin')

@section('title', 'Quản lý Vai trò')
@section('page_pretitle', 'Hệ thống')
@section('page_title', 'Quản lý Vai trò')

@section('breadcrumbs')
    <i class="ti ti-chevron-right"></i><span class="current">Vai trò</span>
@endsection

@section('page_actions')
    <a href="{{ route('admin.roles.create') }}" class="btn btn-primary">
        <i class="ti ti-plus me-2"></i>
        Tạo Vai trò mới
    </a>
@endsection

@section('content')
    <div class="alert alert-info" role="alert">
        <div class="d-flex">
            <div>
                <i class="ti ti-info-circle"></i>
            </div>
            <div>
                Mỗi vai trò là một tập hợp <strong>Quyền hạn</strong> có thể tuỳ ý gán chồng lấn lên nhau.
                Tạo vai trò mới, gán quyền, rồi gán vai trò đó cho user ở <a href="{{ route('admin.users.index') }}">Quản lý Users</a> —
                không cần sửa code. Xem <a href="{{ route('admin.permissions.index') }}">Quyền hạn</a> để tạo permission mới.
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Danh sách Vai trò</h3>
            <div class="card-actions">
                <span class="text-muted">{{ $roles->count() }} vai trò</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Vai trò</th>
                        <th>Tên hệ thống</th>
                        <th>Quyền hạn</th>
                        <th>Users</th>
                        <th class="w-1">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($roles as $role)
                    <tr>
                        <td class="fw-medium">{{ $role->display_name }}</td>
                        <td><code>{{ $role->name }}</code></td>
                        <td>
                            <span class="badge bg-blue-lt">{{ $role->permissions_count }} quyền</span>
                        </td>
                        <td>
                            <span class="badge bg-green-lt">{{ $role->users_count }} user</span>
                        </td>
                        <td>
                            <div class="table-row-actions">
                                <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-icon btn-ghost-secondary">
                                    <i class="ti ti-pencil"></i>
                                </a>
                                <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="d-inline"
                                      data-confirm="Bạn có chắc chắn muốn xoá vai trò này?" data-confirm-title="Xác nhận xóa" data-confirm-ok="Xóa">
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
                        <td colspan="5" class="text-center text-muted py-4">Chưa có vai trò nào.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

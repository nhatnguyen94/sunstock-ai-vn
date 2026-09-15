@extends('layouts.admin')

@section('title', 'Quản lý Quyền hạn')
@section('page_pretitle', 'Hệ thống')
@section('page_title', 'Quản lý Quyền hạn')

@section('breadcrumbs')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
    </li>
    <li class="breadcrumb-item active">Quyền hạn</li>
@endsection

@section('page_actions')
    <a href="{{ route('admin.permissions.create') }}" class="btn btn-primary">
        <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
            <line x1="12" y1="5" x2="12" y2="19"/>
            <line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Tạo Quyền hạn mới
    </a>
@endsection

@section('content')
    <div class="alert alert-info" role="alert">
        <div class="d-flex">
            <div>
                <svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <circle cx="12" cy="12" r="9"/>
                    <line x1="12" y1="8" x2="12.01" y2="8"/>
                    <polyline points="11 12 12 12 12 16 13 16"/>
                </svg>
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
                        <td class="font-weight-medium">{{ $permission->display_name }}</td>
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
                            <div class="btn-group" role="group">
                                <a href="{{ route('admin.permissions.edit', $permission) }}" class="btn btn-sm btn-outline-warning">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"/>
                                        <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z"/>
                                    </svg>
                                </a>
                                <form method="POST" action="{{ route('admin.permissions.destroy', $permission) }}" class="d-inline"
                                      onsubmit="return confirm('Bạn có chắc chắn muốn xoá quyền hạn này?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                            <line x1="4" y1="7" x2="20" y2="7"/>
                                            <line x1="10" y1="11" x2="10" y2="17"/>
                                            <line x1="14" y1="11" x2="14" y2="17"/>
                                            <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"/>
                                            <path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"/>
                                        </svg>
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

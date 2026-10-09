@extends('layouts.admin')
@use('App\Models\Role')
@use('App\Models\User')
@use('App\Backend\Services\UserBulkService')

@section('title', 'Quản lý Users')
@section('page_pretitle', 'Hệ thống')
@section('page_title', 'Quản lý Users')

@section('breadcrumbs')
    <i class="ti ti-chevron-right"></i><span class="current">Users</span>
@endsection

@section('page_actions')
    @if(auth()->user()->hasRole(Role::ADMIN))
    <a href="{{ route('admin.users.export', request()->only('search', 'status')) }}" class="btn btn-outline-secondary"><i class="ti ti-file-export me-1"></i> Xuất CSV</a>
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><i class="ti ti-user-plus me-1"></i> Tạo User mới</a>
    @endif
@endsection

@section('content')
    @php $isAdmin = auth()->user()->hasRole(Role::ADMIN); @endphp
    @if($isAdmin)
    <form method="POST" action="{{ route('admin.users.bulk') }}" id="bulkForm" class="card mb-3" hidden>
        @csrf
        <div class="card-body d-flex flex-wrap align-items-center gap-2 py-2">
            <strong><span id="bulkCount">0</span> user đã chọn</strong>
            <select name="action" class="form-select w-auto" aria-label="Thao tác hàng loạt" required>
                <option value="">Chọn thao tác…</option>
                @foreach(UserBulkService::ACTIONS as $key => $label)<option value="{{ $key }}">{{ ucfirst($label) }}</option>@endforeach
            </select>
            <button class="btn btn-primary">Áp dụng</button>
            <span class="text-secondary small">Mỗi tài khoản đi qua cùng các quy tắc như khi sửa riêng lẻ (không tự khóa mình, không đụng admin cuối cùng).</span>
        </div>
    </form>
    @endif

    <div class="card">
        <div class="card-header">
            <form method="GET" action="{{ route('admin.users.index') }}" class="ad-toolbar flex-fill">
                <div class="input-icon" style="min-width:260px">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="search" class="form-control" name="search" value="{{ request('search') }}" placeholder="Tìm theo tên hoặc email…" aria-label="Tìm user">
                </div>
                <select name="status" class="form-select" style="max-width:190px" aria-label="Lọc theo trạng thái">
                    <option value="">Mọi trạng thái</option>
                    @foreach(User::statusLabels() as $value => $label)
                        <option value="{{ $value }}" @selected((string) request('status') === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-primary">Tìm</button>
                @if(request()->hasAny(['search', 'status']))
                    <a href="{{ route('admin.users.index') }}" class="btn btn-ghost-secondary"><i class="ti ti-x me-1"></i>Xóa lọc</a>
                @endif
            </form>
            <span class="text-secondary small">{{ number_format($users->total()) }} users</span>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter table-hover card-table">
                <thead>
                    <tr>
                        @if($isAdmin)<th class="w-1"><input type="checkbox" class="form-check-input" id="bulkAll" aria-label="Chọn tất cả trên trang"></th>@endif
                        <th>User</th>
                        <th>Xác thực email</th>
                        <th>Vai trò</th>
                        <th>Ngày tạo</th>
                        <th class="w-1 text-end">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                    <tr>
                        @if($isAdmin)<td><input type="checkbox" class="form-check-input bulk-row" name="ids[]" value="{{ $user->id }}" form="bulkForm" aria-label="Chọn {{ $user->email }}"></td>@endif
                        <td>
                            <div class="ad-avatar-cell">
                                <span class="ad-avatar">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                                <div class="ad-lines">
                                    <b>{{ $user->name }}</b>
                                    <small>{{ $user->email }}@if($user->profile && $user->profile->username) · &#64;{{ $user->profile->username }}@endif</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="ad-dot {{ $user->status === User::STATUS_ACTIVE ? 'ok' : ($user->status === User::STATUS_PENDING ? 'warn' : 'bad') }}">{{ $user->statusLabel() }}</span>
                            @if($user->email_verified_at)
                                <div class="small text-secondary">Email xác thực {{ $user->email_verified_at->format('d/m/Y H:i') }}</div>
                            @else
                                <div class="small text-secondary">Email chưa xác thực</div>
                            @endif
                        </td>
                        <td>
                            @foreach($user->roles as $role)
                                <span class="badge bg-blue-lt">{{ $role->display_name }}</span>
                            @endforeach
                        </td>
                        <td class="text-secondary text-nowrap">{{ $user->created_at->format('d/m/Y H:i') }}</td>
                        <td class="text-end">
                            <div class="table-row-actions">
                                <a href="{{ route('admin.users.show', $user) }}" class="btn btn-sm btn-icon btn-ghost-secondary" title="Xem chi tiết"><i class="ti ti-eye"></i></a>
                                @if(auth()->user()->hasRole(Role::ADMIN))
                                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-icon btn-ghost-secondary" title="Chỉnh sửa"><i class="ti ti-pencil"></i></a>
                                @endif
                                @if($user->id !== auth()->id() && auth()->user()->hasRole(Role::ADMIN))
                                <div class="dropdown d-inline-block">
                                    <button type="button" class="btn btn-sm btn-icon btn-ghost-secondary" data-bs-toggle="dropdown" aria-label="Thêm thao tác"><i class="ti ti-dots-vertical"></i></button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        @if($user->email_verified_at)
                                            <form action="{{ route('admin.users.unverify', $user) }}" method="POST"
                                                  data-confirm="Hủy xác thực email cho {{ $user->email }}? User sẽ cần verify lại." data-confirm-ok="Hủy xác thực" data-confirm-title="Hủy xác thực email">
                                                @csrf
                                                <button type="submit" class="dropdown-item"><i class="ti ti-mail-off me-2"></i>Hủy xác thực email</button>
                                            </form>
                                        @else
                                            <form action="{{ route('admin.users.verify', $user) }}" method="POST"
                                                  data-confirm="Xác thực thủ công email cho {{ $user->email }}?" data-confirm-ok="Xác thực" data-confirm-tone="primary" data-confirm-title="Xác thực email">
                                                @csrf
                                                <button type="submit" class="dropdown-item"><i class="ti ti-mail-check me-2"></i>Xác thực thủ công</button>
                                            </form>
                                        @endif
                                        <div class="dropdown-divider"></div>
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                                              data-confirm="Bạn có chắc chắn muốn xóa user {{ $user->name }}? Thao tác này không thể hoàn tác." data-confirm-ok="Xóa user" data-confirm-title="Xóa user">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item text-danger"><i class="ti ti-trash me-2"></i>Xóa user</button>
                                        </form>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ $isAdmin ? 6 : 5 }}">
                            <div class="empty">
                                <div class="empty-icon"><i class="ti ti-users-minus"></i></div>
                                <p class="empty-title">Không tìm thấy users nào</p>
                                <p class="empty-subtitle text-secondary">Thử thay đổi từ khóa hoặc tạo user mới.</p>
                                <div class="empty-action"><a href="{{ route('admin.users.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>Tạo User đầu tiên</a></div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())
            <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="text-secondary small">Hiển thị {{ $users->firstItem() }}–{{ $users->lastItem() }} / {{ $users->total() }}</span>
                {{ $users->links() }}
            </div>
        @endif
    </div>
@endsection

@if($isAdmin)
@push('scripts')
<script>
(function () {
    var form = document.getElementById('bulkForm');
    if (!form) return;
    var rows = Array.prototype.slice.call(document.querySelectorAll('.bulk-row'));
    var all = document.getElementById('bulkAll');
    var count = document.getElementById('bulkCount');
    function refresh() {
        var n = rows.filter(function (r) { return r.checked; }).length;
        count.textContent = n;
        form.hidden = n === 0;
        if (all) all.checked = n > 0 && n === rows.length;
    }
    rows.forEach(function (r) { r.addEventListener('change', refresh); });
    if (all) all.addEventListener('change', function () { rows.forEach(function (r) { r.checked = all.checked; }); refresh(); });
})();
</script>
@endpush
@endif

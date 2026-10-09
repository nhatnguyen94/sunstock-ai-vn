@extends('layouts.admin')
@use('App\Models\User')

@section('title', 'Chi tiết User')
@section('page_pretitle', 'Hệ thống')
@section('page_title', $user->name)

@section('breadcrumbs')
    <i class="ti ti-chevron-right"></i><a href="{{ route('admin.users.index') }}">Users</a>
    <i class="ti ti-chevron-right"></i><span class="current">{{ $user->name }}</span>
@endsection

@section('page_actions')
    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary">
        <i class="ti ti-pencil me-2"></i>
        Chỉnh sửa
    </a>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-4">
            <!-- User Info Card -->
            <div class="card">
                <div class="card-body text-center">
                    <span class="avatar avatar-xl mb-3">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                    <h3 class="m-0 mb-1">{{ $user->name }}</h3>
                    <div class="text-muted">{{ $user->email }}</div>
                    <div class="mt-3">
                        @foreach($user->roles as $role)
                            <span class="badge badge-outline text-blue me-1">{{ $role->display_name }}</span>
                        @endforeach
                    </div>
                </div>
                <div class="d-flex">
                    <a href="{{ route('admin.users.edit', $user) }}" class="card-btn">
                        <i class="ti ti-pencil me-2"></i>
                        Chỉnh sửa
                    </a>
                </div>
            </div>

            <!-- Account Details -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3 class="card-title">Chi tiết tài khoản</h3>
                </div>
                <div class="list-group list-group-flush">
                    <div class="list-group-item">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <span class="status-dot {{ $user->status === User::STATUS_ACTIVE ? 'd-block' : 'status-dot-animated bg-red' }}"></span>
                            </div>
                            <div class="col text-truncate">
                                <strong>Trạng thái</strong>
                                <div class="d-block text-muted text-truncate">
                                    {{ $user->statusLabel() }} · {{ $user->email_verified_at ? 'email đã xác thực' : 'email chưa xác thực' }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="list-group-item">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <i class="ti ti-calendar"></i>
                            </div>
                            <div class="col text-truncate">
                                <strong>Ngày tạo</strong>
                                <div class="d-block text-muted text-truncate">
                                    {{ $user->created_at->format('d/m/Y H:i') }}
                                </div>
                            </div>
                        </div>
                    </div>
                    @if($user->profile && $user->profile->mobile)
                    <div class="list-group-item">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <i class="ti ti-phone"></i>
                            </div>
                            <div class="col text-truncate">
                                <strong>Số điện thoại</strong>
                                <div class="d-block text-muted text-truncate">
                                    {{ $user->profile->mobile }}
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                    <div class="list-group-item">
                        <strong>Đăng nhập gần nhất</strong>
                        <div class="text-muted small">
                            @if($insights['last_login'])
                                {{ $insights['last_login']->created_at->diffForHumans() }} · <code>{{ $insights['last_login']->ip ?? '—' }}</code>
                            @else chưa có dữ liệu @endif
                            @if($insights['failed_7d'] > 0)<br><span class="text-danger">{{ $insights['failed_7d'] }} lần nhập sai e-mail này trong 7 ngày</span>@endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <!-- User Portfolios -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Portfolios ({{ $user->portfolios->count() }})</h3>
                </div>
                <div class="card-body">
                    @if($user->portfolios->count() > 0)
                        <div class="divide-y">
                            @foreach($user->portfolios as $portfolio)
                            <div class="row">
                                <div class="col-auto">
                                    <span class="avatar bg-blue text-white">
                                        <i class="ti ti-device-desktop"></i>
                                    </span>
                                </div>
                                <div class="col">
                                    <div class="text-truncate">
                                        <strong>{{ $portfolio->name }}</strong>
                                    </div>
                                    <div class="text-muted">{{ $portfolio->description }}</div>
                                </div>
                                <div class="col-auto align-self-center">
                                    @if($portfolio->is_active)
                                        <span class="badge bg-success">Hoạt động</span>
                                    @else
                                        <span class="badge bg-secondary">Tạm dừng</span>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="empty">
                            <div class="empty-icon">
                                <i class="ti ti-device-desktop"></i>
                            </div>
                            <p class="empty-title">Chưa có portfolios nào</p>
                            <p class="empty-subtitle text-muted">User này chưa tạo portfolio nào.</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header"><h3 class="card-title"><i class="ti ti-star me-2 text-primary"></i>Theo dõi ({{ count($insights['watchlist']) }})</h3></div>
                        <div class="card-body">
                            @forelse($insights['watchlist'] as $sym)<a href="{{ url('/stock?symbol='.$sym) }}" target="_blank" rel="noopener" class="badge bg-blue-lt me-1 mb-1 text-decoration-none">{{ $sym }}</a>@empty<span class="text-secondary">Chưa theo dõi mã nào.</span>@endforelse
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header"><h3 class="card-title"><i class="ti ti-briefcase me-2 text-primary"></i>Đang nắm giữ ({{ count($insights['holdings']) }})</h3></div>
                        <div class="card-body">
                            @forelse($insights['holdings'] as $sym)<a href="{{ url('/stock?symbol='.$sym) }}" target="_blank" rel="noopener" class="badge bg-green-lt me-1 mb-1 text-decoration-none">{{ $sym }}</a>@empty<span class="text-secondary">Chưa có mã nào trong danh mục.</span>@endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="ti ti-robot me-2 text-primary"></i>AI</h3>
                    <span class="ms-auto small text-secondary">hôm nay {{ $insights['ai']['today'] }} · 7 ngày {{ $insights['ai']['week'] }} · lỗi {{ $insights['ai']['errors'] }}@if($insights['ai']['blocked']) · <span class="text-danger">đang bị khóa AI</span>@endif</span>
                </div>
                <div class="list-group list-group-flush">
                    @forelse($insights['ai']['recent'] as $q)
                        <div class="list-group-item d-flex gap-3"><span class="text-truncate flex-fill">{{ $q->question }}</span><span class="text-secondary small text-nowrap">{{ $q->created_at->diffForHumans() }}</span></div>
                    @empty
                        <div class="list-group-item text-secondary">Chưa hỏi AI câu nào.</div>
                    @endforelse
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-timeline-event me-2 text-primary"></i>Hoạt động gần đây</h3></div>
                <div class="list-group list-group-flush">
                    @forelse($insights['activity'] as $log)
                        <div class="list-group-item d-flex gap-3"><span class="text-truncate flex-fill">{{ $log->description }}</span><span class="text-secondary small text-nowrap">{{ $log->created_at->diffForHumans() }}</span></div>
                    @empty
                        <div class="list-group-item text-secondary">Chưa có hoạt động nào được ghi lại.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
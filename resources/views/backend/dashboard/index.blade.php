@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page_pretitle', 'Tổng quan hệ thống')
@section('page_title', 'Dashboard')

@section('breadcrumbs')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
    <!-- Statistics Cards -->
    <div class="row row-deck row-cards">
        <div class="col-sm-6 col-lg-3">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="subheader">Tổng Users</div>
                        <div class="ms-auto lh-1">
                            <div class="dropdown">
                                <a class="dropdown-toggle text-muted" href="#" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Tất cả</a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item active" href="#">Tất cả</a>
                                    <a class="dropdown-item" href="#">Hoạt động</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline">
                        <div class="h1 mb-0 me-2">{{ $stats['total_users'] ?? 0 }}</div>
                        <div class="me-auto">
                            <span class="text-green d-inline-flex align-items-center lh-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon ms-1" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><polyline points="3,17 9,11 13,15 21,7"/><polyline points="14,7 21,7 21,14"/></svg>
                                +8%
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-sm-6 col-lg-3">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="subheader">Portfolios</div>
                    </div>
                    <div class="d-flex align-items-baseline">
                        <div class="h1 mb-0 me-2">{{ $stats['total_portfolios'] ?? 0 }}</div>
                        <div class="me-auto">
                            <span class="text-blue d-inline-flex align-items-center lh-1">
                                {{ $stats['active_portfolios'] ?? 0 }} hoạt động
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-sm-6 col-lg-3">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="subheader">Stocks</div>
                    </div>
                    <div class="d-flex align-items-baseline">
                        <div class="h1 mb-0 me-2">{{ $stats['total_stocks'] ?? 0 }}</div>
                        <div class="me-auto">
                            <span class="text-yellow d-inline-flex align-items-center lh-1">
                                Cổ phiếu
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-sm-6 col-lg-3">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="subheader">Hệ thống</div>
                    </div>
                    <div class="d-flex align-items-baseline">
                        <div class="h1 mb-0 me-2">99.9%</div>
                        <div class="me-auto">
                            <span class="text-green d-inline-flex align-items-center lh-1">
                                Uptime
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row row-deck row-cards mt-4">
        <!-- Recent Users -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Users mới nhất</h3>
                </div>
                <div class="card-body">
                    @if(isset($recent_users) && $recent_users->count() > 0)
                        <div class="divide-y">
                            @foreach($recent_users as $user)
                            <div class="row">
                                <div class="col-auto">
                                    <span class="avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                                </div>
                                <div class="col">
                                    <div class="text-truncate">
                                        <strong>{{ $user->name }}</strong>
                                    </div>
                                    <div class="text-muted">{{ $user->email }}</div>
                                </div>
                                <div class="col-auto align-self-center">
                                    @foreach($user->roles as $role)
                                        <span class="badge badge-outline text-blue">{{ $role->display_name }}</span>
                                    @endforeach
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="empty">
                            <div class="empty-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="48" height="48" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><circle cx="12" cy="7" r="4"/><path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"/></svg>
                            </div>
                            <p class="empty-title">Chưa có users nào</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Recent Portfolios -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Portfolios hoạt động</h3>
                </div>
                <div class="card-body">
                    @if(isset($recent_portfolios) && $recent_portfolios->count() > 0)
                        <div class="divide-y">
                            @foreach($recent_portfolios as $portfolio)
                            <div class="row">
                                <div class="col-auto">
                                    <span class="avatar bg-blue text-white">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><rect x="3" y="4" width="18" height="12" rx="1"/><line x1="7" y1="20" x2="17" y2="20"/><line x1="9" y1="16" x2="15" y2="16"/></svg>
                                    </span>
                                </div>
                                <div class="col">
                                    <div class="text-truncate">
                                        <strong>{{ $portfolio->name }}</strong>
                                    </div>
                                    <div class="text-muted">{{ $portfolio->user->name }}</div>
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
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="48" height="48" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><rect x="3" y="4" width="18" height="12" rx="1"/><line x1="7" y1="20" x2="17" y2="20"/><line x1="9" y1="16" x2="15" y2="16"/></svg>
                            </div>
                            <p class="empty-title">Chưa có portfolios nào</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Hành động nhanh</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        @can('manage-users')
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('admin.users.create') }}" class="btn btn-primary w-100">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><circle cx="12" cy="7" r="4"/><path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"/><line x1="19" y1="7" x2="19" y2="14"/><line x1="22" y1="10.5" x2="16" y2="10.5"/></svg>
                                Tạo User
                            </a>
                        </div>
                        @endcan
                        
                        @can('manage-features')
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('admin.stocks.create') }}" class="btn btn-success w-100">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><polyline points="3,17 9,11 13,15 21,7"/><polyline points="14,7 21,7 21,14"/></svg>
                                Thêm Stock
                            </a>
                        </div>
                        @endcan
                        
                        @can('view-timeline')
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('admin.timeline') }}" class="btn btn-info w-100">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><circle cx="12" cy="12" r="2"/><path d="M12 1v6m0 6v6"/></svg>
                                Xem Timeline
                            </a>
                        </div>
                        @endcan
                        
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('home') }}" class="btn btn-outline-primary w-100">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l-2 0l9 -9l9 9l-2 0"/><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-7"/><path d="M9 21v-6a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v6"/></svg>
                                Về Frontend
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    // Auto refresh stats mỗi 30 giây
    setInterval(function() {
        // Có thể implement AJAX để cập nhật thống kê
        console.log('Stats refreshed');
    }, 30000);
</script>
@endpush
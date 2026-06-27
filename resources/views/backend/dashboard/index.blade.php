@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page_pretitle', 'Tổng quan hệ thống')
@section('page_title', 'Dashboard')

@section('breadcrumbs')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
    {{-- Row 1: Core stats --}}
    <div class="row row-deck row-cards">
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader mb-1">Tổng Users</div>
                    <div class="h1 mb-0">{{ number_format($stats['total_users']) }}</div>
                    <div class="text-muted small mt-1">
                        @can('manage-users')<a href="{{ route('admin.users.index') }}" class="text-decoration-none">Xem tất cả →</a>@endcan
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader mb-1">Portfolios</div>
                    <div class="h1 mb-0">{{ number_format($stats['total_portfolios']) }}</div>
                    <div class="text-muted small mt-1">{{ number_format($stats['active_portfolios']) }} đang hoạt động</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader mb-1">Cổ phiếu</div>
                    <div class="h1 mb-0">{{ number_format($stats['total_stocks']) }}</div>
                    <div class="text-muted small mt-1">{{ number_format($stats['stock_price_rows']) }} bản ghi giá</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader mb-1">Hoạt động hôm nay</div>
                    <div class="h1 mb-0">{{ number_format($stats['activity_today']) }}</div>
                    <div class="text-muted small mt-1">
                        @can('view-timeline')<a href="{{ route('admin.timeline') }}" class="text-decoration-none">Xem timeline →</a>@endcan
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Row 2: Data sync stats --}}
    <div class="row row-deck row-cards mt-3">
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader mb-1 text-purple">Tin tức</div>
                    <div class="h2 mb-0">{{ number_format($stats['total_news']) }}</div>
                    <div class="text-muted small mt-1">
                        Sync cuối: {{ $stats['news_last_sync'] ? \Carbon\Carbon::parse($stats['news_last_sync'])->diffForHumans() : 'Chưa có' }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader mb-1 text-green">Tỷ giá</div>
                    <div class="h2 mb-0">{{ number_format($stats['exchange_rate_rows']) }}</div>
                    <div class="text-muted small mt-1">
                        Sync cuối: {{ $stats['exchange_last_sync'] ? \Carbon\Carbon::parse($stats['exchange_last_sync'])->diffForHumans() : 'Chưa có' }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader mb-1 text-orange">Ngành hot</div>
                    <div class="h2 mb-0">{{ number_format($stats['hot_industry_rows']) }}</div>
                    <div class="text-muted small mt-1">Cổ phiếu ngành hot</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader mb-1 text-indigo">Tài chính DN</div>
                    <div class="h2 mb-0">{{ number_format($stats['financials_rows']) }}</div>
                    <div class="text-muted small mt-1">
                        <a href="{{ route('admin.sync-status') }}" class="text-decoration-none">Xem sync status →</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Row 3: Recent users + portfolios --}}
    <div class="row row-deck row-cards mt-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Users mới nhất</h3>
                    @can('manage-users')
                    <a href="{{ route('admin.users.index') }}" class="card-options-link text-muted small">Xem tất cả</a>
                    @endcan
                </div>
                <div class="card-body p-0">
                    @forelse($recent_users as $user)
                    <div class="d-flex align-items-center px-3 py-2 border-bottom">
                        <span class="avatar avatar-sm me-3">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                        <div class="flex-fill overflow-hidden">
                            <div class="fw-semibold text-truncate">{{ $user->name }}</div>
                            <div class="text-muted small text-truncate">{{ $user->email }}</div>
                        </div>
                        <div class="ms-2 text-nowrap">
                            @foreach($user->roles as $role)
                                <span class="badge badge-outline text-blue">{{ $role->display_name }}</span>
                            @endforeach
                        </div>
                    </div>
                    @empty
                    <div class="p-3 text-muted text-center">Chưa có user nào.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Portfolios hoạt động</h3>
                    @can('manage-features')
                    <a href="{{ route('admin.portfolios.index') }}" class="card-options-link text-muted small">Xem tất cả</a>
                    @endcan
                </div>
                <div class="card-body p-0">
                    @forelse($recent_portfolios as $portfolio)
                    <div class="d-flex align-items-center px-3 py-2 border-bottom">
                        <span class="avatar avatar-sm bg-blue text-white me-3">P</span>
                        <div class="flex-fill overflow-hidden">
                            <div class="fw-semibold text-truncate">{{ $portfolio->name }}</div>
                            <div class="text-muted small">{{ $portfolio->user->name }}</div>
                        </div>
                        <span class="badge bg-success-lt ms-2">Hoạt động</span>
                    </div>
                    @empty
                    <div class="p-3 text-muted text-center">Chưa có portfolio nào.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Row 4: Recent activity + Quick actions --}}
    <div class="row row-deck row-cards mt-3">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Hoạt động gần đây</h3>
                    @can('view-timeline')
                    <a href="{{ route('admin.timeline') }}" class="card-options-link text-muted small">Xem tất cả</a>
                    @endcan
                </div>
                <div class="card-body p-0">
                    @forelse($recent_activity as $log)
                    @php $cfg = \App\Models\ActivityLog::iconConfig()[$log->event_type] ?? ['color' => 'gray']; @endphp
                    <div class="d-flex align-items-start px-3 py-2 border-bottom">
                        <span class="badge bg-{{ $cfg['color'] }}-lt me-3 mt-1">&nbsp;</span>
                        <div class="flex-fill overflow-hidden">
                            <div class="text-truncate">
                                <strong>{{ $log->user_name }}</strong> — {{ $log->description }}
                            </div>
                            <div class="text-muted small">{{ $log->created_at->diffForHumans() }}</div>
                        </div>
                    </div>
                    @empty
                    <div class="p-3 text-muted text-center">Chưa có hoạt động nào được ghi nhận.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Hành động nhanh</h3></div>
                <div class="card-body d-grid gap-2">
                    @can('manage-users')
                    <a href="{{ route('admin.users.create') }}" class="btn btn-outline-primary">+ Tạo User mới</a>
                    @endcan
                    @can('manage-features')
                    <a href="{{ route('admin.sync-status') }}" class="btn btn-outline-purple">⟳ Sync Status</a>
                    <a href="{{ route('admin.news.index') }}" class="btn btn-outline-secondary">Quản lý News</a>
                    @endcan
                    <a href="{{ route('home') }}" class="btn btn-outline-secondary">← Về Frontend</a>
                </div>
            </div>
        </div>
    </div>
@endsection

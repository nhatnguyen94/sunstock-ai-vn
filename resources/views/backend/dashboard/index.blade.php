@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page_pretitle', 'Tổng quan hệ thống')
@section('page_title', 'Dashboard')

@section('breadcrumbs')
    <i class="ti ti-chevron-right"></i><span class="current">Dashboard</span>
@endsection

@section('page_actions')
    @can('manage-users')
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><i class="ti ti-user-plus me-1"></i> Tạo user</a>
    @endcan
    @can('manage-features')
        <a href="{{ route('admin.sync-status') }}" class="btn btn-outline-secondary"><i class="ti ti-refresh-dot me-1"></i> Sync status</a>
    @endcan
@endsection

@section('content')
@php
    $statusLabel = ['ok' => 'Ổn định', 'warn' => 'Hơi cũ', 'bad' => 'Cần kiểm tra', 'off' => 'Chưa có dữ liệu'];
@endphp

{{-- KPI row --}}
<div class="row row-deck row-cards g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <div class="card"><div class="ad-stat">
            <span class="ad-stat-icon blue"><i class="ti ti-users"></i></span>
            <div class="ad-stat-body">
                <div class="ad-stat-label">Người dùng</div>
                <div class="ad-stat-value">{{ number_format($stats['total_users']) }}</div>
                <div class="ad-stat-sub">
                    @if($stats['unverified_users'] > 0){{ number_format($stats['unverified_users']) }} chưa xác thực email @else Tất cả đã xác thực @endif
                    @can('manage-users') · <a href="{{ route('admin.users.index') }}">Xem tất cả</a>@endcan
                </div>
            </div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card"><div class="ad-stat">
            <span class="ad-stat-icon green"><i class="ti ti-briefcase"></i></span>
            <div class="ad-stat-body">
                <div class="ad-stat-label">Danh mục đầu tư</div>
                <div class="ad-stat-value">{{ number_format($stats['total_portfolios']) }}</div>
                <div class="ad-stat-sub">{{ number_format($stats['active_portfolios']) }} đang hoạt động · {{ number_format($stats['transactions']) }} giao dịch</div>
            </div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card"><div class="ad-stat">
            <span class="ad-stat-icon purple"><i class="ti ti-chart-candle"></i></span>
            <div class="ad-stat-body">
                <div class="ad-stat-label">Cổ phiếu theo dõi</div>
                <div class="ad-stat-value">{{ number_format($stats['total_stocks']) }}</div>
                <div class="ad-stat-sub">{{ number_format($stats['stock_price_rows']) }} bản ghi giá · {{ number_format($stats['watchlist_items']) }} lượt ★</div>
            </div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card"><div class="ad-stat">
            <span class="ad-stat-icon orange"><i class="ti ti-bolt"></i></span>
            <div class="ad-stat-body">
                <div class="ad-stat-label">Hoạt động hôm nay</div>
                <div class="ad-stat-value">{{ number_format($stats['activity_today']) }}</div>
                <div class="ad-stat-sub">@can('view-timeline')<a href="{{ route('admin.timeline') }}">Xem timeline</a>@else sự kiện được ghi nhận @endcan</div>
            </div>
        </div></div>
    </div>
</div>

<div class="row row-deck row-cards g-3 mb-3">
    {{-- Data sources health --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-database-heart me-2 text-primary"></i>Nguồn dữ liệu</h3>
                @can('manage-features')<a href="{{ route('admin.sync-status') }}" class="btn btn-sm btn-ghost-secondary">Chi tiết <i class="ti ti-arrow-right ms-1"></i></a>@endcan
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter">
                    <thead><tr><th>Nguồn</th><th>Trạng thái</th><th>Cập nhật cuối</th><th class="text-end">Bản ghi</th></tr></thead>
                    <tbody>
                    @foreach($sources as $s)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="ad-stat-icon blue" style="flex-basis:38px;height:38px;font-size:1.2rem;border-radius:11px"><i class="ti {{ $s['icon'] }}"></i></span>
                                    <div><div class="fw-semibold">{{ $s['label'] }}</div><div class="text-secondary small">{{ $s['note'] }}</div></div>
                                </div>
                            </td>
                            <td><span class="ad-dot {{ $s['status'] }}">{{ $statusLabel[$s['status']] }}</span></td>
                            <td class="text-nowrap">
                                @if($s['last'])
                                    <span title="{{ $s['last']->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') }}">{{ $s['last']->diffForHumans() }}</span>
                                @else <span class="text-secondary">Chưa có</span> @endif
                            </td>
                            <td class="text-end tabular-nums">{{ number_format($s['rows']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- System health --}}
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-heartbeat me-2 text-danger"></i>Sức khỏe hệ thống</h3></div>
            <div class="list-group list-group-flush">
                <div class="list-group-item d-flex align-items-center gap-3 py-3">
                    <span class="ad-stat-icon {{ $stats['failed_jobs'] > 0 ? 'red' : 'green' }}" style="flex-basis:38px;height:38px;font-size:1.2rem;border-radius:11px"><i class="ti ti-{{ $stats['failed_jobs'] > 0 ? 'alert-triangle' : 'circle-check' }}"></i></span>
                    <div class="flex-fill"><div class="fw-semibold">Job thất bại</div><div class="text-secondary small">{{ $stats['failed_jobs'] > 0 ? 'Cần xem lại hoặc thử lại' : 'Hàng đợi khỏe mạnh' }}</div></div>
                    @can('manage-queue')<a href="{{ route('admin.queue.index') }}" class="badge {{ $stats['failed_jobs'] > 0 ? 'bg-red-lt' : 'bg-green-lt' }} text-decoration-none">{{ number_format($stats['failed_jobs']) }}</a>@else<span class="badge {{ $stats['failed_jobs'] > 0 ? 'bg-red-lt' : 'bg-green-lt' }}">{{ number_format($stats['failed_jobs']) }}</span>@endcan
                </div>
                <div class="list-group-item d-flex align-items-center gap-3 py-3">
                    <span class="ad-stat-icon cyan" style="flex-basis:38px;height:38px;font-size:1.2rem;border-radius:11px"><i class="ti ti-mail-check"></i></span>
                    <div class="flex-fill"><div class="fw-semibold">Email chưa xác thực</div><div class="text-secondary small">Người dùng bị chặn khỏi danh mục</div></div>
                    <span class="badge bg-{{ $stats['unverified_users'] > 0 ? 'yellow' : 'green' }}-lt">{{ number_format($stats['unverified_users']) }}</span>
                </div>
                <div class="list-group-item d-flex align-items-center gap-3 py-3">
                    <span class="ad-stat-icon purple" style="flex-basis:38px;height:38px;font-size:1.2rem;border-radius:11px"><i class="ti ti-star"></i></span>
                    <div class="flex-fill"><div class="fw-semibold">Danh sách theo dõi</div><div class="text-secondary small">Mã được người dùng gắn ★</div></div>
                    <span class="badge bg-blue-lt">{{ number_format($stats['watchlist_items']) }}</span>
                </div>
                <div class="list-group-item d-flex align-items-center gap-3 py-3">
                    <span class="ad-stat-icon orange" style="flex-basis:38px;height:38px;font-size:1.2rem;border-radius:11px"><i class="ti ti-receipt"></i></span>
                    <div class="flex-fill"><div class="fw-semibold">Giao dịch trong sổ</div><div class="text-secondary small">Mua/bán được ghi nhận</div></div>
                    <span class="badge bg-blue-lt">{{ number_format($stats['transactions']) }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row row-deck row-cards g-3 mb-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Users mới nhất</h3>
                @can('manage-users')<a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-ghost-secondary">Xem tất cả</a>@endcan
            </div>
            <div class="list-group list-group-flush">
                @forelse($recent_users as $user)
                    <div class="list-group-item d-flex align-items-center gap-3">
                        <span class="ad-avatar">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                        <div class="flex-fill overflow-hidden">
                            <div class="fw-semibold text-truncate">{{ $user->name }}</div>
                            <div class="text-secondary small text-truncate">{{ $user->email }}</div>
                        </div>
                        <div class="text-nowrap">
                            @foreach($user->roles as $role)<span class="badge bg-blue-lt">{{ $role->display_name }}</span>@endforeach
                        </div>
                    </div>
                @empty
                    <div class="empty"><div class="empty-icon"><i class="ti ti-users"></i></div><p class="empty-title mb-0">Chưa có user nào.</p></div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Portfolios hoạt động</h3>
                @can('manage-features')<a href="{{ route('admin.portfolios.index') }}" class="btn btn-sm btn-ghost-secondary">Xem tất cả</a>@endcan
            </div>
            <div class="list-group list-group-flush">
                @forelse($recent_portfolios as $portfolio)
                    <div class="list-group-item d-flex align-items-center gap-3">
                        <span class="ad-stat-icon green" style="flex-basis:38px;height:38px;font-size:1.15rem;border-radius:11px"><i class="ti ti-briefcase"></i></span>
                        <div class="flex-fill overflow-hidden">
                            <div class="fw-semibold text-truncate">{{ $portfolio->name }}</div>
                            <div class="text-secondary small">{{ $portfolio->user->name }}</div>
                        </div>
                        <span class="ad-dot ok">Hoạt động</span>
                    </div>
                @empty
                    <div class="empty"><div class="empty-icon"><i class="ti ti-briefcase-off"></i></div><p class="empty-title mb-0">Chưa có portfolio nào.</p></div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="row row-deck row-cards g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Hoạt động gần đây</h3>
                @can('view-timeline')<a href="{{ route('admin.timeline') }}" class="btn btn-sm btn-ghost-secondary">Xem tất cả</a>@endcan
            </div>
            <div class="list-group list-group-flush">
                @forelse($recent_activity as $log)
                    @php $cfg = \App\Models\ActivityLog::iconConfig()[$log->event_type] ?? ['color' => 'gray']; @endphp
                    <div class="list-group-item d-flex align-items-start gap-3">
                        <span class="badge bg-{{ $cfg['color'] }}-lt mt-1" style="width:.7rem;height:.7rem;padding:0;border-radius:50%"></span>
                        <div class="flex-fill overflow-hidden">
                            <div class="text-truncate"><strong>{{ $log->user_name }}</strong> — {{ $log->description }}</div>
                            <div class="text-secondary small">{{ $log->created_at->diffForHumans() }}</div>
                        </div>
                    </div>
                @empty
                    <div class="empty"><div class="empty-icon"><i class="ti ti-timeline-event"></i></div><p class="empty-title mb-0">Chưa có hoạt động nào được ghi nhận.</p></div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title">Hành động nhanh</h3><span class="text-secondary small d-none d-md-inline"><kbd>Ctrl</kbd> + <kbd>K</kbd></span></div>
            <div class="card-body">
                <div class="row g-2">
                    @can('manage-users')
                    <div class="col-6"><a href="{{ route('admin.users.create') }}" class="btn btn-outline-secondary w-100 py-3 d-flex flex-column align-items-center gap-1"><i class="ti ti-user-plus fs-2 text-primary"></i>Tạo user</a></div>
                    @endcan
                    @can('manage-features')
                    <div class="col-6"><a href="{{ route('admin.sync-status') }}" class="btn btn-outline-secondary w-100 py-3 d-flex flex-column align-items-center gap-1"><i class="ti ti-refresh-dot fs-2 text-purple"></i>Sync status</a></div>
                    <div class="col-6"><a href="{{ route('admin.news.index') }}" class="btn btn-outline-secondary w-100 py-3 d-flex flex-column align-items-center gap-1"><i class="ti ti-news fs-2 text-orange"></i>Quản lý News</a></div>
                    <div class="col-6"><a href="{{ route('admin.stocks.index') }}" class="btn btn-outline-secondary w-100 py-3 d-flex flex-column align-items-center gap-1"><i class="ti ti-chart-candle fs-2 text-green"></i>Quản lý Stock</a></div>
                    @endcan
                    @can('manage-queue')
                    <div class="col-6"><a href="{{ route('admin.queue.index') }}" class="btn btn-outline-secondary w-100 py-3 d-flex flex-column align-items-center gap-1"><i class="ti ti-activity-heartbeat fs-2 text-red"></i>Giám sát Queue</a></div>
                    @endcan
                    <div class="col-6"><a href="{{ route('home') }}" class="btn btn-outline-secondary w-100 py-3 d-flex flex-column align-items-center gap-1"><i class="ti ti-world fs-2 text-cyan"></i>Về Frontend</a></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

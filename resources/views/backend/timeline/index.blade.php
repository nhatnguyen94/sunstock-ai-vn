@extends('layouts.admin')

@section('title', 'Timeline Hệ thống')
@section('page_pretitle', 'Theo dõi hoạt động thực tế')
@section('page_title', 'Timeline Hệ thống')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Timeline</li>
@endsection

@section('content')
    {{-- Filter --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.timeline') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Loại hoạt động</label>
                    <select class="form-select" name="type">
                        <option value="">Tất cả</option>
                        <option value="user_register"     {{ request('type') === 'user_register'     ? 'selected' : '' }}>Đăng ký user</option>
                        <option value="user_login"        {{ request('type') === 'user_login'        ? 'selected' : '' }}>Đăng nhập user</option>
                        <option value="admin_login"       {{ request('type') === 'admin_login'       ? 'selected' : '' }}>Đăng nhập admin</option>
                        <option value="portfolio_created" {{ request('type') === 'portfolio_created' ? 'selected' : '' }}>Tạo portfolio</option>
                        <option value="portfolio_deleted" {{ request('type') === 'portfolio_deleted' ? 'selected' : '' }}>Xóa portfolio</option>
                        <option value="stock_added"       {{ request('type') === 'stock_added'       ? 'selected' : '' }}>Thêm cổ phiếu</option>
                        <option value="stock_removed"     {{ request('type') === 'stock_removed'     ? 'selected' : '' }}>Xóa cổ phiếu</option>
                        <option value="news_sync"         {{ request('type') === 'news_sync'         ? 'selected' : '' }}>Sync news</option>
                        <option value="stock_price_sync"  {{ request('type') === 'stock_price_sync'  ? 'selected' : '' }}>Sync giá cổ phiếu</option>
                        <option value="admin_action"      {{ request('type') === 'admin_action'      ? 'selected' : '' }}>Hành động admin</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tìm kiếm</label>
                    <input type="text" class="form-control" name="search" value="{{ request('search') }}" placeholder="Tên user, mô tả...">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Ngày</label>
                    <input type="date" class="form-control" name="date" value="{{ request('date') }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Lọc</button>
                </div>
                <div class="col-md-2">
                    @if(request()->hasAny(['type', 'date', 'search']))
                        <a href="{{ route('admin.timeline') }}" class="btn btn-outline-secondary w-100">Reset</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Timeline list --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Hoạt động hệ thống ({{ $items->total() }} bản ghi)</h3>
        </div>
        <div class="card-body">
            @if($items->count() > 0)
                <div class="timeline">
                    @foreach($items as $log)
                    @php $cfg = \App\Models\ActivityLog::iconConfig()[$log->event_type] ?? ['icon' => 'activity', 'color' => 'gray']; @endphp
                    <div class="timeline-item">
                        <div class="timeline-badge bg-{{ $cfg['color'] }}">
                            @include('backend.timeline._icon', ['icon' => $cfg['icon']])
                        </div>
                        <div class="timeline-content">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong>{{ $log->user_name }}</strong>
                                    <span class="badge bg-{{ $cfg['color'] }}-lt ms-1">{{ $log->event_type }}</span>
                                </div>
                                <small class="text-muted text-nowrap ms-3">{{ $log->created_at->diffForHumans() }}</small>
                            </div>
                            <p class="mt-1 mb-0 text-muted">{{ $log->description }}</p>
                            @if($log->properties)
                                <small class="text-muted">
                                    @foreach($log->properties as $k => $v)
                                        <span class="me-2"><strong>{{ $k }}:</strong> {{ is_array($v) ? json_encode($v) : $v }}</span>
                                    @endforeach
                                </small>
                            @endif
                            <div class="mt-1">
                                <small class="text-muted">{{ $log->created_at->format('d/m/Y H:i:s') }}
                                    @if($log->ip_address) &bull; IP: {{ $log->ip_address }} @endif
                                </small>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="mt-4">
                    {{ $items->links('vendor.pagination.bootstrap-5') }}
                </div>
            @else
                <div class="empty">
                    <div class="empty-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-lg" width="48" height="48" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
                    </div>
                    <p class="empty-title">Chưa có hoạt động nào</p>
                    <p class="empty-subtitle text-muted">Hoạt động sẽ xuất hiện khi users đăng ký, tạo portfolio, hoặc admin thực hiện thao tác.</p>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('styles')
<style>
.timeline { position: relative; padding-left: 36px; }
.timeline::before { content: ''; position: absolute; left: 14px; top: 0; bottom: 0; width: 2px; background: #e9ecef; }
.timeline-item { position: relative; margin-bottom: 20px; }
.timeline-badge { position: absolute; left: -29px; top: 4px; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid #fff; }
.timeline-content { background: #f8f9fa; border-radius: 6px; padding: 12px 16px; }
</style>
@endpush

@section('breadcrumbs')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
    </li>
    <li class="breadcrumb-item active">Timeline</li>
@endsection

@section('page_actions')
    <a href="{{ route('admin.timeline.stats') }}" class="btn btn-outline-primary">
        <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
            <line x1="4" y1="19" x2="20" y2="19"/>
            <polyline points="4,15 8,9 12,11 16,6 20,10"/>
        </svg>
        Thống kê
    </a>
@endsection

@section('content')
    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.timeline') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Loại hoạt động</label>
                    <select class="form-select" name="type">
                        <option value="">Tất cả</option>
                        <option value="user_register" {{ request('type') === 'user_register' ? 'selected' : '' }}>
                            Đăng ký user
                        </option>
                        <option value="portfolio_created" {{ request('type') === 'portfolio_created' ? 'selected' : '' }}>
                            Tạo portfolio
                        </option>
                        <option value="stock_added" {{ request('type') === 'stock_added' ? 'selected' : '' }}>
                            Thêm stock
                        </option>
                        <option value="admin_action" {{ request('type') === 'admin_action' ? 'selected' : '' }}>
                            Hành động admin
                        </option>
                        <option value="system_backup" {{ request('type') === 'system_backup' ? 'selected' : '' }}>
                            Backup hệ thống
                        </option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ngày</label>
                    <input type="date" class="form-control" name="date" value="{{ request('date') }}">
                </div>
                <div class="col-md-2 align-self-end">
                    <button type="submit" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                            <circle cx="11" cy="11" r="8"/>
                            <path d="M21 21l-4.35-4.35"/>
                        </svg>
                        Lọc
                    </button>
                    @if(request()->hasAny(['type', 'date']))
                        <a href="{{ route('admin.timeline') }}" class="btn btn-outline-secondary ms-2">Reset</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Timeline -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Hoạt động gần đây</h3>
        </div>
        <div class="card-body">
            @if($items->count() > 0)
                <div class="timeline">
                    @foreach($items as $item)
                    <div class="timeline-item">
                        <div class="timeline-badge bg-{{ $item['color'] }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon text-white" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                @if($item['icon'] === 'user-plus')
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <circle cx="12" cy="7" r="4"/>
                                    <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"/>
                                    <line x1="19" y1="7" x2="19" y2="14"/>
                                    <line x1="22" y1="10.5" x2="16" y2="10.5"/>
                                @elseif($item['icon'] === 'briefcase')
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <rect x="3" y="4" width="18" height="12" rx="1"/>
                                    <line x1="7" y1="20" x2="17" y2="20"/>
                                    <line x1="9" y1="16" x2="15" y2="16"/>
                                @elseif($item['icon'] === 'trending-up')
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <polyline points="3,17 9,11 13,15 21,7"/>
                                    <polyline points="14,7 21,7 21,14"/>
                                @elseif($item['icon'] === 'refresh')
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4"/>
                                    <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/>
                                @elseif($item['icon'] === 'database')
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <ellipse cx="12" cy="6" rx="8" ry="3"/>
                                    <path d="M4 6v6a8 3 0 0 0 16 0v-6"/>
                                    <path d="M4 12v6a8 3 0 0 0 16 0v-6"/>
                                @endif
                            </svg>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <h4 class="timeline-title">{{ $item['user'] }}</h4>
                                <p class="timeline-subtitle text-muted">{{ $item['action'] }}</p>
                            </div>
                            <div class="timeline-body">
                                <p>{{ $item['description'] }}</p>
                            </div>
                            <div class="timeline-time">
                                <small class="text-muted">{{ $item['created_at']->diffForHumans() }}</small>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                <!-- Load more button -->
                <div class="d-flex justify-content-center mt-4">
                    <button class="btn btn-outline-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                            <path d="M12 5l0 14"/>
                            <path d="M18 13l-6 6"/>
                            <path d="M6 13l6 6"/>
                        </svg>
                        Tải thêm
                    </button>
                </div>
            @else
                <div class="empty">
                    <div class="empty-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="48" height="48" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                            <circle cx="12" cy="12" r="2"/>
                            <path d="M12 1v6m0 6v6"/>
                        </svg>
                    </div>
                    <p class="empty-title">Không có hoạt động nào</p>
                    <p class="empty-subtitle text-muted">Thử thay đổi bộ lọc để xem hoạt động khác.</p>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('styles')
<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline:before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
}

.timeline-badge {
    position: absolute;
    left: -23px;
    top: 0;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 3px solid #fff;
    box-shadow: 0 0 0 3px #e9ecef;
}

.timeline-content {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin-left: 15px;
}

.timeline-title {
    font-size: 1rem;
    margin-bottom: 5px;
}

.timeline-subtitle {
    font-size: 0.875rem;
    margin-bottom: 10px;
}

.timeline-body {
    margin-bottom: 10px;
}

.timeline-time {
    text-align: right;
}
</style>
@endpush
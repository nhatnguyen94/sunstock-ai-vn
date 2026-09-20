@extends('layouts.admin')

@section('title', 'Quản lý Portfolio')
@section('page_pretitle', 'Quản lý')
@section('page_title', 'Quản lý Portfolio')

@section('breadcrumbs')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
    </li>
    <li class="breadcrumb-item active">Portfolio</li>
@endsection

@section('page_actions')
    <div class="btn-list">
        <a href="{{ route('admin.portfolios.stats') }}" class="btn btn-outline-primary">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                <line x1="4" y1="19" x2="20" y2="19"/>
                <polyline points="4,15 8,9 12,11 16,6 20,10"/>
            </svg>
            Thống kê
        </a>
    </div>
@endsection

@section('content')
    <!-- Portfolio Statistics (real numbers, from the controller) -->
    @php $sUp = $summary['profit_percent'] >= 0; @endphp
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card"><div class="card-body">
                <div class="subheader">Tổng Portfolio</div>
                <div class="h1 mb-1">{{ $summary['total'] }}</div>
                <div class="text-muted">{{ $summary['active'] }} đang hoạt động · {{ $summary['total'] - $summary['active'] }} tạm dừng</div>
            </div></div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card"><div class="card-body">
                <div class="subheader">Tổng giá trị</div>
                <div class="h1 mb-1 text-success">{{ number_format($summary['value'], 0, ',', '.') }} ₫</div>
                <div class="text-muted">Vốn đầu tư {{ number_format($summary['invested'], 0, ',', '.') }} ₫</div>
            </div></div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card"><div class="card-body">
                <div class="subheader">Người dùng có portfolio</div>
                <div class="h1 mb-1">{{ $summary['owners'] }}</div>
                <div class="text-muted">Chủ sở hữu khác nhau</div>
            </div></div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card"><div class="card-body">
                <div class="subheader">Lãi/Lỗ toàn hệ thống</div>
                <div class="h1 mb-1 {{ $sUp ? 'text-success' : 'text-danger' }}">{{ $sUp ? '+' : '' }}{{ number_format($summary['profit_percent'], 2, ',', '.') }}%</div>
                <div class="text-muted">Giá trị so với vốn</div>
            </div></div>
        </div>
    </div>

    <!-- Portfolio List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Danh sách Portfolio</h3>
                    <form method="GET" action="{{ route('admin.portfolios.index') }}" class="card-actions d-flex gap-2">
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Tìm theo tên portfolio, tên hoặc email chủ sở hữu…">
                        <select name="status" class="form-select w-auto" onchange="this.form.submit()">
                            <option value="">Tất cả</option>
                            <option value="active" @selected(request('status') === 'active')>Hoạt động</option>
                            <option value="inactive" @selected(request('status') === 'inactive')>Tạm dừng</option>
                        </select>
                        <button class="btn btn-primary" type="submit">Tìm</button>
                        @if(request()->hasAny(['search', 'status']))<a href="{{ route('admin.portfolios.index') }}" class="btn btn-ghost-secondary">Xóa lọc</a>@endif
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tên Portfolio</th>
                                <th>Chủ sở hữu</th>
                                <th>Số mã CP</th>
                                <th>Giá trị</th>
                                <th>Lãi/Lỗ</th>
                                <th>Trạng thái</th>
                                <th>Ngày tạo</th>
                                <th class="w-1">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($portfolios ?? [] as $portfolio)
                            <tr>
                                <td>
                                    <span class="text-muted">{{ $portfolio->id }}</span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div>
                                            <div class="font-weight-medium">{{ $portfolio->name }}</div>
                                            @if($portfolio->description)
                                                <div class="text-muted small">{{ Str::limit($portfolio->description, 50) }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar avatar-sm me-2 bg-primary-lt">
                                            {{ strtoupper(substr($portfolio->user->name ?? 'U', 0, 2)) }}
                                        </div>
                                        <div>
                                            <div class="font-weight-medium">{{ $portfolio->user->name ?? 'N/A' }}</div>
                                            <div class="text-muted small">{{ $portfolio->user->email ?? 'N/A' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-blue-lt">{{ $portfolio->items_count }} mã</span>
                                </td>
                                <td>
                                    <div class="text-success font-weight-bold">
                                        {{ number_format($portfolio->current_value, 0, ',', '.') }} ₫
                                    </div>
                                </td>
                                <td>
                                    @php
                                        $profitLoss = $portfolio->total_profit_loss;
                                        $profitPercent = $portfolio->total_profit_loss_percent;
                                    @endphp
                                    <div class="{{ $profitLoss >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ $profitLoss >= 0 ? '+' : '' }}{{ number_format($profitLoss, 0, ',', '.') }} ₫
                                        <small>({{ $profitPercent >= 0 ? '+' : '' }}{{ number_format($profitPercent, 2) }}%)</small>
                                    </div>
                                </td>
                                <td>
                                    @if($portfolio->is_active ?? true)
                                        <span class="badge bg-success-lt">Hoạt động</span>
                                    @else
                                        <span class="badge bg-warning-lt">Tạm dừng</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="text-muted">{{ $portfolio->created_at->format('d/m/Y') ?? 'N/A' }}</div>
                                    <div class="text-muted small">{{ $portfolio->created_at->format('H:i') ?? '' }}</div>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('admin.portfolios.show', $portfolio->id) }}" class="btn btn-sm btn-outline-primary" title="Xem chi tiết">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                <circle cx="12" cy="12" r="2"/>
                                                <path d="M22 12c-2.667 4-6 6-10 6s-7.333 -2-10 -6c2.667 -4 6 -6 10 -6s7.333 2 10 6"/>
                                            </svg>
                                        </a>
                                        
                                        <form action="{{ route('admin.portfolios.toggle-status', $portfolio->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-outline-{{ $portfolio->is_active ?? true ? 'warning' : 'success' }}" title="{{ $portfolio->is_active ?? true ? 'Tạm dừng' : 'Kích hoạt' }}">
                                                @if($portfolio->is_active ?? true)
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                                        <rect x="6" y="4" width="4" height="16"/>
                                                        <rect x="14" y="4" width="4" height="16"/>
                                                    </svg>
                                                @else
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                                        <polygon points="5,3 19,12 5,21 5,3"/>
                                                    </svg>
                                                @endif
                                            </button>
                                        </form>
                                        
                                        <form action="{{ route('admin.portfolios.destroy', $portfolio->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa portfolio này?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                                    <line x1="4" y1="7" x2="20" y2="7"/>
                                                    <line x1="10" y1="11" x2="10" y2="17"/>
                                                    <line x1="14" y1="11" x2="14" y2="17"/>
                                                    <path d="m5 7 1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"/>
                                                    <path d="m9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"/>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <div class="empty">
                                        <div class="empty-img">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-lg" width="48" height="48" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                <rect x="3" y="4" width="18" height="12" rx="1"/>
                                                <line x1="7" y1="8" x2="10" y2="8"/>
                                                <line x1="7" y1="12" x2="17" y2="12"/>
                                                <line x1="7" y1="16" x2="14" y2="16"/>
                                                <line x1="17" y1="8" x2="17" y2="8.01"/>
                                            </svg>
                                        </div>
                                        <p class="empty-title">Chưa có portfolio nào</p>
                                        <p class="empty-subtitle text-muted">
                                            Các người dùng chưa tạo portfolio nào
                                        </p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($portfolios->hasPages())<div class="card-footer d-flex align-items-center">{{ $portfolios->links() }}</div>@endif
            </div>
        </div>
    </div>
@endsection

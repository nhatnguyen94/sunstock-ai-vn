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
    <!-- Portfolio Statistics -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="subheader">Tổng Portfolio</div>
                    </div>
                    <div class="h1 mb-3">{{ $totalPortfolios ?? 0 }}</div>
                    <div class="d-flex mb-2">
                        <div class="flex-1">
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-primary" style="width: 75%" role="progressbar"></div>
                            </div>
                        </div>
                        <div class="text-muted ms-2">75%</div>
                    </div>
                    <div class="text-muted">Portfolio hoạt động</div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="subheader">Tổng giá trị</div>
                    </div>
                    <div class="h1 mb-3 text-success">{{ number_format($totalValue ?? 0, 0, ',', '.') }} VND</div>
                    <div class="d-flex mb-2">
                        <div class="flex-1">
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-success" style="width: 60%" role="progressbar"></div>
                            </div>
                        </div>
                        <div class="text-muted ms-2">+12%</div>
                    </div>
                    <div class="text-muted">Tăng trong tháng</div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="subheader">Người dùng hoạt động</div>
                    </div>
                    <div class="h1 mb-3">{{ $activeUsers ?? 0 }}</div>
                    <div class="d-flex mb-2">
                        <div class="flex-1">
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-yellow" style="width: 85%" role="progressbar"></div>
                            </div>
                        </div>
                        <div class="text-muted ms-2">85%</div>
                    </div>
                    <div class="text-muted">Có portfolio</div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="subheader">Lợi nhuận TB</div>
                    </div>
                    <div class="h1 mb-3 text-green">+8.5%</div>
                    <div class="d-flex mb-2">
                        <div class="flex-1">
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-green" style="width: 40%" role="progressbar"></div>
                            </div>
                        </div>
                        <div class="text-muted ms-2">+2.1%</div>
                    </div>
                    <div class="text-muted">So với tháng trước</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Portfolio List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Danh sách Portfolio</h3>
                    <div class="card-actions">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Tìm kiếm portfolio...">
                            <button class="btn btn-outline-primary" type="button">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="10" cy="10" r="7"/>
                                    <path d="m21 21-6-6"/>
                                </svg>
                            </button>
                        </div>
                    </div>
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
                                    <span class="badge bg-blue-lt">{{ $portfolio->items_count ?? 0 }} mã</span>
                                </td>
                                <td>
                                    <div class="text-success font-weight-bold">
                                        {{ number_format($portfolio->total_value ?? 0, 0, ',', '.') }} VND
                                    </div>
                                </td>
                                <td>
                                    @php
                                        $profitLoss = $portfolio->profit_loss ?? 0;
                                        $profitPercent = $portfolio->profit_percent ?? 0;
                                    @endphp
                                    <div class="{{ $profitLoss >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ $profitLoss >= 0 ? '+' : '' }}{{ number_format($profitLoss, 0, ',', '.') }} VND
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
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    // Live search functionality
    document.querySelector('input[placeholder*="Tìm kiếm"]').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('tbody tr:not(.empty)');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });
</script>
@endpush
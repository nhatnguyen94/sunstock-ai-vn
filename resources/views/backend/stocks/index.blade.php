@extends('layouts.admin')

@section('title', 'Quản lý Cổ phiếu')
@section('page_pretitle', 'Dữ liệu')
@section('page_title', 'Quản lý Cổ phiếu')

@section('breadcrumbs')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
    </li>
    <li class="breadcrumb-item active">Cổ phiếu</li>
@endsection

@section('page_actions')
    <div class="btn-list">
        <a href="{{ route('admin.stocks.create') }}" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Thêm mã CP
        </a>
        
        <form action="{{ route('admin.stocks.update-prices') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-primary">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4"/>
                    <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/>
                </svg>
                Cập nhật giá
            </button>
        </form>
    </div>
@endsection

@section('content')
    <!-- Search and Filter -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.stocks.index') }}">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Tìm kiếm</label>
                                    <input type="text" name="search" class="form-control" placeholder="Mã CP hoặc tên công ty..." value="{{ request('search') }}">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Sàn giao dịch</label>
                                    <select name="exchange" class="form-select">
                                        <option value="">Tất cả sàn</option>
                                        @foreach($exchanges ?? [] as $exchange)
                                            <option value="{{ $exchange }}" {{ request('exchange') === $exchange ? 'selected' : '' }}>
                                                {{ $exchange }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Trạng thái</label>
                                    <select name="status" class="form-select">
                                        <option value="">Tất cả</option>
                                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Hoạt động</option>
                                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Tạm dừng</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="mb-3">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="btn-group d-block">
                                        <button type="submit" class="btn btn-primary">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="10" cy="10" r="7"/>
                                                <path d="m21 21-6-6"/>
                                            </svg>
                                        </button>
                                        <a href="{{ route('admin.stocks.index') }}" class="btn btn-outline-secondary">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4"/>
                                                <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/>
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Stocks List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Danh sách cổ phiếu</h3>
                    <div class="card-actions">
                        <span class="text-muted">Tổng: {{ $stocks->total() ?? 0 }} mã cổ phiếu</span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Mã CP</th>
                                <th>Tên công ty</th>
                                <th>Sàn GD</th>
                                <th>Ngành</th>
                                <th>Vốn hóa</th>
                                <th>Giá hiện tại</th>
                                <th>Trạng thái</th>
                                <th>Cập nhật</th>
                                <th class="w-1">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($stocks ?? [] as $stock)
                            <tr>
                                <td>
                                    <div class="font-weight-bold text-primary">{{ $stock->symbol }}</div>
                                </td>
                                <td>
                                    <div class="font-weight-medium">{{ $stock->name ?? 'N/A' }}</div>
                                </td>
                                <td>
                                    @if($stock->exchange)
                                        <span class="badge bg-{{ $stock->exchange === 'HSX' ? 'red' : ($stock->exchange === 'HNX' ? 'blue' : 'green') }}-lt">
                                            {{ $stock->exchange }}
                                        </span>
                                    @else
                                        <span class="text-muted">N/A</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-muted">{{ $stock->industry ?? 'N/A' }}</span>
                                </td>
                                <td>
                                    @if($stock->market_cap)
                                        <div class="text-end">{{ number_format($stock->market_cap, 0, ',', '.') }} VND</div>
                                    @else
                                        <span class="text-muted">N/A</span>
                                    @endif
                                </td>
                                <td>
                                    @if($stock->latest_price)
                                        <div class="text-end font-weight-bold">{{ number_format($stock->latest_price, 0, ',', '.') }} VND</div>
                                        @if($stock->price_change)
                                            <div class="text-end small {{ $stock->price_change >= 0 ? 'text-success' : 'text-danger' }}">
                                                {{ $stock->price_change >= 0 ? '+' : '' }}{{ number_format($stock->price_change, 2) }}%
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-muted">N/A</span>
                                    @endif
                                </td>
                                <td>
                                    @if($stock->is_active ?? true)
                                        <span class="badge bg-success-lt">Hoạt động</span>
                                    @else
                                        <span class="badge bg-warning-lt">Tạm dừng</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="text-muted">{{ $stock->updated_at->format('d/m/Y') }}</div>
                                    <div class="text-muted small">{{ $stock->updated_at->format('H:i') }}</div>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('admin.stocks.show', $stock) }}" class="btn btn-sm btn-outline-primary" title="Xem chi tiết">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                <circle cx="12" cy="12" r="2"/>
                                                <path d="M22 12c-2.667 4-6 6-10 6s-7.333 -2-10 -6c2.667 -4 6 -6 10 -6s7.333 2 10 6"/>
                                            </svg>
                                        </a>
                                        
                                        <a href="{{ route('admin.stocks.edit', $stock) }}" class="btn btn-sm btn-outline-warning" title="Chỉnh sửa">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"/>
                                                <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z"/>
                                                <path d="M16 5l3 3"/>
                                            </svg>
                                        </a>
                                        
                                        <form action="{{ route('admin.stocks.destroy', $stock) }}" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa mã {{ $stock->symbol }}?')">
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
                                                <line x1="4" y1="19" x2="20" y2="19"/>
                                                <polyline points="4,15 8,9 12,11 16,6 20,10"/>
                                            </svg>
                                        </div>
                                        <p class="empty-title">Chưa có cổ phiếu nào</p>
                                        <p class="empty-subtitle text-muted">
                                            Nhấn nút "Thêm mã CP" để thêm cổ phiếu mới
                                        </p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if(isset($stocks) && method_exists($stocks, 'links'))
                <div class="card-footer">
                    {{ $stocks->appends(request()->query())->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    // Auto-refresh every 5 minutes for stock prices
    setInterval(function() {
        console.log('Auto refresh check...');
    }, 300000); // 5 minutes
</script>
@endpush
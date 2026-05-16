@extends('layouts.admin')

@section('title', 'Chi tiết Portfolio')
@section('page_pretitle', 'Portfolio')
@section('page_title', 'Chi tiết Portfolio #{{ $portfolio->id ?? 'N/A' }}')

@section('breadcrumbs')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
    </li>
    <li class="breadcrumb-item">
        <a href="{{ route('admin.portfolios.index') }}">Portfolio</a>
    </li>
    <li class="breadcrumb-item active">Chi tiết</li>
@endsection

@section('page_actions')
    <div class="btn-list">
        <a href="{{ route('admin.portfolios.index') }}" class="btn btn-outline-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                <line x1="19" y1="12" x2="5" y2="12"/>
                <polyline points="12,19 5,12 12,5"/>
            </svg>
            Quay lại
        </a>
        
        <form action="{{ route('admin.portfolios.toggle-status', $portfolio->id ?? 0) }}" method="POST" class="d-inline">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn btn-outline-{{ $portfolio->is_active ?? true ? 'warning' : 'success' }}">
                @if($portfolio->is_active ?? true)
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="6" y="4" width="4" height="16"/>
                        <rect x="14" y="4" width="4" height="16"/>
                    </svg>
                    Tạm dừng
                @else
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="5,3 19,12 5,21 5,3"/>
                    </svg>
                    Kích hoạt
                @endif
            </button>
        </form>
    </div>
@endsection

@section('content')
    <!-- Portfolio Overview -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Thông tin Portfolio</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Tên Portfolio</label>
                                <div class="form-control-plaintext">{{ $portfolio->name ?? 'N/A' }}</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Mô tả</label>
                                <div class="form-control-plaintext">{{ $portfolio->description ?? 'Không có mô tả' }}</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Chủ sở hữu</label>
                                <div class="form-control-plaintext">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar avatar-sm me-2 bg-primary-lt">
                                            {{ strtoupper(substr($portfolio->user->name ?? 'U', 0, 2)) }}
                                        </div>
                                        <div>
                                            <div class="font-weight-medium">{{ $portfolio->user->name ?? 'N/A' }}</div>
                                            <div class="text-muted small">{{ $portfolio->user->email ?? 'N/A' }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Trạng thái</label>
                                <div class="form-control-plaintext">
                                    @if($portfolio->is_active ?? true)
                                        <span class="badge bg-success-lt fs-6">Hoạt động</span>
                                    @else
                                        <span class="badge bg-warning-lt fs-6">Tạm dừng</span>
                                    @endif
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Ngày tạo</label>
                                <div class="form-control-plaintext">{{ $portfolio->created_at->format('d/m/Y H:i') ?? 'N/A' }}</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Cập nhật cuối</label>
                                <div class="form-control-plaintext">{{ $portfolio->updated_at->format('d/m/Y H:i') ?? 'N/A' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Portfolio Statistics -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="subheader">Tổng giá trị</div>
                    </div>
                    <div class="h1 mb-3 text-primary">{{ number_format($portfolio->total_value ?? 0, 0, ',', '.') }} VND</div>
                    <div class="text-muted">Giá trị hiện tại</div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="subheader">Vốn đầu tư</div>
                    </div>
                    <div class="h1 mb-3">{{ number_format($portfolio->total_investment ?? 0, 0, ',', '.') }} VND</div>
                    <div class="text-muted">Tổng số tiền đã đầu tư</div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="subheader">Lãi/Lỗ</div>
                    </div>
                    @php
                        $profitLoss = $portfolio->profit_loss ?? 0;
                        $profitPercent = $portfolio->profit_percent ?? 0;
                    @endphp
                    <div class="h1 mb-3 {{ $profitLoss >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $profitLoss >= 0 ? '+' : '' }}{{ number_format($profitLoss, 0, ',', '.') }} VND
                    </div>
                    <div class="text-muted {{ $profitPercent >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $profitPercent >= 0 ? '+' : '' }}{{ number_format($profitPercent, 2) }}%
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="subheader">Số mã cổ phiếu</div>
                    </div>
                    <div class="h1 mb-3 text-info">{{ $portfolio->items_count ?? 0 }}</div>
                    <div class="text-muted">Mã cổ phiếu đang sở hữu</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Portfolio Holdings -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Danh mục đầu tư</h3>
                </div>

                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Mã CP</th>
                                <th>Tên công ty</th>
                                <th>Số lượng</th>
                                <th>Giá mua TB</th>
                                <th>Giá hiện tại</th>
                                <th>Giá trị</th>
                                <th>Lãi/Lỗ</th>
                                <th>Tỷ trọng</th>
                                <th>Ngày mua</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($portfolio->items ?? [] as $item)
                            <tr>
                                <td>
                                    <div class="font-weight-bold text-primary">{{ $item->stock_symbol ?? 'N/A' }}</div>
                                </td>
                                <td>
                                    <div class="font-weight-medium">{{ $item->stock->name ?? 'N/A' }}</div>
                                    <div class="text-muted small">{{ $item->stock->exchange ?? 'N/A' }}</div>
                                </td>
                                <td>
                                    <span class="badge bg-blue-lt fs-6">{{ number_format($item->quantity ?? 0, 0, ',', '.') }}</span>
                                </td>
                                <td>
                                    <div class="text-end">{{ number_format($item->buy_price ?? 0, 0, ',', '.') }} VND</div>
                                </td>
                                <td>
                                    <div class="text-end">{{ number_format($item->current_price ?? 0, 0, ',', '.') }} VND</div>
                                </td>
                                <td>
                                    <div class="text-end font-weight-bold">{{ number_format($item->total_value ?? 0, 0, ',', '.') }} VND</div>
                                </td>
                                <td>
                                    @php
                                        $itemProfitLoss = $item->profit_loss ?? 0;
                                        $itemProfitPercent = $item->profit_percent ?? 0;
                                    @endphp
                                    <div class="text-end {{ $itemProfitLoss >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ $itemProfitLoss >= 0 ? '+' : '' }}{{ number_format($itemProfitLoss, 0, ',', '.') }} VND
                                        <div class="small">({{ $itemProfitPercent >= 0 ? '+' : '' }}{{ number_format($itemProfitPercent, 2) }}%)</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-end">
                                        {{ number_format($item->allocation_percent ?? 0, 2) }}%
                                        <div class="progress progress-xs mt-1">
                                            <div class="progress-bar" style="width: {{ $item->allocation_percent ?? 0 }}%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-muted">{{ $item->created_at->format('d/m/Y') ?? 'N/A' }}</div>
                                    <div class="text-muted small">{{ $item->created_at->format('H:i') ?? '' }}</div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <div class="empty">
                                        <div class="empty-img">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-lg" width="48" height="48" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                <circle cx="12" cy="12" r="8"/>
                                                <path d="m9 12l2 2l4 -4"/>
                                            </svg>
                                        </div>
                                        <p class="empty-title">Portfolio trống</p>
                                        <p class="empty-subtitle text-muted">
                                            Chưa có cổ phiếu nào trong portfolio này
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
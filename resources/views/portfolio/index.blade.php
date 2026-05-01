@extends('layouts.app')

@section('content')
<div class="container py-5">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 style="color:var(--text-primary); font-weight:600;">
                <i class="bi bi-briefcase" style="color:var(--primary-blue); margin-right:12px;"></i>
                Danh mục đầu tư
            </h2>
            <p style="color:var(--text-secondary); margin:0;">Quản lý và theo dõi các danh mục đầu tư của bạn</p>
        </div>
        <a href="{{ route('portfolio.create') }}" class="btn btn-primary-custom">
            <i class="bi bi-plus-circle"></i>
            Tạo danh mục mới
        </a>
    </div>

    <!-- Total Stats Cards -->
    <div class="row mb-5">
        <div class="col-md-3">
            <div class="custom-card text-center" style="padding:2rem;">
                <div class="stat-icon" style="background:linear-gradient(135deg, var(--primary-blue), var(--secondary-blue)); color:white; width:60px; height:60px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1rem;">
                    <i class="bi bi-briefcase" style="font-size:1.5rem;"></i>
                </div>
                <h3 style="color:var(--text-primary); margin-bottom:0.5rem;">{{ $totalStats['total_portfolios'] }}</h3>
                <p style="color:var(--text-secondary); margin:0;">Danh mục</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="custom-card text-center" style="padding:2rem;">
                <div class="stat-icon" style="background:linear-gradient(135deg, var(--success-green), #16a34a); color:white; width:60px; height:60px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1rem;">
                    <i class="bi bi-cash-coin" style="font-size:1.5rem;"></i>
                </div>
                <h3 style="color:var(--text-primary); margin-bottom:0.5rem;">{{ number_format($totalStats['total_invested'], 0, ',', '.') }}₫</h3>
                <p style="color:var(--text-secondary); margin:0;">Tổng vốn</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="custom-card text-center" style="padding:2rem;">
                <div class="stat-icon" style="background:linear-gradient(135deg, var(--warning-orange), #f97316); color:white; width:60px; height:60px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1rem;">
                    <i class="bi bi-graph-up" style="font-size:1.5rem;"></i>
                </div>
                <h3 style="color:var(--text-primary); margin-bottom:0.5rem;">{{ number_format($totalStats['current_value'], 0, ',', '.') }}₫</h3>
                <p style="color:var(--text-secondary); margin:0;">Giá trị hiện tại</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="custom-card text-center" style="padding:2rem;">
                <div class="stat-icon" style="background:linear-gradient(135deg, {{ $totalStats['is_positive'] ? 'var(--success-green), #16a34a' : 'var(--danger-red), #dc2626' }}); color:white; width:60px; height:60px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1rem;">
                    <i class="bi bi-{{ $totalStats['is_positive'] ? 'arrow-up' : 'arrow-down' }}" style="font-size:1.5rem;"></i>
                </div>
                <h3 style="color:{{ $totalStats['is_positive'] ? 'var(--success-green)' : 'var(--danger-red)' }}; margin-bottom:0.5rem;">
                    {{ $totalStats['is_positive'] ? '+' : '' }}{{ number_format($totalStats['profit_loss'], 0, ',', '.') }}₫
                </h3>
                <p style="color:var(--text-secondary); margin:0;">
                    Lãi/Lỗ ({{ number_format($totalStats['profit_loss_percent'], 2) }}%)
                </p>
            </div>
        </div>
    </div>

    <!-- Portfolio List -->
    @if($portfolios->count() > 0)
        <div class="row">
            @foreach($portfolios as $portfolio)
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="custom-card" style="height:100%;">
                        <div class="card-header-custom" style="padding:1.5rem 1.5rem 1rem;">
                            <div class="d-flex justify-content-between align-items-start">
                                <h5 style="color:var(--text-primary); font-weight:600; margin:0;">
                                    {{ $portfolio->name }}
                                </h5>
                                <div class="dropdown">
                                    <button class="btn btn-sm" style="color:var(--text-secondary);" data-toggle="dropdown">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <a class="dropdown-item" href="{{ route('portfolio.show', $portfolio->id) }}">
                                            <i class="bi bi-eye"></i> Xem chi tiết
                                        </a>
                                        <a class="dropdown-item" href="{{ route('portfolio.edit', $portfolio->id) }}">
                                            <i class="bi bi-pencil"></i> Chỉnh sửa
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <form method="POST" action="{{ route('portfolio.destroy', $portfolio->id) }}" 
                                              onsubmit="return confirm('Bạn có chắc chắn muốn xóa danh mục này?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="dropdown-item text-danger" type="submit">
                                                <i class="bi bi-trash"></i> Xóa
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            @if($portfolio->description)
                                <p style="color:var(--text-secondary); margin:0.5rem 0 0; font-size:0.9rem;">
                                    {{ Str::limit($portfolio->description, 60) }}
                                </p>
                            @endif
                        </div>

                        <div class="card-body-custom" style="padding:1rem 1.5rem 1.5rem;">
                            <!-- Portfolio Stats -->
                            <div class="portfolio-stats mb-3">
                                <div class="row">
                                    <div class="col-6">
                                        <div class="text-center">
                                            <p style="color:var(--text-secondary); font-size:0.85rem; margin:0;">Tổng vốn</p>
                                            <h6 style="color:var(--text-primary); margin:0;">{{ number_format($portfolio->total_invested, 0, ',', '.') }}₫</h6>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center">
                                            <p style="color:var(--text-secondary); font-size:0.85rem; margin:0;">Giá trị hiện tại</p>
                                            <h6 style="color:var(--text-primary); margin:0;">{{ number_format($portfolio->current_value, 0, ',', '.') }}₫</h6>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Profit/Loss -->
                            @php
                                $profitLoss = $portfolio->current_value - $portfolio->total_invested;
                                $profitLossPercent = $portfolio->total_invested > 0 ? ($profitLoss / $portfolio->total_invested) * 100 : 0;
                                $isPositive = $profitLoss >= 0;
                            @endphp
                            
                            <div class="profit-loss text-center mb-3" style="padding:1rem; background:{{ $isPositive ? 'linear-gradient(135deg, #f0fdf4, #dcfce7)' : 'linear-gradient(135deg, #fef2f2, #fee2e2)' }}; border-radius:12px;">
                                <p style="color:var(--text-secondary); font-size:0.85rem; margin:0 0 0.25rem;">Lãi/Lỗ</p>
                                <h5 style="color:{{ $isPositive ? 'var(--success-green)' : 'var(--danger-red)' }}; margin:0;">
                                    {{ $isPositive ? '+' : '' }}{{ number_format($profitLoss, 0, ',', '.') }}₫
                                    <span style="font-size:0.8rem;">({{ number_format($profitLossPercent, 2) }}%)</span>
                                </h5>
                            </div>

                            <!-- Action Buttons -->
                            <div class="d-flex gap-2">
                                <a href="{{ route('portfolio.show', $portfolio->id) }}" class="btn btn-outline-primary flex-fill" style="font-size:0.9rem;">
                                    <i class="bi bi-eye"></i>
                                    Chi tiết
                                </a>
                                <a href="{{ route('portfolio.add-stock', $portfolio->id) }}" class="btn btn-primary-custom flex-fill" style="font-size:0.9rem;">
                                    <i class="bi bi-plus"></i>
                                    Thêm CP
                                </a>
                            </div>

                            <!-- Portfolio Items Count -->
                            <div class="text-center mt-2">
                                <small style="color:var(--text-secondary);">
                                    <i class="bi bi-list-ul"></i>
                                    {{ $portfolio->items->count() }} cổ phiếu
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <!-- Empty State -->
        <div class="text-center py-5">
            <div class="empty-state" style="max-width:400px; margin:0 auto;">
                <i class="bi bi-briefcase" style="font-size:4rem; color:var(--text-secondary); margin-bottom:1.5rem;"></i>
                <h4 style="color:var(--text-primary); margin-bottom:1rem;">Chưa có danh mục đầu tư</h4>
                <p style="color:var(--text-secondary); margin-bottom:2rem;">
                    Tạo danh mục đầu tư đầu tiên để bắt đầu theo dõi và quản lý các cổ phiếu của bạn.
                </p>
                <a href="{{ route('portfolio.create') }}" class="btn btn-primary-custom">
                    <i class="bi bi-plus-circle"></i>
                    Tạo danh mục đầu tiên
                </a>
            </div>
        </div>
    @endif
</div>

<style>
    .custom-card {
        background: white;
        border-radius: 16px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
    }

    .custom-card:hover {
        box-shadow: var(--shadow-md);
        transform: translateY(-2px);
    }

    .card-header-custom {
        border-bottom: 1px solid var(--border-color);
    }

    .portfolio-stats {
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 1rem;
        background: var(--bg-light);
    }
</style>
@endsection
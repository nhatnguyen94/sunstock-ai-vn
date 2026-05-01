@extends('layouts.app')

@section('content')
<div class="container py-5">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb" style="background:none; padding:0;">
                    <li class="breadcrumb-item">
                        <a href="{{ route('portfolio.index') }}" style="color:var(--primary-blue);">Danh mục đầu tư</a>
                    </li>
                    <li class="breadcrumb-item active" style="color:var(--text-secondary);">{{ $portfolio->name }}</li>
                </ol>
            </nav>
            <h2 style="color:var(--text-primary); font-weight:600; margin:0;">
                <i class="bi bi-briefcase" style="color:var(--primary-blue); margin-right:12px;"></i>
                {{ $portfolio->name }}
                @if(!$portfolio->is_active)
                    <span class="badge badge-secondary ml-2">Tạm dừng</span>
                @endif
            </h2>
            @if($portfolio->description)
                <p style="color:var(--text-secondary); margin:0.5rem 0 0;">{{ $portfolio->description }}</p>
            @endif
        </div>
        
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary" onclick="updatePrices({{ $portfolio->id }})">
                <i class="bi bi-arrow-clockwise" id="refresh-icon"></i>
                Cập nhật giá
            </button>
            <a href="{{ route('portfolio.add-stock', $portfolio->id) }}" class="btn btn-primary-custom">
                <i class="bi bi-plus-circle"></i>
                Thêm cổ phiếu
            </a>
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" data-toggle="dropdown">
                    <i class="bi bi-gear"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="{{ route('portfolio.edit', $portfolio->id) }}">
                        <i class="bi bi-pencil"></i> Chỉnh sửa danh mục
                    </a>
                    <button class="dropdown-item" onclick="getRebalanceSuggestions({{ $portfolio->id }})">
                        <i class="bi bi-pie-chart"></i> Gợi ý rebalance
                    </button>
                    <div class="dropdown-divider"></div>
                    <form method="POST" action="{{ route('portfolio.destroy', $portfolio->id) }}" 
                          onsubmit="return confirm('Bạn có chắc chắn muốn xóa danh mục này?')">
                        @csrf
                        @method('DELETE')
                        <button class="dropdown-item text-danger" type="submit">
                            <i class="bi bi-trash"></i> Xóa danh mục
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Portfolio Statistics -->
    <div class="row mb-5">
        <div class="col-md-3">
            <div class="custom-card text-center" style="padding:2rem;">
                <div class="stat-icon" style="background:linear-gradient(135deg, var(--success-green), #16a34a); color:white; width:60px; height:60px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1rem;">
                    <i class="bi bi-cash-coin" style="font-size:1.5rem;"></i>
                </div>
                <h3 style="color:var(--text-primary); margin-bottom:0.5rem;">{{ number_format($stats['total_invested'], 0, ',', '.') }}₫</h3>
                <p style="color:var(--text-secondary); margin:0;">Tổng vốn đầu tư</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="custom-card text-center" style="padding:2rem;">
                <div class="stat-icon" style="background:linear-gradient(135deg, var(--warning-orange), #f97316); color:white; width:60px; height:60px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1rem;">
                    <i class="bi bi-graph-up" style="font-size:1.5rem;"></i>
                </div>
                <h3 style="color:var(--text-primary); margin-bottom:0.5rem;">{{ number_format($stats['current_value'], 0, ',', '.') }}₫</h3>
                <p style="color:var(--text-secondary); margin:0;">Giá trị hiện tại</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="custom-card text-center" style="padding:2rem;">
                <div class="stat-icon" style="background:linear-gradient(135deg, {{ $stats['is_positive'] ? 'var(--success-green), #16a34a' : 'var(--danger-red), #dc2626' }}); color:white; width:60px; height:60px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1rem;">
                    <i class="bi bi-{{ $stats['is_positive'] ? 'arrow-up' : 'arrow-down' }}" style="font-size:1.5rem;"></i>
                </div>
                <h3 style="color:{{ $stats['is_positive'] ? 'var(--success-green)' : 'var(--danger-red)' }}; margin-bottom:0.5rem;">
                    {{ $stats['is_positive'] ? '+' : '' }}{{ number_format($stats['profit_loss'], 0, ',', '.') }}₫
                </h3>
                <p style="color:var(--text-secondary); margin:0;">Lãi/Lỗ</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="custom-card text-center" style="padding:2rem;">
                <div class="stat-icon" style="background:linear-gradient(135deg, {{ $stats['is_positive'] ? 'var(--success-green), #16a34a' : 'var(--danger-red), #dc2626' }}); color:white; width:60px; height:60px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1rem;">
                    <i class="bi bi-percent" style="font-size:1.5rem;"></i>
                </div>
                <h3 style="color:{{ $stats['is_positive'] ? 'var(--success-green)' : 'var(--danger-red)' }}; margin-bottom:0.5rem;">
                    {{ $stats['is_positive'] ? '+' : '' }}{{ number_format($stats['profit_loss_percent'], 2) }}%
                </h3>
                <p style="color:var(--text-secondary); margin:0;">Tỷ suất sinh lời</p>
            </div>
        </div>
    </div>

    <!-- Alerts Section -->
    @if($alerts['targets_reached'] > 0 || $alerts['stop_losses_hit'] > 0)
        <div class="alert-section mb-4">
            <div class="custom-card" style="border-left:4px solid var(--warning-orange);">
                <div class="card-body-custom" style="padding:1.5rem;">
                    <h5 style="color:var(--warning-orange); margin-bottom:1rem;">
                        <i class="bi bi-exclamation-triangle" style="margin-right:8px;"></i>
                        Cảnh báo danh mục
                    </h5>
                    <div class="row">
                        @if($alerts['targets_reached'] > 0)
                            <div class="col-md-6">
                                <div class="alert-item" style="background:linear-gradient(135deg, #f0fdf4, #dcfce7); padding:1rem; border-radius:8px;">
                                    <strong style="color:var(--success-green);">{{ $alerts['targets_reached'] }} cổ phiếu đạt target</strong>
                                    <p style="color:var(--text-secondary); margin:0.5rem 0 0; font-size:0.9rem;">Cân nhắc chốt lời một phần</p>
                                </div>
                            </div>
                        @endif
                        @if($alerts['stop_losses_hit'] > 0)
                            <div class="col-md-6">
                                <div class="alert-item" style="background:linear-gradient(135deg, #fef2f2, #fee2e2); padding:1rem; border-radius:8px;">
                                    <strong style="color:var(--danger-red);">{{ $alerts['stop_losses_hit'] }} cổ phiếu chạm stop loss</strong>
                                    <p style="color:var(--text-secondary); margin:0.5rem 0 0; font-size:0.9rem;">Cân nhắc cắt lỗ</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Portfolio Holdings -->
    <div class="custom-card">
        <div class="card-header-custom">
            <h4 style="color:var(--text-primary); margin:0;">
                <i class="bi bi-list-ul" style="color:var(--primary-blue); margin-right:8px;"></i>
                Danh sách cổ phiếu ({{ $stats['total_items'] }} mã)
            </h4>
        </div>

        <div class="card-body-custom" style="padding:0;">
            @if($portfolio->items->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover" style="margin:0;">
                        <thead style="background:var(--bg-light);">
                            <tr>
                                <th style="padding:1rem; border:none; font-weight:600;">Mã CP</th>
                                <th style="padding:1rem; border:none; font-weight:600;">Tên</th>
                                <th style="padding:1rem; border:none; font-weight:600; text-align:center;">SL</th>
                                <th style="padding:1rem; border:none; font-weight:600; text-align:right;">Giá mua</th>
                                <th style="padding:1rem; border:none; font-weight:600; text-align:right;">Giá hiện tại</th>
                                <th style="padding:1rem; border:none; font-weight:600; text-align:right;">Giá trị</th>
                                <th style="padding:1rem; border:none; font-weight:600; text-align:right;">Lãi/Lỗ</th>
                                <th style="padding:1rem; border:none; font-weight:600; text-align:center;">Tỷ trọng</th>
                                <th style="padding:1rem; border:none; font-weight:600; text-align:center;">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($portfolio->items as $item)
                                @php
                                    $profitLoss = $item->profit_loss;
                                    $profitLossPercent = $item->profit_loss_percent;
                                    $isPositive = $profitLoss >= 0;
                                    $portfolioPercent = $item->percent_of_portfolio;
                                @endphp
                                <tr>
                                    <td style="padding:1rem; border-top:1px solid var(--border-color); font-weight:600; color:var(--primary-blue);">
                                        {{ $item->stock_symbol }}
                                        @if($item->is_at_target)
                                            <i class="bi bi-bullseye text-success" title="Đạt target"></i>
                                        @endif
                                        @if($item->is_at_stop_loss)
                                            <i class="bi bi-exclamation-triangle text-danger" title="Chạm stop loss"></i>
                                        @endif
                                    </td>
                                    <td style="padding:1rem; border-top:1px solid var(--border-color);">
                                        <div>{{ Str::limit($item->stock_name, 25) }}</div>
                                        @if($item->notes)
                                            <small style="color:var(--text-secondary);">{{ Str::limit($item->notes, 30) }}</small>
                                        @endif
                                    </td>
                                    <td style="padding:1rem; border-top:1px solid var(--border-color); text-align:center;">
                                        {{ number_format($item->quantity, 0, ',', '.') }}
                                    </td>
                                    <td style="padding:1rem; border-top:1px solid var(--border-color); text-align:right;">
                                        {{ number_format($item->buy_price, 0, ',', '.') }}₫
                                    </td>
                                    <td style="padding:1rem; border-top:1px solid var(--border-color); text-align:right;">
                                        {{ number_format($item->current_price, 0, ',', '.') }}₫
                                    </td>
                                    <td style="padding:1rem; border-top:1px solid var(--border-color); text-align:right; font-weight:600;">
                                        {{ number_format($item->current_value, 0, ',', '.') }}₫
                                    </td>
                                    <td style="padding:1rem; border-top:1px solid var(--border-color); text-align:right;">
                                        <div style="color:{{ $isPositive ? 'var(--success-green)' : 'var(--danger-red)' }}; font-weight:600;">
                                            {{ $isPositive ? '+' : '' }}{{ number_format($profitLoss, 0, ',', '.') }}₫
                                        </div>
                                        <small style="color:{{ $isPositive ? 'var(--success-green)' : 'var(--danger-red)' }};">
                                            ({{ $isPositive ? '+' : '' }}{{ number_format($profitLossPercent, 2) }}%)
                                        </small>
                                    </td>
                                    <td style="padding:1rem; border-top:1px solid var(--border-color); text-align:center;">
                                        <div style="display:flex; align-items:center; justify-content:center;">
                                            <div style="width:40px; height:6px; background:var(--border-color); border-radius:3px; margin-right:8px;">
                                                <div style="width:{{ min($portfolioPercent, 100) }}%; height:100%; background:var(--primary-blue); border-radius:3px;"></div>
                                            </div>
                                            <small>{{ number_format($portfolioPercent, 1) }}%</small>
                                        </div>
                                    </td>
                                    <td style="padding:1rem; border-top:1px solid var(--border-color); text-align:center;">
                                        <div class="btn-group-sm">
                                            <button class="btn btn-sm btn-outline-primary" onclick="editItem({{ $item->id }})" title="Chỉnh sửa">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" action="{{ route('portfolio.remove-stock', $item->id) }}" 
                                                  style="display:inline;" 
                                                  onsubmit="return confirm('Bạn có chắc chắn muốn xóa cổ phiếu này?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" type="submit" title="Xóa">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <!-- Empty State -->
                <div class="text-center py-5">
                    <i class="bi bi-inbox" style="font-size:3rem; color:var(--text-secondary); margin-bottom:1rem;"></i>
                    <h5 style="color:var(--text-primary); margin-bottom:1rem;">Chưa có cổ phiếu nào</h5>
                    <p style="color:var(--text-secondary); margin-bottom:2rem;">
                        Thêm cổ phiếu đầu tiên vào danh mục để bắt đầu theo dõi.
                    </p>
                    <a href="{{ route('portfolio.add-stock', $portfolio->id) }}" class="btn btn-primary-custom">
                        <i class="bi bi-plus-circle"></i>
                        Thêm cổ phiếu đầu tiên
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Rebalance Suggestions Modal -->
<div class="modal fade" id="rebalanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Gợi ý Rebalance Danh mục</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="rebalanceContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<style>
    .custom-card {
        background: white;
        border-radius: 16px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
    }

    .card-header-custom {
        border-bottom: 1px solid var(--border-color);
        padding: 1.5rem 2rem 1rem;
    }

    .table tr:hover {
        background: var(--bg-light);
    }

    .btn-group-sm .btn {
        padding: 0.25rem 0.5rem;
        margin: 0 2px;
    }
</style>

<script>
async function updatePrices(portfolioId) {
    const refreshIcon = document.getElementById('refresh-icon');
    refreshIcon.classList.add('fa-spin');
    
    try {
        const response = await fetch(`/portfolio/${portfolioId}/update-prices`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            location.reload(); // Refresh page to show updated prices
        } else {
            alert(result.message || 'Không thể cập nhật giá');
        }
    } catch (error) {
        alert('Có lỗi xảy ra khi cập nhật giá');
    } finally {
        refreshIcon.classList.remove('fa-spin');
    }
}

async function getRebalanceSuggestions(portfolioId) {
    try {
        const response = await fetch(`/portfolio/${portfolioId}/rebalance-suggestions`);
        const result = await response.json();
        
        if (result.success) {
            let content = '<div>Chưa có gợi ý rebalance.</div>';
            
            if (result.suggestions && result.suggestions.length > 0) {
                content = '<ul class="list-group">';
                result.suggestions.forEach(suggestion => {
                    content += `<li class="list-group-item">
                        <strong>${suggestion.symbol}</strong>: ${suggestion.reason}
                        <br><small>Hiện tại: ${suggestion.current_percent}% → Đề xuất: ${suggestion.suggested_percent}%</small>
                    </li>`;
                });
                content += '</ul>';
            }
            
            document.getElementById('rebalanceContent').innerHTML = content;
            $('#rebalanceModal').modal('show');
        }
    } catch (error) {
        alert('Có lỗi xảy ra khi tạo gợi ý rebalance');
    }
}

function editItem(itemId) {
    // Simple edit functionality - you can enhance this
    const newQuantity = prompt('Nhập số lượng mới:');
    const newPrice = prompt('Nhập giá mua mới:');
    
    if (newQuantity && newPrice) {
        // Create and submit form
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/portfolio/item/${itemId}/update`;
        
        form.innerHTML = `
            <input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]').getAttribute('content')}">
            <input type="hidden" name="quantity" value="${newQuantity}">
            <input type="hidden" name="buy_price" value="${newPrice}">
        `;
        
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
@endsection
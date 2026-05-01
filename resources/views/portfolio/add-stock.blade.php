@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <!-- Page Header -->
            <div class="mb-4">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb" style="background:none; padding:0;">
                        <li class="breadcrumb-item">
                            <a href="{{ route('portfolio.index') }}" style="color:var(--primary-blue);">Danh mục đầu tư</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="{{ route('portfolio.show', $portfolio->id) }}" style="color:var(--primary-blue);">{{ $portfolio->name }}</a>
                        </li>
                        <li class="breadcrumb-item active" style="color:var(--text-secondary);">Thêm cổ phiếu</li>
                    </ol>
                </nav>
                <h2 style="color:var(--text-primary); font-weight:600; margin:0;">
                    <i class="bi bi-plus-circle" style="color:var(--primary-blue); margin-right:12px;"></i>
                    Thêm cổ phiếu vào danh mục
                </h2>
                <p style="color:var(--text-secondary); margin:0.5rem 0 0;">Danh mục: <strong>{{ $portfolio->name }}</strong></p>
            </div>

            <!-- Add Stock Form -->
            <div class="custom-card">
                <div class="card-header-custom">
                    <h4 style="color:var(--text-primary); margin:0;">
                        <i class="bi bi-graph-up" style="color:var(--primary-blue); margin-right:8px;"></i>
                        Thông tin cổ phiếu
                    </h4>
                </div>

                <div class="card-body-custom" style="padding:2rem;">
                    <form method="POST" action="{{ route('portfolio.store-stock', $portfolio->id) }}">
                        @csrf

                        <!-- Stock Symbol & Name -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label for="stock_symbol" style="font-weight:500; color:var(--text-primary); margin-bottom:0.75rem;">
                                        <i class="bi bi-tag" style="color:var(--primary-blue); margin-right:8px;"></i>
                                        Mã cổ phiếu
                                        <span style="color:var(--danger-red);">*</span>
                                    </label>
                                    <input type="text" 
                                           name="stock_symbol" 
                                           id="stock_symbol"
                                           class="form-control search-input @error('stock_symbol') is-invalid @enderror" 
                                           value="{{ old('stock_symbol') }}" 
                                           required
                                           placeholder="VD: VCB, FPT, VNM"
                                           style="background:white; color:var(--text-primary); padding:1rem; border-radius:12px; border:2px solid var(--border-color); text-transform:uppercase;">
                                    @error('stock_symbol')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="form-group mb-4">
                                    <label for="stock_name" style="font-weight:500; color:var(--text-primary); margin-bottom:0.75rem;">
                                        <i class="bi bi-building" style="color:var(--primary-blue); margin-right:8px;"></i>
                                        Tên công ty
                                        <span style="color:var(--danger-red);">*</span>
                                    </label>
                                    <input type="text" 
                                           name="stock_name" 
                                           id="stock_name"
                                           class="form-control search-input @error('stock_name') is-invalid @enderror" 
                                           value="{{ old('stock_name') }}" 
                                           required
                                           placeholder="VD: Ngân hàng TMCP Ngoại thương Việt Nam"
                                           style="background:white; color:var(--text-primary); padding:1rem; border-radius:12px; border:2px solid var(--border-color);">
                                    @error('stock_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Quantity & Buy Price -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label for="quantity" style="font-weight:500; color:var(--text-primary); margin-bottom:0.75rem;">
                                        <i class="bi bi-hash" style="color:var(--primary-blue); margin-right:8px;"></i>
                                        Số lượng
                                        <span style="color:var(--danger-red);">*</span>
                                    </label>
                                    <input type="number" 
                                           name="quantity" 
                                           id="quantity"
                                           class="form-control search-input @error('quantity') is-invalid @enderror" 
                                           value="{{ old('quantity') }}" 
                                           required
                                           min="1"
                                           placeholder="VD: 100"
                                           style="background:white; color:var(--text-primary); padding:1rem; border-radius:12px; border:2px solid var(--border-color);">
                                    @error('quantity')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label for="buy_price" style="font-weight:500; color:var(--text-primary); margin-bottom:0.75rem;">
                                        <i class="bi bi-cash-coin" style="color:var(--primary-blue); margin-right:8px;"></i>
                                        Giá mua (₫)
                                        <span style="color:var(--danger-red);">*</span>
                                    </label>
                                    <input type="number" 
                                           name="buy_price" 
                                           id="buy_price"
                                           class="form-control search-input @error('buy_price') is-invalid @enderror" 
                                           value="{{ old('buy_price') }}" 
                                           required
                                           step="0.01"
                                           min="0.01"
                                           placeholder="VD: 85000"
                                           style="background:white; color:var(--text-primary); padding:1rem; border-radius:12px; border:2px solid var(--border-color);">
                                    @error('buy_price')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Buy Date -->
                        <div class="form-group mb-4">
                            <label for="buy_date" style="font-weight:500; color:var(--text-primary); margin-bottom:0.75rem;">
                                <i class="bi bi-calendar-event" style="color:var(--primary-blue); margin-right:8px;"></i>
                                Ngày mua
                                <span style="color:var(--danger-red);">*</span>
                            </label>
                            <input type="date" 
                                   name="buy_date" 
                                   id="buy_date"
                                   class="form-control search-input @error('buy_date') is-invalid @enderror" 
                                   value="{{ old('buy_date', date('Y-m-d')) }}" 
                                   required
                                   max="{{ date('Y-m-d') }}"
                                   style="background:white; color:var(--text-primary); padding:1rem; border-radius:12px; border:2px solid var(--border-color);">
                            @error('buy_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Investment Value Display -->
                        <div class="investment-summary mb-4" style="background:linear-gradient(135deg, var(--light-blue), #f0f9ff); padding:1.5rem; border-radius:12px; border-left:4px solid var(--primary-blue);">
                            <h6 style="color:var(--primary-blue); margin-bottom:1rem;">
                                <i class="bi bi-calculator" style="margin-right:8px;"></i>
                                Tổng giá trị đầu tư
                            </h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <p style="color:var(--text-secondary); font-size:0.9rem; margin:0;">Số lượng × Giá mua</p>
                                    <h4 id="totalInvestment" style="color:var(--text-primary); margin:0;">0₫</h4>
                                </div>
                                <div class="col-md-6">
                                    <p style="color:var(--text-secondary); font-size:0.9rem; margin:0;">Tỷ trọng trong danh mục</p>
                                    <h5 id="portfolioPercent" style="color:var(--success-green); margin:0;">0%</h5>
                                </div>
                            </div>
                        </div>

                        <!-- Target & Stop Loss (Optional) -->
                        <h6 style="color:var(--text-primary); margin-bottom:1rem; border-bottom:2px solid var(--border-color); padding-bottom:0.5rem;">
                            <i class="bi bi-bullseye" style="color:var(--warning-orange); margin-right:8px;"></i>
                            Mục tiêu và cảnh báo (tùy chọn)
                        </h6>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label for="target_price" style="font-weight:500; color:var(--text-primary); margin-bottom:0.75rem;">
                                        <i class="bi bi-bullseye" style="color:var(--success-green); margin-right:8px;"></i>
                                        Target Price (₫)
                                    </label>
                                    <input type="number" 
                                           name="target_price" 
                                           id="target_price"
                                           class="form-control search-input @error('target_price') is-invalid @enderror" 
                                           value="{{ old('target_price') }}" 
                                           step="0.01"
                                           min="0.01"
                                           placeholder="VD: 95000"
                                           style="background:white; color:var(--text-primary); padding:1rem; border-radius:12px; border:2px solid var(--border-color);">
                                    @error('target_price')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small style="color:var(--text-secondary);">Giá mục tiêu để chốt lời</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label for="stop_loss_price" style="font-weight:500; color:var(--text-primary); margin-bottom:0.75rem;">
                                        <i class="bi bi-shield-x" style="color:var(--danger-red); margin-right:8px;"></i>
                                        Stop Loss (₫)
                                    </label>
                                    <input type="number" 
                                           name="stop_loss_price" 
                                           id="stop_loss_price"
                                           class="form-control search-input @error('stop_loss_price') is-invalid @enderror" 
                                           value="{{ old('stop_loss_price') }}" 
                                           step="0.01"
                                           min="0.01"
                                           placeholder="VD: 75000"
                                           style="background:white; color:var(--text-primary); padding:1rem; border-radius:12px; border:2px solid var(--border-color);">
                                    @error('stop_loss_price')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small style="color:var(--text-secondary);">Giá cắt lỗ để bảo vệ vốn</small>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="form-group mb-4">
                            <label for="notes" style="font-weight:500; color:var(--text-primary); margin-bottom:0.75rem;">
                                <i class="bi bi-journal-text" style="color:var(--primary-blue); margin-right:8px;"></i>
                                Ghi chú (tùy chọn)
                            </label>
                            <textarea name="notes" 
                                      id="notes"
                                      rows="3"
                                      class="form-control search-input @error('notes') is-invalid @enderror" 
                                      placeholder="Lý do mua, phân tích, hoặc ghi chú khác..."
                                      style="background:white; color:var(--text-primary); padding:1rem; border-radius:12px; border:2px solid var(--border-color); resize:vertical;">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Form Actions -->
                        <div class="d-flex gap-3">
                            <button type="submit" class="btn btn-primary-custom" style="padding:1rem 2rem;">
                                <i class="bi bi-check-circle"></i>
                                Thêm vào danh mục
                            </button>
                            
                            <a href="{{ route('portfolio.show', $portfolio->id) }}" class="btn btn-outline-secondary" style="padding:1rem 2rem;">
                                <i class="bi bi-x-circle"></i>
                                Hủy bỏ
                            </a>
                        </div>

                        @if($errors->any())
                            <div class="alert mt-4" style="background:linear-gradient(135deg, #fee2e2, #fecaca); color:var(--danger-red); 
                                        padding:1rem 1.5rem; border-radius:12px; font-weight:500; 
                                        border:1px solid rgba(239, 68, 68, 0.3);">
                                <i class="bi bi-exclamation-triangle" style="margin-right:8px;"></i>
                                Vui lòng kiểm tra lại thông tin:
                                <ul style="margin:0.5rem 0 0 1rem;">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </form>
                </div>
            </div>

            <!-- Tips Section -->
            <div class="tips-section mt-5">
                <h5 style="color:var(--text-primary); margin-bottom:1.5rem;">
                    <i class="bi bi-lightbulb" style="color:var(--warning-orange); margin-right:8px;"></i>
                    Lời khuyên đầu tư
                </h5>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="tip-card" style="background:linear-gradient(135deg, #f0fdf4, #dcfce7); border:1px solid #16a34a; border-radius:12px; padding:1.5rem;">
                            <h6 style="color:var(--success-green); margin-bottom:1rem;">
                                <i class="bi bi-check-circle" style="margin-right:8px;"></i>
                                Target Price
                            </h6>
                            <ul style="color:var(--text-primary); margin:0; padding-left:1rem; font-size:0.9rem;">
                                <li>Đặt target 10-20% cao hơn giá mua</li>
                                <li>Có thể chốt lời từng phần khi đạt target</li>
                                <li>Review target định kỳ theo thị trường</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="tip-card" style="background:linear-gradient(135deg, #fef2f2, #fee2e2); border:1px solid #dc2626; border-radius:12px; padding:1.5rem;">
                            <h6 style="color:var(--danger-red); margin-bottom:1rem;">
                                <i class="bi bi-shield-x" style="margin-right:8px;"></i>
                                Stop Loss
                            </h6>
                            <ul style="color:var(--text-primary); margin:0; padding-left:1rem; font-size:0.9rem;">
                                <li>Đặt stop loss 5-10% thấp hơn giá mua</li>
                                <li>Tuân thủ nghiêm túc để bảo vệ vốn</li>
                                <li>Có thể điều chỉnh khi giá tăng</li>
                            </ul>
                        </div>
                    </div>
                </div>
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

    .search-input:focus {
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
    }

    #stock_symbol {
        text-transform: uppercase;
    }

    .tip-card:hover {
        transform: translateY(-2px);
        transition: all 0.3s ease;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const quantityInput = document.getElementById('quantity');
    const priceInput = document.getElementById('buy_price');
    const totalElement = document.getElementById('totalInvestment');
    const percentElement = document.getElementById('portfolioPercent');
    
    // Portfolio current value for percentage calculation
    const portfolioCurrentValue = {{ $portfolio->current_value ?: 1 }};
    
    function calculateTotal() {
        const quantity = parseInt(quantityInput.value) || 0;
        const price = parseFloat(priceInput.value) || 0;
        const total = quantity * price;
        
        // Format total investment
        totalElement.textContent = new Intl.NumberFormat('vi-VN').format(total) + '₫';
        
        // Calculate percentage of portfolio
        const percent = portfolioCurrentValue > 0 ? (total / portfolioCurrentValue) * 100 : 0;
        percentElement.textContent = percent.toFixed(2) + '%';
    }
    
    quantityInput.addEventListener('input', calculateTotal);
    priceInput.addEventListener('input', calculateTotal);
    
    // Auto-fetch stock name when symbol is entered (mock functionality)
    const symbolInput = document.getElementById('stock_symbol');
    const nameInput = document.getElementById('stock_name');
    
    symbolInput.addEventListener('blur', function() {
        const symbol = this.value.toUpperCase();
        if (symbol && !nameInput.value) {
            // Mock stock name lookup - in real app, call API
            const stockNames = {
                'VCB': 'Ngân hàng TMCP Ngoại thương Việt Nam',
                'FPT': 'Công ty Cổ phần FPT',
                'VNM': 'Công ty Cổ phần Sữa Việt Nam',
                'VIC': 'Tập đoàn Vingroup',
                'GAS': 'Tổng Công ty Khí Việt Nam',
                'MSN': 'Công ty Cổ phần Tập đoàn Masan',
                'SAB': 'Tổng Công ty Sabeco',
                'HPG': 'Công ty Cổphần Tập đoàn Hòa Phát',
            };
            
            if (stockNames[symbol]) {
                nameInput.value = stockNames[symbol];
            }
        }
    });
});
</script>
@endsection
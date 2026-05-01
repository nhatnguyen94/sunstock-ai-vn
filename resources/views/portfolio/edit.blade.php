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
                        <li class="breadcrumb-item active" style="color:var(--text-secondary);">Chỉnh sửa</li>
                    </ol>
                </nav>
                <h2 style="color:var(--text-primary); font-weight:600; margin:0;">
                    <i class="bi bi-pencil-square" style="color:var(--primary-blue); margin-right:12px;"></i>
                    Chỉnh sửa danh mục đầu tư
                </h2>
            </div>

            <!-- Edit Form -->
            <div class="custom-card">
                <div class="card-header-custom">
                    <h4 style="color:var(--text-primary); margin:0;">
                        <i class="bi bi-briefcase" style="color:var(--primary-blue); margin-right:8px;"></i>
                        Thông tin danh mục
                    </h4>
                </div>

                <div class="card-body-custom" style="padding:2rem;">
                    <form method="POST" action="{{ route('portfolio.update', $portfolio->id) }}">
                        @csrf
                        @method('PUT')

                        <!-- Portfolio Name -->
                        <div class="form-group mb-4">
                            <label for="name" style="font-weight:500; color:var(--text-primary); margin-bottom:0.75rem;">
                                <i class="bi bi-tag" style="color:var(--primary-blue); margin-right:8px;"></i>
                                Tên danh mục đầu tư
                                <span style="color:var(--danger-red);">*</span>
                            </label>
                            <input type="text" 
                                   name="name" 
                                   id="name"
                                   class="form-control search-input @error('name') is-invalid @enderror" 
                                   value="{{ old('name', $portfolio->name) }}" 
                                   required
                                   placeholder="VD: Danh mục cổ phiếu ngân hàng"
                                   style="background:white; color:var(--text-primary); padding:1rem; border-radius:12px; border:2px solid var(--border-color);">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Portfolio Description -->
                        <div class="form-group mb-4">
                            <label for="description" style="font-weight:500; color:var(--text-primary); margin-bottom:0.75rem;">
                                <i class="bi bi-card-text" style="color:var(--primary-blue); margin-right:8px;"></i>
                                Mô tả (tùy chọn)
                            </label>
                            <textarea name="description" 
                                      id="description"
                                      rows="4"
                                      class="form-control search-input @error('description') is-invalid @enderror" 
                                      placeholder="Mô tả chi tiết về danh mục đầu tư của bạn..."
                                      style="background:white; color:var(--text-primary); padding:1rem; border-radius:12px; border:2px solid var(--border-color); resize:vertical;">{{ old('description', $portfolio->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Portfolio Status -->
                        <div class="form-group mb-4">
                            <label style="font-weight:500; color:var(--text-primary); margin-bottom:0.75rem;">
                                <i class="bi bi-toggle-on" style="color:var(--primary-blue); margin-right:8px;"></i>
                                Trạng thái danh mục
                            </label>
                            <div class="custom-control custom-switch">
                                <input type="checkbox" 
                                       class="custom-control-input" 
                                       id="is_active" 
                                       name="is_active" 
                                       value="1" 
                                       {{ old('is_active', $portfolio->is_active) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="is_active" style="color:var(--text-primary);">
                                    Kích hoạt danh mục (cho phép giao dịch và cập nhật giá)
                                </label>
                            </div>
                        </div>

                        <!-- Portfolio Stats (Read-only) -->
                        <div class="portfolio-stats mb-4" style="background:var(--bg-light); border-radius:12px; padding:1.5rem;">
                            <h6 style="color:var(--text-primary); margin-bottom:1rem;">
                                <i class="bi bi-bar-chart" style="color:var(--primary-blue); margin-right:8px;"></i>
                                Thống kê danh mục
                            </h6>
                            <div class="row">
                                <div class="col-md-3 text-center">
                                    <p style="color:var(--text-secondary); font-size:0.9rem; margin:0;">Số cổ phiếu</p>
                                    <h5 style="color:var(--text-primary); margin:0;">{{ $portfolio->items->count() }}</h5>
                                </div>
                                <div class="col-md-3 text-center">
                                    <p style="color:var(--text-secondary); font-size:0.9rem; margin:0;">Tổng vốn</p>
                                    <h5 style="color:var(--text-primary); margin:0;">{{ number_format($portfolio->total_invested, 0, ',', '.') }}₫</h5>
                                </div>
                                <div class="col-md-3 text-center">
                                    <p style="color:var(--text-secondary); font-size:0.9rem; margin:0;">Giá trị hiện tại</p>
                                    <h5 style="color:var(--text-primary); margin:0;">{{ number_format($portfolio->current_value, 0, ',', '.') }}₫</h5>
                                </div>
                                <div class="col-md-3 text-center">
                                    @php
                                        $profitLoss = $portfolio->current_value - $portfolio->total_invested;
                                        $isPositive = $profitLoss >= 0;
                                    @endphp
                                    <p style="color:var(--text-secondary); font-size:0.9rem; margin:0;">Lãi/Lỗ</p>
                                    <h5 style="color:{{ $isPositive ? 'var(--success-green)' : 'var(--danger-red)' }}; margin:0;">
                                        {{ $isPositive ? '+' : '' }}{{ number_format($profitLoss, 0, ',', '.') }}₫
                                    </h5>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="d-flex gap-3">
                            <button type="submit" class="btn btn-primary-custom" style="padding:1rem 2rem;">
                                <i class="bi bi-check-circle"></i>
                                Cập nhật danh mục
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

            <!-- Danger Zone -->
            <div class="custom-card mt-4" style="border:1px solid var(--danger-red);">
                <div class="card-header-custom" style="background:linear-gradient(135deg, #fef2f2, #fee2e2); border-bottom:1px solid var(--danger-red);">
                    <h5 style="color:var(--danger-red); margin:0;">
                        <i class="bi bi-exclamation-triangle" style="margin-right:8px;"></i>
                        Vùng nguy hiểm
                    </h5>
                </div>
                <div class="card-body-custom" style="padding:2rem;">
                    <p style="color:var(--text-primary); margin-bottom:1.5rem;">
                        Xóa danh mục sẽ xóa vĩnh viễn tất cả dữ liệu bao gồm các cổ phiếu và lịch sử giao dịch. 
                        Hành động này không thể hoàn tác.
                    </p>
                    
                    <form method="POST" action="{{ route('portfolio.destroy', $portfolio->id) }}" 
                          onsubmit="return confirm('Bạn có CHẮC CHẮN muốn xóa danh mục này? Tất cả dữ liệu sẽ bị mất vĩnh viễn!')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger" style="padding:0.75rem 1.5rem;">
                            <i class="bi bi-trash"></i>
                            Xóa danh mục vĩnh viễn
                        </button>
                    </form>
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

    .custom-control-input:checked ~ .custom-control-label::before {
        background-color: var(--success-green);
        border-color: var(--success-green);
    }

    .portfolio-stats {
        border: 1px solid var(--border-color);
    }
</style>
@endsection
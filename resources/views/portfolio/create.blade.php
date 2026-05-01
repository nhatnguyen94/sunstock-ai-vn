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
                        <li class="breadcrumb-item active" style="color:var(--text-secondary);">Tạo mới</li>
                    </ol>
                </nav>
                <h2 style="color:var(--text-primary); font-weight:600; margin:0;">
                    <i class="bi bi-plus-circle" style="color:var(--primary-blue); margin-right:12px;"></i>
                    Tạo danh mục đầu tư mới
                </h2>
            </div>

            <!-- Create Form -->
            <div class="custom-card">
                <div class="card-header-custom">
                    <h4 style="color:var(--text-primary); margin:0;">
                        <i class="bi bi-briefcase" style="color:var(--primary-blue); margin-right:8px;"></i>
                        Thông tin danh mục
                    </h4>
                </div>

                <div class="card-body-custom" style="padding:2rem;">
                    <form method="POST" action="{{ route('portfolio.store') }}">
                        @csrf

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
                                   value="{{ old('name') }}" 
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
                                      style="background:white; color:var(--text-primary); padding:1rem; border-radius:12px; border:2px solid var(--border-color); resize:vertical;">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Info Box -->
                        <div class="info-box mb-4" style="background:linear-gradient(135deg, var(--light-blue), #f0f9ff); border-left:4px solid var(--primary-blue); padding:1.5rem; border-radius:12px;">
                            <h6 style="color:var(--primary-blue); margin-bottom:1rem;">
                                <i class="bi bi-info-circle" style="margin-right:8px;"></i>
                                Lưu ý quan trọng
                            </h6>
                            <ul style="color:var(--text-primary); margin:0; padding-left:1.5rem;">
                                <li style="margin-bottom:0.5rem;">Sau khi tạo danh mục, bạn có thể thêm các cổ phiếu vào danh mục</li>
                                <li style="margin-bottom:0.5rem;">Hệ thống sẽ tự động tính toán lãi/lỗ dựa trên giá thị trường</li>
                                <li style="margin-bottom:0.5rem;">Bạn có thể đặt target price và stop loss cho từng cổ phiếu</li>
                                <li>Danh mục có thể được chỉnh sửa hoặc xóa bất cứ lúc nào</li>
                            </ul>
                        </div>

                        <!-- Form Actions -->
                        <div class="d-flex gap-3">
                            <button type="submit" class="btn btn-primary-custom" style="padding:1rem 2rem;">
                                <i class="bi bi-check-circle"></i>
                                Tạo danh mục
                            </button>
                            
                            <a href="{{ route('portfolio.index') }}" class="btn btn-outline-secondary" style="padding:1rem 2rem;">
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

            <!-- Sample Portfolio Ideas -->
            <div class="mt-5">
                <h5 style="color:var(--text-primary); margin-bottom:1.5rem;">
                    <i class="bi bi-lightbulb" style="color:var(--warning-orange); margin-right:8px;"></i>
                    Gợi ý tên danh mục
                </h5>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="suggestion-card" style="background:white; border:1px solid var(--border-color); border-radius:12px; padding:1.5rem;">
                            <h6 style="color:var(--primary-blue); margin-bottom:1rem;">Theo ngành nghề</h6>
                            <ul style="color:var(--text-secondary); margin:0; padding-left:1rem; font-size:0.9rem;">
                                <li>Danh mục Ngân hàng</li>
                                <li>Cổ phiếu BĐS</li>
                                <li>Công nghệ & Viễn thông</li>
                                <li>Hàng tiêu dùng</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="suggestion-card" style="background:white; border:1px solid var(--border-color); border-radius:12px; padding:1.5rem;">
                            <h6 style="color:var(--success-green); margin-bottom:1rem;">Theo chiến lược</h6>
                            <ul style="color:var(--text-secondary); margin:0; padding-left:1rem; font-size:0.9rem;">
                                <li>Đầu tư dài hạn</li>
                                <li>Trade ngắn hạn</li>
                                <li>Cổ phiếu cổ tức cao</li>
                                <li>Cổ phiếu tăng trưởng</li>
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

    .suggestion-card:hover {
        box-shadow: var(--shadow-sm);
        transform: translateY(-1px);
        transition: all 0.3s ease;
    }
</style>
@endsection
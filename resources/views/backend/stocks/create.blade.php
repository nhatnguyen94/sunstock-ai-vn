@extends('layouts.admin')

@section('title', 'Thêm mã cổ phiếu')
@section('page_pretitle', 'Dữ liệu')
@section('page_title', 'Thêm mã cổ phiếu mới')

@section('breadcrumbs')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
    </li>
    <li class="breadcrumb-item">
        <a href="{{ route('admin.stocks.index') }}">Cổ phiếu</a>
    </li>
    <li class="breadcrumb-item active">Thêm mới</li>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.stocks.store') }}">
        @csrf
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Thông tin cổ phiếu</h3>
                <div class="card-actions">
                    <span class="text-muted small">Thông tin sàn & ngành được đồng bộ tự động từ vnstock</span>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label required">Mã cổ phiếu</label>
                            <input type="text" class="form-control @error('symbol') is-invalid @enderror"
                                   name="symbol" value="{{ old('symbol') }}"
                                   placeholder="VD: VCB, FPT, ACB" maxlength="10"
                                   style="text-transform:uppercase" required>
                            @error('symbol')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label class="form-label required">Tên công ty</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                   name="name" value="{{ old('name') }}"
                                   placeholder="VD: Ngân hàng TMCP Ngoại Thương Việt Nam" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Trạng thái</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active"
                                       value="1" {{ old('is_active', '1') ? 'checked' : '' }} id="is_active">
                                <label class="form-check-label" for="is_active">Hoạt động</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label class="form-label text-muted">Thông tin sàn & ngành</label>
                            <div class="alert alert-info py-2 mb-0">
                                Sàn giao dịch và ngành nghề được đồng bộ tự động khi chạy
                                <code>php artisan stock:sync</code>. Không cần nhập thủ công.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer text-end">
                <a href="{{ route('admin.stocks.index') }}" class="btn btn-outline-secondary me-2">Hủy</a>
                <button type="submit" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="16" height="16" viewBox="0 0 24 24"
                         stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <path d="M6 4h10l4 4v10a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12a2 2 0 0 1 2 -2"/>
                        <circle cx="12" cy="14" r="2"/>
                        <polyline points="14 4 14 8 6 8 6 4"/>
                    </svg>
                    Lưu cổ phiếu
                </button>
            </div>
        </div>
    </form>
@endsection

@section('title', 'Thêm mã cổ phiếu')
@section('page_pretitle', 'Dữ liệu')
@section('page_title', 'Thêm mã cổ phiếu mới')

@section('breadcrumbs')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
    </li>
    <li class="breadcrumb-item">
        <a href="{{ route('admin.stocks.index') }}">Cổ phiếu</a>
    </li>
    <li class="breadcrumb-item active">Thêm mới</li>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.stocks.store') }}">
        @csrf
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Thông tin cổ phiếu</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label required">Mã cổ phiếu</label>
                            <input type="text" class="form-control @error('symbol') is-invalid @enderror"
                                   name="symbol" value="{{ old('symbol') }}"
                                   placeholder="VD: VCB, FPT, ACB" maxlength="10"
                                   style="text-transform:uppercase" required>
                            @error('symbol')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label class="form-label required">Tên công ty</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                   name="name" value="{{ old('name') }}"
                                   placeholder="VD: Ngân hàng TMCP Ngoại Thương Việt Nam" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Sàn giao dịch</label>
                            <select name="exchange" class="form-select @error('exchange') is-invalid @enderror">
                                <option value="">-- Chọn sàn --</option>
                                <option value="HSX" {{ old('exchange') === 'HSX' ? 'selected' : '' }}>HSX (HoSE)</option>
                                <option value="HNX" {{ old('exchange') === 'HNX' ? 'selected' : '' }}>HNX</option>
                                <option value="UPCOM" {{ old('exchange') === 'UPCOM' ? 'selected' : '' }}>UPCOM</option>
                            </select>
                            @error('exchange')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Ngành nghề</label>
                            <input type="text" class="form-control @error('industry') is-invalid @enderror"
                                   name="industry" value="{{ old('industry') }}"
                                   placeholder="VD: Ngân hàng, Công nghệ, Bất động sản">
                            @error('industry')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Vốn hóa (VND)</label>
                            <input type="number" class="form-control @error('market_cap') is-invalid @enderror"
                                   name="market_cap" value="{{ old('market_cap') }}"
                                   placeholder="0" min="0" step="0.01">
                            @error('market_cap')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Trạng thái</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active"
                                       value="1" {{ old('is_active', '1') ? 'checked' : '' }} id="is_active">
                                <label class="form-check-label" for="is_active">Hoạt động</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer text-end">
                <a href="{{ route('admin.stocks.index') }}" class="btn btn-outline-secondary me-2">Hủy</a>
                <button type="submit" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="16" height="16" viewBox="0 0 24 24"
                         stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <path d="M6 4h10l4 4v10a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12a2 2 0 0 1 2 -2"/>
                        <circle cx="12" cy="14" r="2"/>
                        <polyline points="14 4 14 8 6 8 6 4"/>
                    </svg>
                    Lưu cổ phiếu
                </button>
            </div>
        </div>
    </form>
@endsection

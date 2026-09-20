@extends('layouts.app')

@section('title', 'Tạo danh mục đầu tư | Sun Stock AI')

@section('head')
@vite('resources/frontend/css/portfolio/portfolio.css')
@endsection

@section('content')
<div class="pf-page">
<div class="container">
<div class="pf-form-wrap">

    <div class="pf-head">
        <div>
            <div class="pf-crumbs"><a href="{{ route('portfolio.index') }}">Danh mục đầu tư</a><span aria-hidden="true">/</span><span>Tạo mới</span></div>
            <h1 class="pf-title"><i class="bi bi-plus-circle"></i> Tạo danh mục mới</h1>
            <p class="pf-sub">Bạn có thể tạo nhiều danh mục, ví dụ “Dài hạn”, “Lướt sóng”, “Cổ tức”.</p>
        </div>
    </div>

    @if(session('info'))<div class="pf-alert info"><i class="bi bi-info-circle"></i><div>{{ session('info') }}</div></div>@endif

    <div class="pf-card">
        <div class="pf-card-body">
            @if($errors->any())
                <div class="pf-check-err"><i class="bi bi-exclamation-triangle"></i> Vui lòng kiểm tra lại thông tin:
                    <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <form method="POST" action="{{ route('portfolio.store') }}">
                @csrf
                @if(!empty($symbol))<input type="hidden" name="symbol" value="{{ $symbol }}">@endif

                <div class="pf-field">
                    <label for="name">Tên danh mục <span class="req">*</span></label>
                    <input type="text" id="name" name="name" class="pf-input @error('name') is-invalid @enderror" value="{{ old('name') }}" required maxlength="255" autofocus
                           placeholder="VD: Danh mục cổ phiếu ngân hàng">
                    @error('name')<div class="pf-error">{{ $message }}</div>@enderror
                </div>

                <div class="pf-field">
                    <label for="description">Mô tả <small>(tùy chọn)</small></label>
                    <textarea id="description" name="description" rows="3" maxlength="1000" class="pf-input @error('description') is-invalid @enderror"
                              placeholder="Mục tiêu, chiến lược, thời gian nắm giữ…">{{ old('description') }}</textarea>
                    @error('description')<div class="pf-error">{{ $message }}</div>@enderror
                </div>

                <div class="pf-alert info" style="margin-bottom:0">
                    <i class="bi bi-lightbulb"></i>
                    <div><strong>Sau khi tạo</strong><span>Thêm cổ phiếu (giá thị trường được điền sẵn), đặt giá mục tiêu / cắt lỗ để nhận email cảnh báo và theo dõi biểu đồ hiệu suất.</span></div>
                </div>

                <div class="pf-form-actions">
                    <button type="submit" class="pf-btn pf-btn-primary"><i class="bi bi-check2-circle"></i> Tạo danh mục{{ !empty($symbol) ? ' & thêm ' . $symbol : '' }}</button>
                    <a href="{{ route('portfolio.index') }}" class="pf-btn pf-btn-ghost">Hủy</a>
                </div>
            </form>
        </div>
    </div>
</div>
</div>
</div>
@endsection

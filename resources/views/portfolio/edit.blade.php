@extends('layouts.app')

@section('title', 'Chỉnh sửa ' . $portfolio->name . ' | Sun Stock AI')

@section('head')
@vite('resources/frontend/css/portfolio/portfolio.css')
@endsection

@section('content')
<div class="pf-page">
<div class="container">
<div class="pf-form-wrap">

    <div class="pf-head">
        <div>
            <div class="pf-crumbs">
                <a href="{{ route('portfolio.index') }}">Danh mục đầu tư</a><span aria-hidden="true">/</span>
                <a href="{{ route('portfolio.show', $portfolio->id) }}">{{ $portfolio->name }}</a><span aria-hidden="true">/</span><span>Chỉnh sửa</span>
            </div>
            <h1 class="pf-title"><i class="bi bi-pencil-square"></i> Chỉnh sửa danh mục</h1>
        </div>
    </div>

    <div class="pf-card">
        <div class="pf-card-body">
            @if($errors->any())
                <div class="pf-check-err"><i class="bi bi-exclamation-triangle"></i> Vui lòng kiểm tra lại thông tin:
                    <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <form method="POST" action="{{ route('portfolio.update', $portfolio->id) }}">
                @csrf @method('PUT')

                <div class="pf-field">
                    <label for="name">Tên danh mục <span class="req">*</span></label>
                    <input type="text" id="name" name="name" class="pf-input @error('name') is-invalid @enderror" value="{{ old('name', $portfolio->name) }}" required maxlength="255">
                    @error('name')<div class="pf-error">{{ $message }}</div>@enderror
                </div>

                <div class="pf-field">
                    <label for="description">Mô tả <small>(tùy chọn)</small></label>
                    <textarea id="description" name="description" rows="3" maxlength="1000" class="pf-input @error('description') is-invalid @enderror">{{ old('description', $portfolio->description) }}</textarea>
                    @error('description')<div class="pf-error">{{ $message }}</div>@enderror
                </div>

                <div class="pf-field">
                    {{-- The hidden 0 makes an UNCHECKED box actually arrive as false (browsers omit unchecked checkboxes,
                         which made it impossible to deactivate a portfolio). --}}
                    <input type="hidden" name="is_active" value="0">
                    <label class="pf-switch">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $portfolio->is_active) ? 'checked' : '' }}>
                        <span>Đang theo dõi <small class="pf-help" style="display:block;font-weight:500">Danh mục tạm dừng không còn được cập nhật giá và không gửi cảnh báo.</small></span>
                    </label>
                </div>

                <div class="pf-form-actions">
                    <button type="submit" class="pf-btn pf-btn-primary"><i class="bi bi-check2-circle"></i> Lưu thay đổi</button>
                    <a href="{{ route('portfolio.show', $portfolio->id) }}" class="pf-btn pf-btn-ghost">Hủy</a>
                </div>
            </form>
        </div>
    </div>
</div>
</div>
</div>
@endsection

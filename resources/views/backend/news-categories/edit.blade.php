@extends('layouts.admin')

@section('title', 'Chỉnh sửa Danh mục')
@section('page_pretitle', 'Quản lý dữ liệu')
@section('page_title', 'Chỉnh sửa Danh mục: ' . $category->name)

@section('breadcrumbs')
    <i class="ti ti-chevron-right"></i><a href="{{ route('admin.news.index') }}">News</a>
    <i class="ti ti-chevron-right"></i><a href="{{ route('admin.news-categories.index') }}">Danh mục</a>
    <i class="ti ti-chevron-right"></i><span class="current">Chỉnh sửa</span>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.news-categories.update', $category) }}">
        @csrf
        @method('PUT')
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Thông tin Danh mục</h3>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label required">Tên danh mục</label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror"
                           name="name" value="{{ old('name', $category->name) }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Slug</label>
                    <input type="text" class="form-control @error('slug') is-invalid @enderror"
                           name="slug" value="{{ old('slug', $category->slug) }}">
                    @error('slug')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="text-muted small">
                    Đang có <strong>{{ $category->news()->count() }}</strong> bài viết thuộc danh mục này.
                </div>
            </div>
            <div class="card-footer">
                <div class="d-flex">
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-eye me-2"></i>
                        Cập nhật Danh mục
                    </button>
                    <a href="{{ route('admin.news-categories.index') }}" class="btn btn-link ms-auto">Hủy</a>
                </div>
            </div>
        </div>
    </form>
@endsection

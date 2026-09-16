@extends('layouts.admin')

@section('title', 'Chỉnh sửa Danh mục')
@section('page_pretitle', 'Quản lý dữ liệu')
@section('page_title', 'Chỉnh sửa Danh mục: ' . $category->name)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.news.index') }}">News</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.news-categories.index') }}">Danh mục</a></li>
    <li class="breadcrumb-item active">Chỉnh sửa</li>
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
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                            <circle cx="12" cy="12" r="2"/>
                            <path d="M12 1v6m0 6v6"/>
                        </svg>
                        Cập nhật Danh mục
                    </button>
                    <a href="{{ route('admin.news-categories.index') }}" class="btn btn-link ms-auto">Hủy</a>
                </div>
            </div>
        </div>
    </form>
@endsection

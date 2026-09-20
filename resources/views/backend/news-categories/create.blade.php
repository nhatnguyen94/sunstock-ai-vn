@extends('layouts.admin')

@section('title', 'Tạo Danh mục mới')
@section('page_pretitle', 'Quản lý dữ liệu')
@section('page_title', 'Tạo Danh mục Tin tức mới')

@section('breadcrumbs')
    <i class="ti ti-chevron-right"></i><a href="{{ route('admin.news.index') }}">News</a>
    <i class="ti ti-chevron-right"></i><a href="{{ route('admin.news-categories.index') }}">Danh mục</a>
    <i class="ti ti-chevron-right"></i><span class="current">Tạo mới</span>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.news-categories.store') }}">
        @csrf
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Thông tin Danh mục</h3>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label required">Tên danh mục</label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror"
                           name="name" value="{{ old('name') }}" placeholder="vd: Bất động sản" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Slug</label>
                    <input type="text" class="form-control @error('slug') is-invalid @enderror"
                           name="slug" value="{{ old('slug') }}" placeholder="vd: bat-dong-san">
                    @error('slug')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="form-hint">Bỏ trống để tự tạo từ tên danh mục.</small>
                </div>
            </div>
            <div class="card-footer">
                <div class="d-flex">
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-plus me-2"></i>
                        Tạo Danh mục
                    </button>
                    <a href="{{ route('admin.news-categories.index') }}" class="btn btn-link ms-auto">Hủy</a>
                </div>
            </div>
        </div>
    </form>
@endsection

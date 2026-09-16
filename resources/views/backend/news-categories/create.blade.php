@extends('layouts.admin')

@section('title', 'Tạo Danh mục mới')
@section('page_pretitle', 'Quản lý dữ liệu')
@section('page_title', 'Tạo Danh mục Tin tức mới')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.news.index') }}">News</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.news-categories.index') }}">Danh mục</a></li>
    <li class="breadcrumb-item active">Tạo mới</li>
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
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Tạo Danh mục
                    </button>
                    <a href="{{ route('admin.news-categories.index') }}" class="btn btn-link ms-auto">Hủy</a>
                </div>
            </div>
        </div>
    </form>
@endsection

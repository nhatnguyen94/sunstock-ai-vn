@extends('layouts.admin')

@section('title', 'Danh mục Tin tức')
@section('page_pretitle', 'Quản lý dữ liệu')
@section('page_title', 'Danh mục Tin tức')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.news.index') }}">News</a></li>
    <li class="breadcrumb-item active">Danh mục</li>
@endsection

@section('page_actions')
    <a href="{{ route('admin.news-categories.create') }}" class="btn btn-primary">
        <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
            <line x1="12" y1="5" x2="12" y2="19"/>
            <line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Tạo Danh mục mới
    </a>
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Danh sách Danh mục</h3>
            <div class="card-actions">
                <span class="text-muted">{{ $categories->count() }} danh mục</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Tên</th>
                        <th>Slug</th>
                        <th>Số bài viết</th>
                        <th class="w-1">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $category)
                    <tr>
                        <td class="font-weight-medium">{{ $category->name }}</td>
                        <td><code>{{ $category->slug }}</code></td>
                        <td><span class="badge bg-purple-lt">{{ $category->news_count }}</span></td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="{{ route('admin.news-categories.edit', $category) }}" class="btn btn-sm btn-outline-warning">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"/>
                                        <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z"/>
                                    </svg>
                                </a>
                                <form method="POST" action="{{ route('admin.news-categories.destroy', $category) }}" class="d-inline"
                                      onsubmit="return confirm('Bạn có chắc chắn muốn xoá danh mục này?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                            <line x1="4" y1="7" x2="20" y2="7"/>
                                            <line x1="10" y1="11" x2="10" y2="17"/>
                                            <line x1="14" y1="11" x2="14" y2="17"/>
                                            <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"/>
                                            <path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">Chưa có danh mục nào.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

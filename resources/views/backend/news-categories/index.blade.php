@extends('layouts.admin')

@section('title', 'Danh mục Tin tức')
@section('page_pretitle', 'Quản lý dữ liệu')
@section('page_title', 'Danh mục Tin tức')

@section('breadcrumbs')
    <i class="ti ti-chevron-right"></i><a href="{{ route('admin.news.index') }}">News</a>
    <i class="ti ti-chevron-right"></i><span class="current">Danh mục</span>
@endsection

@section('page_actions')
    <a href="{{ route('admin.news-categories.create') }}" class="btn btn-primary">
        <i class="ti ti-plus me-2"></i>
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
                        <td class="fw-medium">{{ $category->name }}</td>
                        <td><code>{{ $category->slug }}</code></td>
                        <td><span class="badge bg-purple-lt">{{ $category->news_count }}</span></td>
                        <td>
                            <div class="table-row-actions">
                                <a href="{{ route('admin.news-categories.edit', $category) }}" class="btn btn-sm btn-icon btn-ghost-secondary">
                                    <i class="ti ti-pencil"></i>
                                </a>
                                <form method="POST" action="{{ route('admin.news-categories.destroy', $category) }}" class="d-inline"
                                      data-confirm="Bạn có chắc chắn muốn xoá danh mục này?" data-confirm-title="Xác nhận xóa" data-confirm-ok="Xóa">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-icon btn-ghost-secondary">
                                        <i class="ti ti-trash text-danger"></i>
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

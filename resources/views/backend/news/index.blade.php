@extends('layouts.admin')

@section('title', 'Quản lý Tin tức')
@section('page_pretitle', 'Nội dung')
@section('page_title', 'Quản lý Tin tức')

@section('breadcrumbs')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
    </li>
    <li class="breadcrumb-item active">Tin tức</li>
@endsection

@section('page_actions')
    <div class="btn-list">
        <form action="{{ route('admin.news.update-rss') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4"/>
                    <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/>
                </svg>
                Cập nhật RSS
            </button>
        </form>
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Danh sách tin tức từ VnExpress</h3>
                    <div class="card-actions">
                        <a href="#" class="btn btn-outline-primary btn-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                <circle cx="10" cy="10" r="7"/>
                                <path d="M21 21l-6 -6"/>
                            </svg>
                            Tìm kiếm
                        </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>Tiêu đề</th>
                                <th>Mô tả</th>
                                <th>Nguồn</th>
                                <th>Thời gian</th>
                                <th>Trạng thái</th>
                                <th class="w-1">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Placeholder for news items -->
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="empty">
                                        <div class="empty-img">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-lg" width="48" height="48" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                <path d="M16 6h3a1 1 0 0 1 1 1v11a1 1 0 0 1 -1 1h-14a1 1 0 0 1 -1 -1v-11a1 1 0 0 1 1 -1h3"/>
                                                <rect x="8" y="2" width="8" height="4" rx="1"/>
                                                <line x1="9" y1="12" x2="9.01" y2="12"/>
                                                <line x1="13" y1="12" x2="15" y2="12"/>
                                                <line x1="9" y1="16" x2="9.01" y2="16"/>
                                                <line x1="13" y1="16" x2="15" y2="16"/>
                                            </svg>
                                        </div>
                                        <p class="empty-title">Chưa có tin tức nào</p>
                                        <p class="empty-subtitle text-muted">
                                            Nhấn nút "Cập nhật RSS" để lấy tin tức mới từ VnExpress
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- News Statistics -->
    <div class="row mt-4">
        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="subheader">Tổng tin tức</div>
                    </div>
                    <div class="h1 mb-3">0</div>
                    <div class="d-flex mb-2">
                        <div class="flex-1">
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-primary" style="width: 0%" role="progressbar"></div>
                            </div>
                        </div>
                        <div class="text-muted ms-2">0%</div>
                    </div>
                    <div class="text-muted">Trong 24h qua</div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="subheader">Tin mới trong ngày</div>
                    </div>
                    <div class="h1 mb-3">0</div>
                    <div class="d-flex mb-2">
                        <div class="flex-1">
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-green" style="width: 0%" role="progressbar"></div>
                            </div>
                        </div>
                        <div class="text-muted ms-2">0%</div>
                    </div>
                    <div class="text-muted">So với hôm qua</div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="subheader">Lần cập nhật cuối</div>
                    </div>
                    <div class="h1 mb-3">N/A</div>
                    <div class="d-flex mb-2">
                        <div class="flex-1">
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-yellow" style="width: 0%" role="progressbar"></div>
                            </div>
                        </div>
                        <div class="text-muted ms-2">0h</div>
                    </div>
                    <div class="text-muted">Giờ trước</div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="subheader">Trạng thái RSS</div>
                    </div>
                    <div class="h1 mb-3 text-success">Hoạt động</div>
                    <div class="d-flex mb-2">
                        <div class="flex-1">
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-success" style="width: 100%" role="progressbar"></div>
                            </div>
                        </div>
                        <div class="text-muted ms-2">100%</div>
                    </div>
                    <div class="text-muted">VnExpress RSS</div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    // Auto refresh news every 30 minutes
    setInterval(function() {
        if (confirm('Cập nhật tin tức mới?')) {
            document.querySelector('form[action*="update-rss"]').submit();
        }
    }, 1800000); // 30 minutes
</script>
@endpush
@extends('layouts.admin')

@section('title', 'Quản lý Tin tức')
@section('page_pretitle', 'Nội dung')
@section('page_title', 'Tin tức thị trường')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Tin tức</li>
@endsection

@section('page_actions')
    <div class="btn-list">
        <form action="{{ route('admin.news.update-rss') }}" method="POST" class="d-inline" id="sync-form">
            @csrf
            <button type="submit" class="btn btn-primary" id="sync-btn">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="24" height="24" viewBox="0 0 24 24"
                     stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
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

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            {{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Filter card --}}
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.news.index') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Tìm kiếm tiêu đề</label>
                    <input type="text" name="search" class="form-control"
                           placeholder="Nhập từ khóa..." value="{{ $filters['search'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Chuyên mục</label>
                    <select name="category_id" class="form-select">
                        <option value="">Tất cả chuyên mục</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" @selected(($filters['category_id'] ?? '') == $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Nguồn</label>
                    <select name="source" class="form-select">
                        <option value="">Tất cả nguồn</option>
                        @foreach($sources as $src)
                            <option value="{{ $src }}" @selected(($filters['source'] ?? '') === $src)>{{ $src }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Từ ngày</label>
                    <input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Đến ngày</label>
                    <input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Lọc</button>
                    <a href="{{ route('admin.news.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Results card --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                Danh sách tin tức
                <span class="badge bg-blue-lt ms-2">{{ number_format($news->total()) }} bài</span>
            </h3>
        </div>

        @if($news->isEmpty())
            <div class="card-body text-center py-5 text-muted">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon mb-2" width="48" height="48" viewBox="0 0 24 24"
                     stroke-width="1" stroke="currentColor" fill="none">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M4 6h16M4 12h16M4 18h7"/>
                </svg>
                <p>Chưa có tin tức. Nhấn <strong>Cập nhật RSS</strong> để đồng bộ lần đầu.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table table-hover">
                    <thead>
                        <tr>
                            <th style="width:48%">Tiêu đề</th>
                            <th>Nguồn</th>
                            <th>Chuyên mục</th>
                            <th>Thời gian đăng</th>
                            <th class="w-1"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($news as $item)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-start gap-2">
                                        @if($item->image_url)
                                            <img src="{{ $item->image_url }}"
                                                 class="rounded flex-shrink-0"
                                                 style="width:64px;height:48px;object-fit:cover;"
                                                 loading="lazy"
                                                 onerror="this.style.display='none'">
                                        @endif
                                        <div>
                                            <div class="fw-semibold lh-sm">{{ $item->title }}</div>
                                            @if($item->description)
                                                <div class="text-muted small mt-1"
                                                     style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                                                    {{ $item->description }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @php
                                        $badge = match($item->source) {
                                            'VnExpress'          => 'bg-blue-lt',
                                            'CafeF'              => 'bg-green-lt',
                                            'Tinnhanhchungkhoan' => 'bg-orange-lt',
                                            default              => 'bg-secondary-lt',
                                        };
                                    @endphp
                                    <span class="badge {{ $badge }}">{{ $item->source }}</span>
                                </td>
                                <td>
                                    <span class="text-muted small">{{ $item->category?->name ?? '—' }}</span>
                                </td>
                                <td>
                                    <span title="{{ $item->published_at->format('d/m/Y H:i') }}">
                                        {{ $item->published_at->diffForHumans() }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ $item->url }}" target="_blank" rel="noopener noreferrer"
                                       class="btn btn-sm btn-outline-secondary">
                                        Xem gốc
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($news->hasPages())
                <div class="card-footer d-flex align-items-center">
                    {{ $news->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection

@push('scripts')
<script>
document.getElementById('sync-form').addEventListener('submit', function () {
    const btn = document.getElementById('sync-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang cập nhật...';
});
</script>
@endpush
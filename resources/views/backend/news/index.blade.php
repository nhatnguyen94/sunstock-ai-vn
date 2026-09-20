@extends('layouts.admin')

@section('title', 'Quản lý Tin tức')
@section('page_pretitle', 'Nội dung')
@section('page_title', 'Tin tức thị trường')

@section('breadcrumbs')
    <i class="ti ti-chevron-right"></i><span class="current">Tin tức</span>
@endsection

@section('page_actions')
    <div class="btn-list">
        <form action="{{ route('admin.news.update-rss') }}" method="POST" class="d-inline" id="sync-form">
            @csrf
            <button type="submit" class="btn btn-primary" id="sync-btn">
                <i class="ti ti-refresh me-2"></i>
                Cập nhật RSS
            </button>
        </form>
    </div>
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <form method="GET" action="{{ route('admin.news.index') }}" class="ad-toolbar flex-fill">
                <div class="input-icon" style="min-width:240px">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="search" name="search" class="form-control" placeholder="Tìm theo tiêu đề…" value="{{ $filters['search'] ?? '' }}" aria-label="Tìm tin tức">
                </div>
                <select name="category_id" class="form-select" aria-label="Chuyên mục" style="max-width:190px">
                    <option value="">Mọi chuyên mục</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(($filters['category_id'] ?? '') == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
                <select name="source" class="form-select" aria-label="Nguồn" style="max-width:170px">
                    <option value="">Mọi nguồn</option>
                    @foreach($sources as $src)
                        <option value="{{ $src }}" @selected(($filters['source'] ?? '') === $src)>{{ $src }}</option>
                    @endforeach
                </select>
                <input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}" aria-label="Từ ngày" title="Từ ngày" style="max-width:160px">
                <input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}" aria-label="Đến ngày" title="Đến ngày" style="max-width:160px">
                <button type="submit" class="btn btn-primary">Lọc</button>
                @if(request()->hasAny(['search', 'category_id', 'source', 'date_from', 'date_to']))
                    <a href="{{ route('admin.news.index') }}" class="btn btn-ghost-secondary"><i class="ti ti-x me-1"></i>Xóa lọc</a>
                @endif
            </form>
            <span class="badge bg-blue-lt">{{ number_format($news->total()) }} bài</span>
        </div>

        @if($news->isEmpty())
            <div class="empty">
                <div class="empty-icon"><i class="ti ti-news-off"></i></div>
                <p class="empty-title">Chưa có tin tức</p>
                <p class="empty-subtitle text-secondary">Nhấn <strong>Cập nhật RSS</strong> để đồng bộ lần đầu, hoặc đổi bộ lọc.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter table-hover card-table">
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
                                    <div class="d-flex align-items-start gap-3">
                                        @if($item->image_url)
                                            <img src="{{ $item->image_url }}" class="rounded flex-shrink-0" style="width:72px;height:52px;object-fit:cover;" loading="lazy" alt="" onerror="this.style.display='none'">
                                        @endif
                                        <div class="min-w-0">
                                            <div class="fw-semibold lh-sm">{{ $item->title }}</div>
                                            @if($item->description)
                                                <div class="text-secondary small mt-1 text-truncate-2">{{ $item->description }}</div>
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
                                <td class="text-secondary small">{{ $item->category?->name ?? '—' }}</td>
                                <td class="text-nowrap small" title="{{ $item->published_at->format('d/m/Y H:i') }}">{{ $item->published_at->diffForHumans() }}</td>
                                <td class="text-end">
                                    <a href="{{ $item->url }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary">Xem gốc <i class="ti ti-external-link ms-1"></i></a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($news->hasPages())
                <div class="card-footer">{{ $news->links() }}</div>
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
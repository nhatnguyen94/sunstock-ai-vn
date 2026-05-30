@extends('layouts.app')

@section('title', isset($categorySlug) ? 'Tin tức · ' . ($categories->firstWhere('slug', $categorySlug)?->name ?? $categorySlug) : 'Tin tức thị trường – Sun Stock AI')

@section('head')
@vite('resources/frontend/css/news/index.css')
@endsection

@section('content')
<div class="news-page-wrapper">

    {{-- Hero / title bar --}}
    <div class="news-hero">
        <div class="container">
            <h1 class="news-hero-title">
                <i class="bi bi-newspaper"></i>
                @if($categorySlug && $currentCategory = $categories->firstWhere('slug', $categorySlug))
                    {{ $currentCategory->name }}
                @else
                    Tin tức thị trường
                @endif
            </h1>
            <p class="news-hero-sub">Tổng hợp từ VnExpress, CafeF, Dân Trí · Cập nhật mỗi 30 phút</p>
        </div>
    </div>

    <div class="container news-layout">

        {{-- Left: articles --}}
        <div class="news-main">

            {{-- Search bar --}}
            <form method="GET" action="{{ $categorySlug ? route('news.category', $categorySlug) : route('news.index') }}" class="news-search-form">
                <div class="news-search-wrap">
                    <i class="bi bi-search news-search-icon"></i>
                    <input type="text" name="search" class="news-search-input"
                           placeholder="Tìm kiếm bài viết..." value="{{ request('search') }}">
                    @if(request('search'))
                        <a href="{{ $categorySlug ? route('news.category', $categorySlug) : route('news.index') }}" class="news-search-clear">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    @endif
                </div>
            </form>

            {{-- Results count --}}
            <div class="news-result-info">
                {{ number_format($news->total()) }} bài viết
                @if(request('search'))
                    · kết quả cho "<strong>{{ request('search') }}</strong>"
                @endif
            </div>

            @if($news->isEmpty())
                <div class="news-empty">
                    <i class="bi bi-inbox news-empty-icon"></i>
                    <p>Không tìm thấy bài viết nào.</p>
                </div>
            @else
                <div class="news-grid">
                    @foreach($news as $item)
                    <article class="news-card" data-aos="fade-up" data-aos-delay="{{ ($loop->index % 3) * 60 }}">
                        @if($item->image_url)
                        <a href="{{ $item->url }}" target="_blank" rel="noopener noreferrer" class="news-card-img-wrap">
                            <img src="{{ $item->image_url }}" alt="{{ $item->title }}" loading="lazy"
                                 onerror="this.closest('.news-card-img-wrap').style.display='none'">
                        </a>
                        @endif
                        <div class="news-card-body">
                            <div class="news-card-meta">
                                <span class="news-source-badge news-source-{{ Str::slug($item->source) }}">{{ $item->source }}</span>
                                @if($item->category)
                                <a href="{{ route('news.category', $item->category->slug) }}" class="news-cat-link">
                                    {{ $item->category->name }}
                                </a>
                                @endif
                            </div>
                            <a href="{{ $item->url }}" target="_blank" rel="noopener noreferrer" class="news-card-title">
                                {{ $item->title }}
                            </a>
                            @if($item->description)
                            <p class="news-card-desc">{{ $item->description }}</p>
                            @endif
                            <div class="news-card-footer">
                                <span class="news-card-time">
                                    <i class="bi bi-clock"></i>
                                    {{ $item->published_at->diffForHumans() }}
                                </span>
                                <a href="{{ $item->url }}" target="_blank" rel="noopener noreferrer" class="news-read-more">
                                    Đọc thêm <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </article>
                    @endforeach
                </div>

                {{-- Pagination --}}
                @if($news->hasPages())
                <div class="news-pagination">
                    {{ $news->links() }}
                </div>
                @endif
            @endif
        </div>

        {{-- Right: category sidebar --}}
        <aside class="news-sidebar">
            <div class="news-sidebar-card">
                <h3 class="news-sidebar-title">
                    <i class="bi bi-grid-3x3-gap"></i> Chuyên mục
                </h3>
                <ul class="news-cat-list">
                    <li>
                        <a href="{{ route('news.index') }}"
                           class="news-cat-item {{ !$categorySlug ? 'active' : '' }}">
                            <i class="bi bi-journals"></i> Tất cả
                        </a>
                    </li>
                    @foreach($categories as $cat)
                    <li>
                        <a href="{{ route('news.category', $cat->slug) }}"
                           class="news-cat-item {{ $categorySlug === $cat->slug ? 'active' : '' }}">
                            <i class="bi bi-tag"></i> {{ $cat->name }}
                        </a>
                    </li>
                    @endforeach
                </ul>
            </div>
        </aside>

    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Thêm ' . ($symbol ?: 'cổ phiếu') . ' vào danh mục | Sun Stock AI')

@section('head')
@vite('resources/frontend/css/portfolio/portfolio.css')
@endsection

@use('App\Support\VnFormat', 'F')

@section('content')
<div class="pf-page">
<div class="container">
<div class="pf-form-wrap" style="max-width:640px">

    <div class="pf-head">
        <div>
            <h1 class="pf-title"><i class="bi bi-folder-plus"></i> Thêm {{ $symbol ?: 'cổ phiếu' }} vào danh mục</h1>
            <p class="pf-sub">Chọn danh mục bạn muốn thêm vào.</p>
        </div>
    </div>

    <div class="pf-choose">
        @foreach($portfolios as $p)
            <a href="{{ route('portfolio.add-stock', ['id' => $p->id, 'symbol' => $symbol]) }}">
                <span><strong>{{ $p->name }}</strong><br><small class="pf-help">{{ $p->items->count() }} cổ phiếu · {{ F::number($p->current_value) }}₫</small></span>
                <i class="bi bi-chevron-right"></i>
            </a>
        @endforeach
        <a href="{{ route('portfolio.create', ['symbol' => $symbol]) }}" style="border-style:dashed"><span><strong><i class="bi bi-plus-lg"></i> Tạo danh mục mới</strong></span><i class="bi bi-chevron-right"></i></a>
    </div>
</div>
</div>
</div>
@endsection

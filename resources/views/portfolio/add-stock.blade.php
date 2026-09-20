@extends('layouts.app')

@section('title', 'Thêm cổ phiếu vào ' . $portfolio->name . ' | Sun Stock AI')

@section('head')
@vite('resources/frontend/css/portfolio/portfolio.css')
@endsection

@php
    $pageData = [
        'quoteUrl'   => '/portfolio/quote',
        'portfolioValue' => (float) $portfolio->current_value,
        'symbol'     => old('stock_symbol', $symbol ?? ''),
    ];
@endphp

@section('content')
<div class="pf-page">
<div class="container">
<div class="pf-form-wrap">

    <div class="pf-head">
        <div>
            <div class="pf-crumbs">
                <a href="{{ route('portfolio.index') }}">Danh mục đầu tư</a><span aria-hidden="true">/</span>
                <a href="{{ route('portfolio.show', $portfolio->id) }}">{{ $portfolio->name }}</a><span aria-hidden="true">/</span><span>Thêm cổ phiếu</span>
            </div>
            <h1 class="pf-title"><i class="bi bi-plus-circle"></i> Thêm cổ phiếu</h1>
            <p class="pf-sub">Danh mục: <strong>{{ $portfolio->name }}</strong> — mua thêm mã đã có sẽ tự tính lại giá vốn bình quân.</p>
        </div>
    </div>

    @if(session('success'))<div class="pf-alert good"><i class="bi bi-check-circle"></i><div>{{ session('success') }}</div></div>@endif

    <div class="pf-card">
        <div class="pf-card-body">
            @if($errors->any())
                <div class="pf-check-err"><i class="bi bi-exclamation-triangle"></i> Vui lòng kiểm tra lại thông tin:
                    <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <form method="POST" action="{{ route('portfolio.store-stock', $portfolio->id) }}" id="pfAddForm" autocomplete="off">
                @csrf
                <input type="hidden" name="stock_name" id="stock_name" value="{{ old('stock_name') }}">

                <div class="pf-field">
                    <label for="stock_symbol">Mã cổ phiếu <span class="req">*</span></label>
                    <div class="pf-input-group">
                        <input type="text" id="stock_symbol" name="stock_symbol" class="pf-input search-input @error('stock_symbol') is-invalid @enderror"
                               value="{{ old('stock_symbol', $symbol ?? '') }}" required maxlength="10" style="text-transform:uppercase"
                               placeholder="Gõ mã hoặc tên công ty: FPT, VCB, Hòa Phát…" autofocus>
                    </div>
                    @error('stock_symbol')<div class="pf-error">{{ $message }}</div>@enderror
                </div>

                <div class="pf-quote" id="pfQuote" role="status" aria-live="polite">
                    <span id="pfQuoteText"></span>
                    <button type="button" class="pf-btn pf-btn-soft pf-btn-sm" id="pfUseQuote"><i class="bi bi-magic"></i> Dùng giá này</button>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="pf-field">
                            <label for="quantity">Số lượng <span class="req">*</span> <small>(cổ phiếu)</small></label>
                            <input type="number" id="quantity" name="quantity" class="pf-input @error('quantity') is-invalid @enderror" value="{{ old('quantity') }}" required min="1" step="1" placeholder="VD: 100">
                            @error('quantity')<div class="pf-error">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="pf-field">
                            <label for="buy_price">Giá mua <span class="req">*</span> <small>(₫ / cổ phiếu)</small></label>
                            <div class="pf-input-group">
                                <input type="number" id="buy_price" name="buy_price" class="pf-input @error('buy_price') is-invalid @enderror" value="{{ old('buy_price') }}" required min="100" step="any" placeholder="VD: 85000">
                                <span class="suffix">₫</span>
                            </div>
                            <div class="pf-help">Nhập theo đồng: 85.000₫ gõ là <b>85000</b>.</div>
                            @error('buy_price')<div class="pf-error">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="pf-field">
                    <label for="buy_date">Ngày mua <span class="req">*</span></label>
                    <input type="date" id="buy_date" name="buy_date" class="pf-input @error('buy_date') is-invalid @enderror" value="{{ old('buy_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                    @error('buy_date')<div class="pf-error">{{ $message }}</div>@enderror
                </div>

                <div class="pf-summary">
                    <div><small>Tổng vốn mua</small><strong id="totalInvestment">0₫</strong></div>
                    <div><small>Tỷ trọng trong danh mục sau khi thêm</small><strong id="portfolioPercent">—</strong></div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="pf-field">
                            <label for="target_price">Giá mục tiêu <small>(₫, tùy chọn — báo khi đạt)</small></label>
                            <input type="number" id="target_price" name="target_price" class="pf-input @error('target_price') is-invalid @enderror" value="{{ old('target_price') }}" min="100" step="any" placeholder="VD: 95000">
                            @error('target_price')<div class="pf-error">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="pf-field">
                            <label for="stop_loss_price">Giá cắt lỗ <small>(₫, tùy chọn — báo khi chạm)</small></label>
                            <input type="number" id="stop_loss_price" name="stop_loss_price" class="pf-input @error('stop_loss_price') is-invalid @enderror" value="{{ old('stop_loss_price') }}" min="100" step="any" placeholder="VD: 78000">
                            @error('stop_loss_price')<div class="pf-error">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
                <div class="pf-help" style="margin:-.5rem 0 1.25rem">
                    Gợi ý nhanh:
                    <button type="button" class="pf-btn pf-btn-soft pf-btn-sm" data-preset="target" data-pct="10">Mục tiêu +10%</button>
                    <button type="button" class="pf-btn pf-btn-soft pf-btn-sm" data-preset="target" data-pct="20">+20%</button>
                    <button type="button" class="pf-btn pf-btn-danger pf-btn-sm" data-preset="stop" data-pct="-7">Cắt lỗ −7%</button>
                    <button type="button" class="pf-btn pf-btn-danger pf-btn-sm" data-preset="stop" data-pct="-10">−10%</button>
                    <span> — tính từ giá mua. Email cảnh báo được gửi một lần mỗi lần chạm.</span>
                </div>

                <div class="pf-field">
                    <label for="notes">Ghi chú <small>(tùy chọn)</small></label>
                    <textarea id="notes" name="notes" rows="2" maxlength="1000" class="pf-input" placeholder="Lý do mua, kế hoạch…">{{ old('notes') }}</textarea>
                </div>

                <div class="pf-form-actions">
                    <button type="submit" class="pf-btn pf-btn-primary"><i class="bi bi-check2-circle"></i> Thêm vào danh mục</button>
                    <a href="{{ route('portfolio.show', $portfolio->id) }}" class="pf-btn pf-btn-ghost">Hủy</a>
                </div>
            </form>
        </div>
    </div>
</div>
</div>
</div>
@endsection

@section('scripts')
<script>window.__PF__ = @json($pageData);</script>
@vite('resources/frontend/js/portfolio/add-stock.js')
@endsection

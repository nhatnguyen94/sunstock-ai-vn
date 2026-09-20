@extends('layouts.app')

@section('title', 'Danh sách theo dõi cổ phiếu | Sun Stock AI')

@section('head')
@vite(['resources/frontend/css/watchlist/watchlist.css', 'resources/frontend/css/shared/watchlist.css'])
@endsection

@php
    $pageData = [
        'auth' => true,
        'open' => (bool) $market_open,
        'dataUrl' => route('watchlist.data', [], false),
        'storeUrl' => route('watchlist.store', [], false),
        'rows' => $rows,
        'max' => $max_items,
        'tradeDate' => $trade_date?->format('d/m/Y'),
    ];
@endphp

@section('content')
<section class="wlp-header">
    <div class="container">
        <div class="wlp-header-row">
            <div>
                <div class="wlp-badge"><i class="bi bi-star-fill"></i> Cá nhân hóa</div>
                <h1 class="wlp-title">Danh sách theo dõi</h1>
                <p class="wlp-sub" id="wlSub">
                    @if($trade_date)
                        Giá phiên {{ $trade_date->format('d/m/Y') }}
                        <span class="wlp-live {{ $market_open ? 'open' : '' }}"><i></i> {{ $market_open ? 'Đang giao dịch — tự cập nhật mỗi phút' : 'Ngoài giờ giao dịch' }}</span>
                    @else
                        Chưa có dữ liệu thị trường
                    @endif
                </p>
            </div>
            <form class="wlp-add" id="wlAddForm" autocomplete="off">
                <div class="wlp-add-box">
                    <i class="bi bi-search"></i>
                    <input type="text" id="wlSymbol" placeholder="Thêm mã: FPT, VNM, VCB… hoặc tên công ty" maxlength="30" required>
                </div>
                <button type="submit" class="wlp-add-btn" id="wlAddBtn"><i class="bi bi-plus-lg"></i> Thêm</button>
            </form>
        </div>
    </div>
</section>

<div class="wlp-main"><div class="container">
    <div class="wlp-card">
        <div class="wlp-toolbar">
            <div class="wlp-stats" id="wlStats"></div>
            <label class="wlp-sort">Sắp xếp
                <select id="wlSort">
                    <option value="added">Mới thêm</option>
                    <option value="gain">% tăng nhiều nhất</option>
                    <option value="loss">% giảm nhiều nhất</option>
                    <option value="value">Thanh khoản cao</option>
                    <option value="symbol">Mã A → Z</option>
                </select>
            </label>
        </div>
        <div class="table-responsive">
            <table class="wlp-table">
                <thead><tr>
                    <th style="width:44px"></th><th>Mã</th><th class="num">Giá (₫)</th><th class="num">+/- (₫)</th><th class="num">%</th>
                    <th class="num d-none d-md-table-cell">KLGD</th><th class="num d-none d-md-table-cell">GTGD</th><th class="d-none d-lg-table-cell" style="width:90px">20 phiên</th><th class="num">Thao tác</th>
                </tr></thead>
                <tbody id="wlBody"></tbody>
            </table>
        </div>
        <div class="wlp-empty" id="wlEmpty" hidden>
            <i class="bi bi-star"></i>
            <h3>Chưa theo dõi mã nào</h3>
            <p>Gõ mã hoặc tên công ty ở ô phía trên để thêm. Bạn cũng có thể bấm ★ ở trang chủ, trang cổ phiếu và hồ sơ công ty.</p>
        </div>
        <p class="wlp-foot"><span id="wlCount">0</span>/{{ $max_items }} mã · giá lấy từ bảng giá thị trường; mã chưa có trong bảng giá dùng giá đóng cửa gần nhất.</p>
    </div>
</div></div>
@endsection

@section('scripts')
<script>window.__WL__ = @json($pageData);</script>
@vite('resources/frontend/js/watchlist/index.js')
@endsection

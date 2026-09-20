@extends('layouts.app')

@section('title', 'Danh mục đầu tư | Sun Stock AI')

@section('head')
@vite('resources/frontend/css/portfolio/portfolio.css')
@endsection

@use('App\Support\VnFormat', 'F')

@section('content')
<div class="pf-page">
<div class="container">

    <div class="pf-head">
        <div>
            <h1 class="pf-title"><i class="bi bi-briefcase"></i> Danh mục đầu tư</h1>
            <p class="pf-sub">Theo dõi lãi/lỗ, tỷ trọng và cảnh báo giá cho các cổ phiếu bạn đang nắm giữ.</p>
        </div>
        <div class="pf-actions">
            <a href="{{ route('portfolio.create') }}" class="pf-btn pf-btn-primary"><i class="bi bi-plus-lg"></i> Tạo danh mục mới</a>
        </div>
    </div>

    @if(session('success'))<div class="pf-alert good"><i class="bi bi-check-circle"></i><div>{{ session('success') }}</div></div>@endif
    @if(session('info'))<div class="pf-alert info"><i class="bi bi-info-circle"></i><div>{{ session('info') }}</div></div>@endif
    @if($errors->any())<div class="pf-alert bad"><i class="bi bi-exclamation-triangle"></i><div>{{ $errors->first() }}</div></div>@endif

    @if($portfolios->count() > 0)
        @php $tUp = $totalStats['is_positive']; @endphp
        <div class="pf-kpis">
            <div class="pf-kpi"><div class="pf-kpi-label">Tổng giá trị</div><div class="pf-kpi-value">{{ F::number($totalStats['current_value']) }}₫</div><div class="pf-kpi-sub">{{ $totalStats['total_portfolios'] }} danh mục</div></div>
            <div class="pf-kpi"><div class="pf-kpi-label">Tổng vốn</div><div class="pf-kpi-value">{{ F::number($totalStats['total_invested']) }}₫</div></div>
            <div class="pf-kpi {{ $tUp ? 'up' : 'down' }}"><div class="pf-kpi-label">Lãi / Lỗ</div>
                <div class="pf-kpi-value {{ $tUp ? 'up' : 'down' }}">{{ $tUp ? '+' : '' }}{{ F::number($totalStats['profit_loss']) }}₫</div>
                <div class="pf-kpi-sub"><span class="{{ $tUp ? 'up' : 'down' }}"><strong>{{ F::percent($totalStats['profit_loss_percent'], 2, true) }}</strong></span></div></div>
        </div>

        <div class="pf-cards">
        @foreach($portfolios as $portfolio)
            @php
                $pl = $portfolio->current_value - $portfolio->total_invested;
                $plPct = $portfolio->total_invested > 0 ? ($pl / $portfolio->total_invested) * 100 : 0;
                $isUp = $pl >= 0;
                $top = $portfolio->items->sortByDesc(fn ($i) => $i->current_value)->take(5);
            @endphp
            <div class="pf-card pf-pcard">
                <div class="pf-card-head">
                    <h5><a href="{{ route('portfolio.show', $portfolio->id) }}">{{ $portfolio->name }}</a></h5>
                    <div class="pf-menu">
                        <button type="button" class="pf-btn pf-btn-ghost pf-btn-icon pf-btn-sm pf-menu-btn" aria-label="Tùy chọn"><i class="bi bi-three-dots"></i></button>
                        <div class="pf-menu-list">
                            <a href="{{ route('portfolio.show', $portfolio->id) }}"><i class="bi bi-eye"></i> Xem chi tiết</a>
                            <a href="{{ route('portfolio.edit', $portfolio->id) }}"><i class="bi bi-pencil"></i> Chỉnh sửa</a>
                            <a href="{{ route('portfolio.export', $portfolio->id) }}"><i class="bi bi-filetype-csv"></i> Xuất CSV</a>
                            <hr>
                            <button type="button" class="danger pf-del-portfolio" data-action="{{ route('portfolio.destroy', $portfolio->id) }}" data-name="{{ $portfolio->name }}"><i class="bi bi-trash"></i> Xóa</button>
                        </div>
                    </div>
                </div>
                <div class="pf-card-body">
                    @if($portfolio->description)<p class="pf-hint" style="margin:0 0 .75rem">{{ \Illuminate\Support\Str::limit($portfolio->description, 80) }}</p>@endif
                    <div class="pf-mini"><span>Vốn</span><b>{{ F::number($portfolio->total_invested) }}₫</b></div>
                    <div class="pf-mini"><span>Giá trị hiện tại</span><b>{{ F::number($portfolio->current_value) }}₫</b></div>
                    <div class="pf-pl-box {{ $isUp ? 'up' : 'down' }}">
                        <span style="font-weight:700">Lãi / Lỗ</span>
                        <span class="{{ $isUp ? 'up' : 'down' }}"><strong>{{ $isUp ? '+' : '' }}{{ F::number($pl) }}₫</strong> <span class="pf-pill {{ $isUp ? 'up' : 'down' }}">{{ F::percent($plPct, 2, true) }}</span></span>
                    </div>
                    <div class="pf-syms">
                        @forelse($top as $i)<span class="pf-chip">{{ $i->stock_symbol }}</span>@empty<span class="pf-hint" style="margin:0">Chưa có cổ phiếu nào</span>@endforelse
                        @if($portfolio->items->count() > 5)<span class="pf-chip">+{{ $portfolio->items->count() - 5 }}</span>@endif
                    </div>
                </div>
                <div class="pf-card-foot">
                    <a href="{{ route('portfolio.show', $portfolio->id) }}" class="pf-btn pf-btn-soft"><i class="bi bi-eye"></i> Chi tiết</a>
                    <a href="{{ route('portfolio.add-stock', $portfolio->id) }}" class="pf-btn pf-btn-ghost"><i class="bi bi-plus-lg"></i> Thêm cổ phiếu</a>
                </div>
            </div>
        @endforeach
        </div>
    @else
        <div class="pf-card">
            <div class="pf-empty">
                <i class="bi bi-briefcase big"></i>
                <h4>Bắt đầu theo dõi danh mục của bạn</h4>
                <p>Ghi lại các cổ phiếu bạn đang giữ và xem ngay lãi/lỗ, tỷ trọng, biểu đồ hiệu suất cùng lịch cổ tức — không cần bảng tính.</p>
                <div class="pf-steps">
                    <div class="pf-step"><b>1</b><h6>Tạo danh mục</h6><p>Đặt tên, ví dụ “Dài hạn” hoặc “Ngân hàng”.</p></div>
                    <div class="pf-step"><b>2</b><h6>Thêm cổ phiếu</h6><p>Chọn mã, số lượng, giá mua — giá thị trường được điền sẵn.</p></div>
                    <div class="pf-step"><b>3</b><h6>Theo dõi</h6><p>Lãi/lỗ theo ngày, cảnh báo qua email khi chạm mục tiêu hoặc cắt lỗ.</p></div>
                </div>
                <a href="{{ route('portfolio.create') }}" class="pf-btn pf-btn-primary"><i class="bi bi-plus-lg"></i> Tạo danh mục đầu tiên</a>
            </div>
        </div>
    @endif
</div>
</div>

<div class="modal fade pf-modal" id="pfDeletePortfolio" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
        <form class="modal-content" method="POST" id="pfDeletePortfolioForm" action="#">
            @csrf @method('DELETE')
            <div class="modal-body text-center">
                <i class="bi bi-exclamation-triangle down" style="font-size:2rem"></i>
                <h5 class="mt-2" style="font-weight:800">Xóa danh mục “<span id="pfDelName"></span>”?</h5>
                <p class="pf-hint">Toàn bộ cổ phiếu trong danh mục sẽ bị xóa và không thể khôi phục.</p>
                <div class="d-flex justify-content-center" style="gap:.5rem"><button type="button" class="pf-btn pf-btn-ghost" data-dismiss="modal">Hủy</button><button type="submit" class="pf-btn pf-btn-danger-solid">Xóa danh mục</button></div>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
@vite('resources/frontend/js/portfolio/index.js')
@endsection

@extends('layouts.admin')

@section('title', 'Thống kê Portfolio')
@section('page_pretitle', 'Quản lý')
@section('page_title', 'Thống kê Portfolio')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.portfolios.index') }}">Portfolio</a></li>
    <li class="breadcrumb-item active">Thống kê</li>
@endsection

@section('page_actions')
    <a href="{{ route('admin.portfolios.index') }}" class="btn btn-outline-primary">Danh sách Portfolio</a>
@endsection

@section('content')
    @php $up = $stats['profit_percent'] >= 0; @endphp
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6"><div class="card"><div class="card-body">
            <div class="subheader">Tổng Portfolio</div><div class="h1 mb-1">{{ $stats['total'] }}</div>
            <div class="text-muted">{{ $stats['active'] }} đang hoạt động</div></div></div></div>
        <div class="col-lg-3 col-md-6"><div class="card"><div class="card-body">
            <div class="subheader">Có cổ phiếu</div><div class="h1 mb-1">{{ $stats['with_items'] }}</div>
            <div class="text-muted">Trung bình {{ number_format($stats['avg_items_per_portfolio'], 1, ',', '.') }} mã / portfolio</div></div></div></div>
        <div class="col-lg-3 col-md-6"><div class="card"><div class="card-body">
            <div class="subheader">Người dùng</div><div class="h1 mb-1">{{ $stats['owners'] }}</div>
            <div class="text-muted">Đang dùng tính năng danh mục</div></div></div></div>
        <div class="col-lg-3 col-md-6"><div class="card"><div class="card-body">
            <div class="subheader">Giá trị / Vốn</div>
            <div class="h1 mb-1 {{ $up ? 'text-success' : 'text-danger' }}">{{ $up ? '+' : '' }}{{ number_format($stats['profit_percent'], 2, ',', '.') }}%</div>
            <div class="text-muted">{{ number_format($stats['value'], 0, ',', '.') }} ₫ / {{ number_format($stats['invested'], 0, ',', '.') }} ₫</div></div></div></div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">10 mã được nắm giữ nhiều nhất</h3></div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead><tr><th>Mã</th><th class="text-end">Số portfolio</th><th class="text-end">Tổng giá trị nắm giữ</th></tr></thead>
                <tbody>
                @forelse($stats['top_symbols'] as $row)
                    <tr>
                        <td><span class="badge bg-blue-lt">{{ $row->stock_symbol }}</span></td>
                        <td class="text-end">{{ $row->portfolios }}</td>
                        <td class="text-end">{{ number_format($row->value, 0, ',', '.') }} ₫</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted py-4">Chưa có cổ phiếu nào trong các portfolio.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

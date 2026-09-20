@extends('layouts.admin')

@section('title', 'Quản lý Portfolio')
@section('page_pretitle', 'Quản lý')
@section('page_title', 'Quản lý Portfolio')

@section('breadcrumbs')
    <i class="ti ti-chevron-right"></i><span class="current">Portfolio</span>
@endsection

@section('page_actions')
    <a href="{{ route('admin.portfolios.stats') }}" class="btn btn-outline-secondary"><i class="ti ti-chart-line me-1"></i> Thống kê</a>
@endsection

@section('content')
    @php $sUp = $summary['profit_percent'] >= 0; @endphp

    {{-- Portfolio statistics (real numbers, from the controller) --}}
    <div class="row row-cards g-3 mb-3">
        <div class="col-sm-6 col-xl-3">
            <div class="card"><div class="ad-stat">
                <span class="ad-stat-icon blue"><i class="ti ti-briefcase"></i></span>
                <div class="ad-stat-body">
                    <div class="ad-stat-label">Tổng Portfolio</div>
                    <div class="ad-stat-value">{{ $summary['total'] }}</div>
                    <div class="ad-stat-sub">{{ $summary['active'] }} đang hoạt động · {{ $summary['total'] - $summary['active'] }} tạm dừng</div>
                </div>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card"><div class="ad-stat">
                <span class="ad-stat-icon green"><i class="ti ti-wallet"></i></span>
                <div class="ad-stat-body">
                    <div class="ad-stat-label">Tổng giá trị</div>
                    <div class="ad-stat-value text-success" style="font-size:1.4rem">{{ number_format($summary['value'], 0, ',', '.') }} ₫</div>
                    <div class="ad-stat-sub">Vốn đầu tư {{ number_format($summary['invested'], 0, ',', '.') }} ₫</div>
                </div>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card"><div class="ad-stat">
                <span class="ad-stat-icon purple"><i class="ti ti-users"></i></span>
                <div class="ad-stat-body">
                    <div class="ad-stat-label">Người dùng có portfolio</div>
                    <div class="ad-stat-value">{{ $summary['owners'] }}</div>
                    <div class="ad-stat-sub">Chủ sở hữu khác nhau</div>
                </div>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card"><div class="ad-stat">
                <span class="ad-stat-icon {{ $sUp ? 'green' : 'red' }}"><i class="ti ti-trending-{{ $sUp ? 'up' : 'down' }}"></i></span>
                <div class="ad-stat-body">
                    <div class="ad-stat-label">Lãi/Lỗ toàn hệ thống</div>
                    <div class="ad-stat-value {{ $sUp ? 'text-success' : 'text-danger' }}">{{ $sUp ? '+' : '' }}{{ number_format($summary['profit_percent'], 2, ',', '.') }}%</div>
                    <div class="ad-stat-sub">Giá trị so với vốn</div>
                </div>
            </div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <form method="GET" action="{{ route('admin.portfolios.index') }}" class="ad-toolbar flex-fill">
                <div class="input-icon" style="min-width:300px">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Tên portfolio, tên hoặc email chủ sở hữu…" aria-label="Tìm portfolio">
                </div>
                <select name="status" class="form-select" onchange="this.form.submit()" aria-label="Trạng thái" style="max-width:170px">
                    <option value="">Tất cả</option>
                    <option value="active" @selected(request('status') === 'active')>Hoạt động</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Tạm dừng</option>
                </select>
                <button class="btn btn-primary" type="submit">Tìm</button>
                @if(request()->hasAny(['search', 'status']))<a href="{{ route('admin.portfolios.index') }}" class="btn btn-ghost-secondary"><i class="ti ti-x me-1"></i>Xóa lọc</a>@endif
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter table-hover card-table">
                <thead>
                    <tr>
                        <th>Portfolio</th>
                        <th>Chủ sở hữu</th>
                        <th>Số mã CP</th>
                        <th class="text-end">Giá trị</th>
                        <th class="text-end">Lãi/Lỗ</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
                        <th class="w-1 text-end">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($portfolios ?? [] as $portfolio)
                    @php
                        $profitLoss = $portfolio->total_profit_loss;
                        $profitPercent = $portfolio->total_profit_loss_percent;
                        $active = $portfolio->is_active ?? true;
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $portfolio->name }}</div>
                            <div class="text-secondary small">#{{ $portfolio->id }}@if($portfolio->description) · {{ Str::limit($portfolio->description, 50) }}@endif</div>
                        </td>
                        <td>
                            <div class="ad-avatar-cell">
                                <span class="ad-avatar">{{ mb_strtoupper(mb_substr($portfolio->user->name ?? 'U', 0, 1)) }}</span>
                                <div class="ad-lines"><b>{{ $portfolio->user->name ?? 'N/A' }}</b><small>{{ $portfolio->user->email ?? 'N/A' }}</small></div>
                            </div>
                        </td>
                        <td><span class="badge bg-blue-lt">{{ $portfolio->items_count }} mã</span></td>
                        <td class="text-end tabular-nums fw-bold">{{ number_format($portfolio->current_value, 0, ',', '.') }} ₫</td>
                        <td class="text-end tabular-nums {{ $profitLoss >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $profitLoss >= 0 ? '+' : '' }}{{ number_format($profitLoss, 0, ',', '.') }} ₫
                            <div class="small">({{ $profitPercent >= 0 ? '+' : '' }}{{ number_format($profitPercent, 2) }}%)</div>
                        </td>
                        <td><span class="ad-dot {{ $active ? 'ok' : 'warn' }}">{{ $active ? 'Hoạt động' : 'Tạm dừng' }}</span></td>
                        <td class="text-secondary small text-nowrap">{{ $portfolio->created_at->format('d/m/Y') }}<br>{{ $portfolio->created_at->format('H:i') }}</td>
                        <td class="text-end">
                            <div class="table-row-actions">
                                <a href="{{ route('admin.portfolios.show', $portfolio->id) }}" class="btn btn-sm btn-icon btn-ghost-secondary" title="Xem chi tiết"><i class="ti ti-eye"></i></a>
                                <form action="{{ route('admin.portfolios.toggle-status', $portfolio->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-icon btn-ghost-secondary" title="{{ $active ? 'Tạm dừng' : 'Kích hoạt' }}"><i class="ti ti-player-{{ $active ? 'pause' : 'play' }}"></i></button>
                                </form>
                                <form action="{{ route('admin.portfolios.destroy', $portfolio->id) }}" method="POST" class="d-inline"
                                      data-confirm="Bạn có chắc chắn muốn xóa portfolio “{{ $portfolio->name }}”? Toàn bộ cổ phiếu và giao dịch trong đó sẽ bị xóa." data-confirm-title="Xóa portfolio" data-confirm-ok="Xóa">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-icon btn-ghost-secondary" title="Xóa"><i class="ti ti-trash text-danger"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8">
                            <div class="empty">
                                <div class="empty-icon"><i class="ti ti-briefcase-off"></i></div>
                                <p class="empty-title">Chưa có portfolio nào</p>
                                <p class="empty-subtitle text-secondary">Các người dùng chưa tạo portfolio nào, hoặc bộ lọc không khớp.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($portfolios->hasPages())<div class="card-footer">{{ $portfolios->links() }}</div>@endif
    </div>
@endsection

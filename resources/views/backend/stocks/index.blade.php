@extends('layouts.admin')

@section('title', 'Quản lý Cổ phiếu')
@section('page_pretitle', 'Dữ liệu')
@section('page_title', 'Quản lý Cổ phiếu')

@section('breadcrumbs')
    <i class="ti ti-chevron-right"></i><span class="current">Cổ phiếu</span>
@endsection

@section('page_actions')
    <form action="{{ route('admin.stocks.update-prices') }}" method="POST" data-confirm="Cập nhật giá cho các mã cổ phiếu ngay bây giờ?" data-confirm-ok="Cập nhật" data-confirm-tone="primary" data-confirm-title="Cập nhật giá">
        @csrf
        <button type="submit" class="btn btn-outline-secondary"><i class="ti ti-refresh me-1"></i> Cập nhật giá</button>
    </form>
    <a href="{{ route('admin.stocks.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i> Thêm mã CP</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <form method="GET" action="{{ route('admin.stocks.index') }}" class="ad-toolbar flex-fill">
                <div class="input-icon" style="min-width:260px">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="search" name="search" class="form-control" placeholder="Mã CP hoặc tên công ty…" value="{{ request('search') }}" aria-label="Tìm cổ phiếu">
                </div>
                <select name="exchange" class="form-select" aria-label="Sàn giao dịch" style="max-width:170px">
                    <option value="">Tất cả sàn</option>
                    @foreach($exchanges ?? [] as $exchange)
                        <option value="{{ $exchange }}" {{ request('exchange') === $exchange ? 'selected' : '' }}>{{ $exchange }}</option>
                    @endforeach
                </select>
                <select name="status" class="form-select" aria-label="Trạng thái" style="max-width:170px">
                    <option value="">Mọi trạng thái</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Hoạt động</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Tạm dừng</option>
                </select>
                <button type="submit" class="btn btn-primary">Lọc</button>
                @if(request()->hasAny(['search', 'exchange', 'status']))
                    <a href="{{ route('admin.stocks.index') }}" class="btn btn-ghost-secondary"><i class="ti ti-x me-1"></i>Xóa lọc</a>
                @endif
            </form>
            <span class="text-secondary small">Tổng: {{ number_format($stocks->total() ?? 0) }} mã</span>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter table-hover card-table">
                <thead>
                    <tr>
                        <th>Mã CP</th>
                        <th>Tên công ty</th>
                        <th>Sàn</th>
                        <th>Ngành</th>
                        <th class="text-end">Giá hiện tại</th>
                        <th>Trạng thái</th>
                        <th>Cập nhật</th>
                        <th class="w-1 text-end">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stocks ?? [] as $stock)
                    <tr>
                        <td><span class="fw-bold text-primary">{{ $stock->symbol }}</span></td>
                        <td class="fw-medium" style="max-width:280px"><span class="d-block text-truncate" title="{{ $stock->name }}">{{ $stock->name ?? 'N/A' }}</span></td>
                        <td>
                            @php $exch = $stock->symbolInfo->exchange ?? null; @endphp
                            @if($exch)
                                <span class="badge bg-{{ $exch === 'HSX' ? 'red' : ($exch === 'HNX' ? 'blue' : 'green') }}-lt">{{ $exch }}</span>
                            @else
                                <span class="text-secondary">—</span>
                            @endif
                        </td>
                        <td class="text-secondary">{{ $stock->symbolInfo->industry ?? '—' }}</td>
                        <td class="text-end tabular-nums">
                            @if($stock->latestPrice)
                                <div class="fw-bold">{{ number_format($stock->latestPrice->close * 1000, 0, ',', '.') }} đ</div>
                                <div class="text-secondary small">{{ \Illuminate\Support\Str::of((string) $stock->latestPrice->date)->substr(0, 10) }}</div>
                            @else
                                <span class="text-secondary">N/A</span>
                            @endif
                        </td>
                        <td>
                            @if($stock->is_active ?? true)
                                <span class="ad-dot ok">Hoạt động</span>
                            @else
                                <span class="ad-dot warn">Tạm dừng</span>
                            @endif
                        </td>
                        <td class="text-secondary text-nowrap small">{{ $stock->updated_at->format('d/m/Y') }}<br>{{ $stock->updated_at->format('H:i') }}</td>
                        <td class="text-end">
                            <div class="table-row-actions">
                                <a href="{{ route('admin.stocks.show', $stock) }}" class="btn btn-sm btn-icon btn-ghost-secondary" title="Xem chi tiết"><i class="ti ti-eye"></i></a>
                                <a href="{{ route('admin.stocks.edit', $stock) }}" class="btn btn-sm btn-icon btn-ghost-secondary" title="Chỉnh sửa"><i class="ti ti-pencil"></i></a>
                                <form action="{{ route('admin.stocks.destroy', $stock) }}" method="POST" class="d-inline"
                                      data-confirm="Bạn có chắc chắn muốn xóa mã {{ $stock->symbol }}? Toàn bộ dữ liệu giá của mã này sẽ bị xóa." data-confirm-ok="Xóa mã" data-confirm-title="Xóa cổ phiếu">
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
                                <div class="empty-icon"><i class="ti ti-chart-candle"></i></div>
                                <p class="empty-title">Chưa có cổ phiếu nào</p>
                                <p class="empty-subtitle text-secondary">Nhấn “Thêm mã CP” để thêm cổ phiếu mới hoặc đổi bộ lọc.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(isset($stocks) && method_exists($stocks, 'links') && $stocks->hasPages())
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="text-secondary small">Hiển thị {{ $stocks->firstItem() }}–{{ $stocks->lastItem() }} / {{ number_format($stocks->total()) }}</span>
            {{ $stocks->appends(request()->query())->links() }}
        </div>
        @endif
    </div>
@endsection

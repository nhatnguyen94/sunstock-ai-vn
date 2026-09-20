@extends('layouts.admin')

@section('title', 'Chi tiết: {{ $stock->symbol }}')
@section('page_pretitle', 'Dữ liệu')
@section('page_title', '{{ $stock->symbol }} — {{ $stock->name ?? "N/A" }}')

@section('breadcrumbs')
    <i class="ti ti-chevron-right"></i><a href="{{ route('admin.stocks.index') }}">Cổ phiếu</a>
    <i class="ti ti-chevron-right"></i><span class="current">{{ $stock->symbol }}</span>
@endsection

@section('page_actions')
    <div class="btn-list">
        <a href="{{ route('admin.stocks.edit', $stock) }}" class="btn btn-warning">
            <i class="ti ti-pencil me-1"></i>
            Chỉnh sửa
        </a>
        <form action="{{ route('admin.stocks.destroy', $stock) }}" method="POST" class="d-inline"
              data-confirm="Xóa mã {{ $stock->symbol }}? Thao tác này không thể hoàn tác." data-confirm-title="Xác nhận xóa" data-confirm-ok="Xóa">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">
                <i class="ti ti-trash me-1"></i>
                Xóa
            </button>
        </form>
    </div>
@endsection

@section('content')
    <!-- Thông tin cơ bản -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Thông tin cơ bản</h3>
                </div>
                <div class="card-body">
                    <div class="datagrid">
                        <div class="datagrid-item">
                            <div class="datagrid-title">Mã cổ phiếu</div>
                            <div class="datagrid-content fw-bold text-primary fs-3">{{ $stock->symbol }}</div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">Tên công ty</div>
                            <div class="datagrid-content">{{ $stock->name ?? '—' }}</div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">Sàn giao dịch</div>
                            <div class="datagrid-content">
                                @php $exch = $stock->symbolInfo->exchange ?? null; @endphp
                                @if($exch)
                                    <span class="badge bg-{{ $exch === 'HSX' ? 'red' : ($exch === 'HNX' ? 'blue' : 'green') }}-lt">
                                        {{ $exch }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">Ngành nghề</div>
                            <div class="datagrid-content">{{ $stock->symbolInfo->industry ?? '—' }}</div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">Vốn hóa</div>
                            <div class="datagrid-content text-muted">Xem trong Tài chính</div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">Trạng thái</div>
                            <div class="datagrid-content">
                                @if($stock->is_active)
                                    <span class="badge bg-success-lt">Hoạt động</span>
                                @else
                                    <span class="badge bg-warning-lt">Tạm dừng</span>
                                @endif
                            </div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">Ngày tạo</div>
                            <div class="datagrid-content">{{ $stock->created_at->format('d/m/Y H:i') }}</div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">Cập nhật lần cuối</div>
                            <div class="datagrid-content">{{ $stock->updated_at->format('d/m/Y H:i') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Thống kê giá</h3>
                </div>
                <div class="card-body">
                    @php
                        $latestPrice = $stock->prices()->orderByDesc('date')->first();
                        $totalPriceRecords = $stock->prices()->count();
                        $oldestDate = $stock->prices()->orderBy('date')->value('date');
                    @endphp
                    <div class="datagrid">
                        <div class="datagrid-item">
                            <div class="datagrid-title">Giá đóng cửa gần nhất</div>
                            <div class="datagrid-content fw-bold">
                                {{ $latestPrice ? number_format($latestPrice->close, 0, ',', '.') . ' VND' : '—' }}
                            </div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">Ngày giao dịch gần nhất</div>
                            <div class="datagrid-content">
                                {{ $latestPrice ? \Carbon\Carbon::parse($latestPrice->date)->format('d/m/Y') : '—' }}
                            </div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">Tổng bản ghi giá</div>
                            <div class="datagrid-content">{{ number_format($totalPriceRecords) }}</div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">Dữ liệu từ ngày</div>
                            <div class="datagrid-content">
                                {{ $oldestDate ? \Carbon\Carbon::parse($oldestDate)->format('d/m/Y') : '—' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lịch sử giá gần đây -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Lịch sử giá (30 ngày gần nhất)</h3>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table table-sm">
                <thead>
                    <tr>
                        <th>Ngày</th>
                        <th class="text-end">Mở cửa</th>
                        <th class="text-end">Cao nhất</th>
                        <th class="text-end">Thấp nhất</th>
                        <th class="text-end">Đóng cửa</th>
                        <th class="text-end">Khối lượng</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stock->prices()->orderByDesc('date')->limit(30)->get() as $price)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($price->date)->format('d/m/Y') }}</td>
                        <td class="text-end">{{ number_format($price->open, 0, ',', '.') }}</td>
                        <td class="text-end text-success">{{ number_format($price->high, 0, ',', '.') }}</td>
                        <td class="text-end text-danger">{{ number_format($price->low, 0, ',', '.') }}</td>
                        <td class="text-end fw-bold">{{ number_format($price->close, 0, ',', '.') }}</td>
                        <td class="text-end text-muted">{{ number_format($price->volume) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-3">Chưa có dữ liệu giá</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@extends('layouts.admin')
@use('Carbon\Carbon')

@section('title', 'Trạng thái Sync')
@section('page_pretitle', 'Quản lý dữ liệu')
@section('page_title', 'Trạng thái Đồng bộ')

@section('breadcrumbs')
    <i class="ti ti-chevron-right"></i><span class="current">Sync Status</span>
@endsection

@section('page_actions')
    <button type="button" class="btn btn-outline-secondary" onclick="location.reload()"><i class="ti ti-reload me-1"></i> Tải lại</button>
@endsection

@section('content')
@php
    $iconMap = ['news' => 'ti-news', 'currency' => 'ti-currency-dollar', 'flame' => 'ti-flame', 'trending-up' => 'ti-trending-up', 'database' => 'ti-database',
                'report' => 'ti-report-analytics', 'building' => 'ti-building-skyscraper', 'chart-pie' => 'ti-chart-pie', 'chart-line' => 'ti-chart-line', 'chart-arrows' => 'ti-chart-arrows'];
    $colorMap = ['purple' => 'purple', 'green' => 'green', 'orange' => 'orange', 'blue' => 'blue', 'cyan' => 'cyan', 'yellow' => 'orange', 'pink' => 'purple', 'red' => 'red', 'indigo' => 'blue'];
    $ok = count($sources) - $late;
@endphp

<div class="alert alert-{{ $late === 0 ? 'success' : 'warning' }} d-flex align-items-center gap-2 mb-3" role="status">
    <i class="ti ti-{{ $late === 0 ? 'circle-check' : 'alert-triangle' }} fs-3"></i>
    <div>
        @if($late === 0)
            <strong>Tất cả {{ count($sources) }} nguồn</strong> đang đúng lịch.
        @else
            <strong>{{ $late }}/{{ count($sources) }} nguồn trễ hoặc chưa có dữ liệu</strong> — xem nhãn đỏ bên dưới rồi bấm <em>Sync ngay</em> ở nguồn đó.
        @endif
        <span class="text-secondary">“Trễ” = lần cập nhật cuối cũ hơn mức cho phép của từng nguồn (nguồn chạy theo giờ giao dịch được cho phép lâu hơn qua cuối tuần).</span>
    </div>
</div>
<div class="row row-cards g-3">
    @foreach($sources as $src)
    @php
        $lastSync = $src['last_sync'] ? Carbon::parse($src['last_sync']) : null;
        $state = $src['state'] === 'late' ? 'bad' : $src['state'];
        $stateText = ['off' => 'Chưa có dữ liệu', 'late' => 'Trễ', 'ok' => 'Đúng lịch'][$src['state']];
        $run = $src['last_run'];
        $tone = $colorMap[$src['color']] ?? 'blue';
    @endphp
    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-start gap-3 mb-3">
                    <span class="ad-stat-icon {{ $tone }}"><i class="ti {{ $iconMap[$src['icon']] ?? 'ti-refresh' }}"></i></span>
                    <div class="flex-fill min-w-0">
                        <h3 class="h4 mb-1">{{ $src['label'] }}</h3>
                        <span class="ad-dot {{ $state }}">{{ $stateText }}</span>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <div class="ad-stat-value" style="font-size:1.5rem">{{ number_format($src['row_count']) }}</div>
                        <div class="ad-stat-label">Bản ghi</div>
                    </div>
                    <div class="col-6">
                        <div class="fw-bold {{ $state === 'bad' ? 'text-danger' : ($state === 'ok' ? 'text-success' : 'text-secondary') }}" style="font-size:1.05rem;line-height:1.9rem" @if($lastSync) title="{{ $lastSync->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') }}" @endif>
                            {{ $lastSync ? $lastSync->diffForHumans() : 'Chưa có' }}
                        </div>
                        <div class="ad-stat-label">Lần sync cuối</div>
                    </div>
                </div>
                <p class="text-secondary small mb-2">{{ $src['description'] }}</p>
                <div class="small">
                    @if($run)
                        <i class="ti ti-{{ $run->ok ? 'circle-check text-success' : 'circle-x text-danger' }}"></i>
                        Lần chạy cuối {{ $run->ran_at->diffForHumans() }} — {{ $run->ok ? 'thành công' : 'THẤT BẠI' }}, {{ number_format($run->duration_ms / 1000, 1, ',', '.') }}s
                    @else
                        <span class="text-secondary"><i class="ti ti-clock-question"></i> Chưa có lần chạy nào được ghi lại</span>
                    @endif
                </div>
            </div>
            <div class="card-footer">
                <button class="btn btn-outline-primary w-100 btn-trigger" data-key="{{ $src['key'] }}" data-label="{{ $src['label'] }}">
                    <i class="ti ti-refresh me-1"></i> Sync ngay
                </button>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="card mt-3">
    <div class="card-header"><h3 class="card-title"><i class="ti ti-history me-2 text-primary"></i>Các lần chạy gần nhất</h3></div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr><th>Lệnh</th><th>Kết quả</th><th>Thời lượng</th><th>Lúc</th></tr></thead>
            <tbody>
            @forelse($recentRuns as $r)
                <tr>
                    <td><code>{{ $r->command }}</code></td>
                    <td><span class="ad-dot {{ $r->ok ? 'ok' : 'bad' }}">{{ $r->ok ? 'Thành công' : 'Thất bại' }}</span></td>
                    <td class="tabular-nums">{{ number_format($r->duration_ms / 1000, 1, ',', '.') }}s</td>
                    <td class="text-nowrap" title="{{ $r->ran_at->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i:s') }}">{{ $r->ran_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-secondary text-center py-4">Chưa ghi lại lần chạy nào — bảng này đầy dần khi các lệnh đồng bộ chạy theo lịch hoặc khi bấm “Sync ngay”.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.btn-trigger').forEach(btn => {
    const idle = btn.innerHTML;
    btn.addEventListener('click', async function () {
        const key = this.dataset.key;
        const label = this.dataset.label;
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang sync…';

        try {
            const res = await fetch(`/admin/sync-status/trigger/${encodeURIComponent(key)}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
            });
            const data = await res.json();
            window.adToast((data.success ? label + ': ' : '') + (data.message || data.error || 'Lỗi không xác định'), data.success ? 'success' : 'error');
            if (data.success) setTimeout(() => location.reload(), 1500);
        } catch (e) {
            window.adToast('Lỗi kết nối: ' + e.message, 'error');
        }

        this.disabled = false;
        this.innerHTML = idle;
    });
});
</script>
@endpush

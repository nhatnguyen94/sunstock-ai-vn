@extends('layouts.admin')

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
                'report' => 'ti-report-analytics', 'building' => 'ti-building-skyscraper', 'chart-pie' => 'ti-chart-pie', 'chart-line' => 'ti-chart-line'];
    $colorMap = ['purple' => 'purple', 'green' => 'green', 'orange' => 'orange', 'blue' => 'blue', 'cyan' => 'cyan', 'yellow' => 'orange', 'pink' => 'purple', 'red' => 'red', 'indigo' => 'blue'];
    $fresh = collect($sources)->filter(fn ($s) => $s['last_sync'] && \Carbon\Carbon::parse($s['last_sync'])->gte(now()->subDay()))->count();
@endphp

<div class="alert alert-{{ $fresh === count($sources) ? 'success' : 'warning' }} d-flex align-items-center gap-2 mb-3" role="status">
    <i class="ti ti-{{ $fresh === count($sources) ? 'circle-check' : 'alert-triangle' }} fs-3"></i>
    <div><strong>{{ $fresh }}/{{ count($sources) }} nguồn</strong> đã đồng bộ trong 24 giờ qua.
        @if($fresh < count($sources)) Các nguồn còn lại có thể cần chạy lại bằng nút <em>Sync ngay</em>. @endif</div>
</div>

<div class="row row-cards g-3">
    @foreach($sources as $src)
    @php
        $lastSync = $src['last_sync'] ? \Carbon\Carbon::parse($src['last_sync']) : null;
        $isStale = !$lastSync || $lastSync->lt(now()->subDay());
        $state = !$lastSync ? 'off' : ($isStale ? 'bad' : 'ok');
        $stateText = !$lastSync ? 'Chưa có dữ liệu' : ($isStale ? 'Cũ' : 'Mới');
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
                <p class="text-secondary small mb-0">{{ $src['description'] }}</p>
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

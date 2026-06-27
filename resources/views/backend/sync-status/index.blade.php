@extends('layouts.admin')

@section('title', 'Trạng thái Sync')
@section('page_pretitle', 'Quản lý dữ liệu')
@section('page_title', 'Trạng thái Đồng bộ')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Sync Status</li>
@endsection

@section('content')
<div class="row row-cards">
    @foreach($sources as $src)
    @php
        $lastSync  = $src['last_sync'] ? \Carbon\Carbon::parse($src['last_sync']) : null;
        $isStale   = !$lastSync || $lastSync->lt(now()->subDay());
        $statusColor = $isStale ? 'danger' : 'success';
        $statusText  = $isStale ? ($lastSync ? 'Cũ' : 'Chưa có dữ liệu') : 'Mới';
    @endphp
    <div class="col-md-6 col-xl-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    @include('backend.sync-status._icon', ['icon' => $src['icon'], 'color' => $src['color']])
                    {{ $src['label'] }}
                </h3>
                <div class="card-options">
                    <span class="badge bg-{{ $statusColor }}-lt text-{{ $statusColor }}">{{ $statusText }}</span>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-6 text-center">
                        <div class="h2 mb-0">{{ number_format($src['row_count']) }}</div>
                        <div class="text-muted small">Bản ghi</div>
                    </div>
                    <div class="col-6 text-center">
                        <div class="h4 mb-0 {{ $isStale ? 'text-danger' : 'text-success' }}">
                            {{ $lastSync ? $lastSync->diffForHumans() : 'Chưa có' }}
                        </div>
                        <div class="text-muted small">Lần cuối sync</div>
                    </div>
                </div>
                <p class="text-muted small mb-3">{{ $src['description'] }}</p>
                @if($lastSync)
                <p class="text-muted small mb-0">{{ $lastSync->format('d/m/Y H:i') }}</p>
                @endif
            </div>
            <div class="card-footer">
                <button class="btn btn-sm btn-outline-primary w-100 btn-trigger"
                        data-key="{{ $src['key'] }}"
                        data-label="{{ $src['label'] }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-1" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4"/><path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/></svg>
                    Sync ngay
                </button>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Result toast --}}
<div id="sync-toast" class="position-fixed bottom-0 end-0 p-3" style="z-index:9999; display:none;">
    <div class="alert mb-0" id="sync-toast-body" role="alert"></div>
</div>
@endsection

@push('styles')
<style>
.card .card-header .card-title svg { vertical-align: middle; margin-right: 6px; }
</style>
@endpush

@push('scripts')
<script>
document.querySelectorAll('.btn-trigger').forEach(btn => {
    btn.addEventListener('click', async function () {
        const key   = this.dataset.key;
        const label = this.dataset.label;
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang sync...';

        try {
            const res = await fetch(`/admin/sync-status/trigger/${encodeURIComponent(key)}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            });
            const data = await res.json();
            showToast(data.success ? 'success' : 'danger', data.message || data.error || 'Lỗi không xác định');
            if (data.success) setTimeout(() => location.reload(), 1500);
        } catch (e) {
            showToast('danger', 'Lỗi kết nối: ' + e.message);
        }

        this.disabled = false;
        this.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-1" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4"/><path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/></svg>Sync ngay';
    });
});

function showToast(type, msg) {
    const toast = document.getElementById('sync-toast');
    const body  = document.getElementById('sync-toast-body');
    body.className = `alert alert-${type} mb-0`;
    body.textContent = msg;
    toast.style.display = 'block';
    setTimeout(() => { toast.style.display = 'none'; }, 5000);
}
</script>
@endpush

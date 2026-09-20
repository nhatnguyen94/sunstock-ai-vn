@extends('layouts.admin')

@section('title', 'Giám sát Queue')
@section('page_pretitle', 'Hệ thống')
@section('page_title', 'Giám sát Queue')

@section('breadcrumbs')
    <i class="ti ti-chevron-right"></i><span class="current">Queue</span>
@endsection

@section('page_actions')
    <span class="ad-dot ok align-self-center" id="live-dot" title="Tự làm mới mỗi 5 giây">Trực tiếp · 5s</span>
@endsection

@section('content')
    <div class="row row-cards g-3 mb-3" id="queue-stats-row">
        @foreach($queues as $queue)
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title text-capitalize"><i class="ti ti-stack-2 me-2 text-primary"></i>Queue: {{ $queue['name'] }}</h3>
                </div>
                <div class="card-body">
                    <div class="row g-3 text-center" data-queue="{{ $queue['name'] }}">
                        <div class="col-4">
                            <div class="ad-stat-value text-blue" data-field="pending">{{ number_format($queue['pending']) }}</div>
                            <div class="ad-stat-label">Chờ xử lý</div>
                        </div>
                        <div class="col-4">
                            <div class="ad-stat-value text-orange" data-field="reserved">{{ number_format($queue['reserved']) }}</div>
                            <div class="ad-stat-label">Đang chạy</div>
                        </div>
                        <div class="col-4">
                            <div class="ad-stat-value text-secondary" data-field="delayed">{{ number_format($queue['delayed']) }}</div>
                            <div class="ad-stat-label">Hoãn (delayed)</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="row row-cards g-3 mb-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="card-title"><span class="status-dot status-dot-animated bg-orange me-2"></span>Đang xử lý (real-time)</h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead><tr><th>Job</th><th>Nội dung</th><th>Queue</th><th>Bắt đầu lúc</th><th>Đã chạy</th></tr></thead>
                        <tbody id="processing-tbody">
                            {{-- filled by JS on load + every poll, see renderProcessing() --}}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="card-title"><i class="ti ti-circle-check me-2 text-success"></i>Vừa xử lý xong</h3>
                    <div class="card-actions">
                        <span class="text-secondary small">Hôm nay: <strong id="processed-today-count">{{ number_format($activity['processedToday']) }}</strong> job</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead><tr><th>Job</th><th>Nội dung</th><th>Trạng thái</th><th>Lúc</th><th>Thời gian chạy</th></tr></thead>
                        <tbody id="recent-tbody">
                            {{-- filled by JS on load + every poll, see renderRecent() --}}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="ti ti-alert-triangle me-2 text-danger"></i>Job thất bại (Failed Jobs)</h3>
            <div class="card-actions d-flex align-items-center gap-2">
                <span class="badge bg-{{ $failedJobs->total() > 0 ? 'red' : 'green' }}-lt">{{ $failedJobs->total() }} job</span>
                @if($failedJobs->total() > 0)
                <button class="btn btn-sm btn-outline-success" id="btn-retry-all"><i class="ti ti-rotate me-1"></i>Retry tất cả</button>
                <button class="btn btn-sm btn-outline-danger" id="btn-delete-all"><i class="ti ti-trash me-1"></i>Xoá tất cả</button>
                @endif
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter table-hover card-table">
                <thead><tr><th>Job</th><th>Queue</th><th>Lỗi</th><th>Thời gian fail</th><th class="w-1 text-end">Thao tác</th></tr></thead>
                <tbody>
                    @forelse($failedJobs as $job)
                    <tr id="failed-job-{{ $job->uuid }}">
                        <td class="fw-semibold">{{ class_basename($job->job_class) }}</td>
                        <td><span class="badge bg-azure-lt">{{ $job->queue }}</span></td>
                        <td class="text-secondary small" style="max-width:420px"><span class="text-truncate-2" title="{{ $job->short_exception }}">{{ $job->short_exception }}</span></td>
                        <td class="text-secondary small text-nowrap">{{ \Carbon\Carbon::parse($job->failed_at)->format('d/m/Y H:i') }}</td>
                        <td class="text-end text-nowrap">
                            <button class="btn btn-sm btn-outline-success btn-retry" data-uuid="{{ $job->uuid }}"><i class="ti ti-rotate me-1"></i>Retry</button>
                            <button class="btn btn-sm btn-ghost-secondary btn-icon btn-delete" data-uuid="{{ $job->uuid }}" title="Xoá job này" aria-label="Xoá job này"><i class="ti ti-trash text-danger"></i></button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5">
                            <div class="empty"><div class="empty-icon"><i class="ti ti-circle-check text-success"></i></div>
                                <p class="empty-title">Không có job nào thất bại 🎉</p><p class="empty-subtitle text-secondary mb-0">Hàng đợi đang khỏe mạnh.</p></div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($failedJobs->hasPages())
        <div class="card-footer">{{ $failedJobs->links() }}</div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const toast = (ok, msg) => window.adToast(msg, ok ? 'success' : 'error');

function esc(str) {
    return (str ?? '').toString().replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function formatElapsed(seconds) {
    if (seconds < 60) return `${seconds}s`;
    const m = Math.floor(seconds / 60), s = seconds % 60;
    return `${m}m ${s}s`;
}

const emptyRow = (icon, text) => `<tr><td colspan="5"><div class="empty py-4"><div class="empty-icon" style="font-size:1.8rem"><i class="ti ${icon}"></i></div><p class="empty-subtitle text-secondary mb-0">${text}</p></div></td></tr>`;

function renderProcessing(rows) {
    const tbody = document.getElementById('processing-tbody');
    if (rows.length === 0) {
        tbody.innerHTML = emptyRow('ti-zzz', 'Không có job nào đang chạy');
        return;
    }
    tbody.innerHTML = rows.map(r => `
        <tr>
            <td class="fw-semibold">${esc(r.job_class)}</td>
            <td class="text-secondary small">${esc(r.summary) || '—'}</td>
            <td><span class="badge bg-azure-lt">${esc(r.queue)}</span></td>
            <td class="text-secondary small">${esc(r.started_at)}</td>
            <td><span class="badge bg-orange-lt">${formatElapsed(r.elapsed_seconds)}</span></td>
        </tr>
    `).join('');
}

function renderRecent(rows) {
    const tbody = document.getElementById('recent-tbody');
    if (rows.length === 0) {
        tbody.innerHTML = emptyRow('ti-history', 'Chưa có job nào xử lý xong');
        return;
    }
    tbody.innerHTML = rows.map(r => `
        <tr>
            <td class="fw-semibold">${esc(r.job_class)}</td>
            <td class="text-secondary small">${esc(r.summary) || '—'}</td>
            <td><span class="ad-dot ${r.status === 'completed' ? 'ok' : 'bad'}">${r.status === 'completed' ? 'Xong' : 'Fail'}</span></td>
            <td class="text-secondary small">${esc(r.finished_at)}</td>
            <td class="text-secondary small">${esc(r.duration_display)}</td>
        </tr>
    `).join('');
}

// Auto-refresh queue depth + currently-processing + recently-finished, every 5s, no full reload.
async function refreshQueueStats() {
    try {
        const res = await fetch(@json(route('admin.queue.stats')), { headers: { 'Accept': 'application/json' } });
        if (!res.ok) return;
        const data = await res.json();

        data.queues.forEach(q => {
            const row = document.querySelector(`[data-queue="${q.name}"]`);
            if (!row) return;
            row.querySelector('[data-field="pending"]').textContent = q.pending.toLocaleString();
            row.querySelector('[data-field="reserved"]').textContent = q.reserved.toLocaleString();
            row.querySelector('[data-field="delayed"]').textContent = q.delayed.toLocaleString();
        });

        renderProcessing(data.processing);
        renderRecent(data.recent);
        document.getElementById('processed-today-count').textContent = data.processedToday.toLocaleString();
    } catch (e) { /* silent — next poll will retry */ }
}
refreshQueueStats();
setInterval(() => { if (!document.hidden) refreshQueueStats(); }, 5000);

async function call(url, method) {
    const res = await fetch(url, { method, headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' } });
    return res.json();
}

document.querySelectorAll('.btn-retry').forEach(btn => {
    btn.addEventListener('click', async function () {
        const uuid = this.dataset.uuid;
        this.disabled = true;
        try {
            const data = await call(`/admin/queue/failed/${uuid}/retry`, 'POST');
            toast(data.success, data.message);
            if (data.success) document.getElementById(`failed-job-${uuid}`)?.remove();
        } catch (e) {
            toast(false, 'Lỗi kết nối: ' + e.message);
        }
        this.disabled = false;
    });
});

document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', async function () {
        if (!await window.adConfirm('Xoá job này khỏi danh sách fail?', { title: 'Xoá job', ok: 'Xoá' })) return;
        const uuid = this.dataset.uuid;
        this.disabled = true;
        try {
            const data = await call(`/admin/queue/failed/${uuid}`, 'DELETE');
            toast(data.success, data.message);
            if (data.success) document.getElementById(`failed-job-${uuid}`)?.remove();
        } catch (e) {
            toast(false, 'Lỗi kết nối: ' + e.message);
        }
        this.disabled = false;
    });
});

document.getElementById('btn-delete-all')?.addEventListener('click', async function () {
    if (!await window.adConfirm('Xoá VĨNH VIỄN toàn bộ job thất bại? Không thể hoàn tác.', { title: 'Xoá tất cả job thất bại', ok: 'Xoá tất cả' })) return;
    this.disabled = true;
    try {
        const data = await call(@json(route('admin.queue.destroy-all')), 'DELETE');
        toast(data.success, data.message);
        if (data.success) setTimeout(() => location.reload(), 1500);
    } catch (e) {
        toast(false, 'Lỗi kết nối: ' + e.message);
    }
    this.disabled = false;
});

document.getElementById('btn-retry-all')?.addEventListener('click', async function () {
    if (!await window.adConfirm('Retry toàn bộ job đang fail?', { title: 'Retry tất cả', ok: 'Retry', tone: 'primary' })) return;
    this.disabled = true;
    try {
        const data = await call(@json(route('admin.queue.retry-all')), 'POST');
        toast(data.success, data.message);
        if (data.success) setTimeout(() => location.reload(), 1500);
    } catch (e) {
        toast(false, 'Lỗi kết nối: ' + e.message);
    }
    this.disabled = false;
});
</script>
@endpush

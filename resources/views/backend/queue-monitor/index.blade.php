@extends('layouts.admin')

@section('title', 'Giám sát Queue')
@section('page_pretitle', 'Quản lý dữ liệu')
@section('page_title', 'Giám sát Queue')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Queue</li>
@endsection

@section('content')
    <div class="row row-cards mb-4" id="queue-stats-row">
        @foreach($queues as $queue)
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title text-capitalize">Queue: {{ $queue['name'] }}</h3>
                </div>
                <div class="card-body">
                    <div class="row g-3 text-center" data-queue="{{ $queue['name'] }}">
                        <div class="col-4">
                            <div class="h2 mb-0 text-blue" data-field="pending">{{ number_format($queue['pending']) }}</div>
                            <div class="text-muted small">Chờ xử lý</div>
                        </div>
                        <div class="col-4">
                            <div class="h2 mb-0 text-orange" data-field="reserved">{{ number_format($queue['reserved']) }}</div>
                            <div class="text-muted small">Đang chạy</div>
                        </div>
                        <div class="col-4">
                            <div class="h2 mb-0 text-muted" data-field="delayed">{{ number_format($queue['delayed']) }}</div>
                            <div class="text-muted small">Hoãn (delayed)</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="row row-cards mb-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <span class="status-dot status-dot-animated bg-orange me-2"></span>
                        Đang xử lý (real-time)
                    </h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Job</th>
                                <th>Nội dung</th>
                                <th>Queue</th>
                                <th>Bắt đầu lúc</th>
                                <th>Đã chạy</th>
                            </tr>
                        </thead>
                        <tbody id="processing-tbody">
                            {{-- filled by JS on load + every poll, see renderProcessing() --}}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Vừa xử lý xong</h3>
                    <div class="card-actions">
                        <span class="text-muted small">Hôm nay: <strong id="processed-today-count">{{ number_format($activity['processedToday']) }}</strong> job</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Job</th>
                                <th>Nội dung</th>
                                <th>Trạng thái</th>
                                <th>Lúc</th>
                                <th>Thời gian chạy</th>
                            </tr>
                        </thead>
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
            <h3 class="card-title">Job thất bại (Failed Jobs)</h3>
            <div class="card-actions">
                <span class="text-muted me-3">{{ $failedJobs->total() }} job</span>
                @if($failedJobs->total() > 0)
                <button class="btn btn-sm btn-outline-success" id="btn-retry-all">Retry tất cả</button>
                <button class="btn btn-sm btn-outline-danger ms-1" id="btn-delete-all">Xoá tất cả</button>
                @endif
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Job</th>
                        <th>Queue</th>
                        <th>Lỗi</th>
                        <th>Thời gian fail</th>
                        <th class="w-1">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($failedJobs as $job)
                    <tr id="failed-job-{{ $job->uuid }}">
                        <td class="font-weight-medium">{{ class_basename($job->job_class) }}</td>
                        <td><span class="badge bg-azure-lt">{{ $job->queue }}</span></td>
                        <td class="text-muted small">{{ $job->short_exception }}</td>
                        <td class="text-muted small">{{ \Carbon\Carbon::parse($job->failed_at)->format('d/m/Y H:i') }}</td>
                        <td>
                            <div class="btn-group" role="group">
                                <button class="btn btn-sm btn-outline-success btn-retry" data-uuid="{{ $job->uuid }}">
                                    Retry
                                </button>
                                <button class="btn btn-sm btn-outline-danger btn-delete" data-uuid="{{ $job->uuid }}">
                                    Xoá
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">Không có job nào thất bại 🎉</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($failedJobs->hasPages())
        <div class="card-footer">
            {{ $failedJobs->links() }}
        </div>
        @endif
    </div>

    {{-- Result toast --}}
    <div id="queue-toast" class="position-fixed bottom-0 end-0 p-3" style="z-index:9999; display:none;">
        <div class="alert mb-0" id="queue-toast-body" role="alert"></div>
    </div>
@endsection

@push('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

function showToast(type, msg) {
    const toast = document.getElementById('queue-toast');
    const body  = document.getElementById('queue-toast-body');
    body.className = `alert alert-${type} mb-0`;
    body.textContent = msg;
    toast.style.display = 'block';
    setTimeout(() => { toast.style.display = 'none'; }, 5000);
}

function esc(str) {
    return (str ?? '').toString().replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function formatElapsed(seconds) {
    if (seconds < 60) return `${seconds}s`;
    const m = Math.floor(seconds / 60), s = seconds % 60;
    return `${m}m ${s}s`;
}

function renderProcessing(rows) {
    const tbody = document.getElementById('processing-tbody');
    if (rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Không có job nào đang chạy</td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => `
        <tr>
            <td class="font-weight-medium">${esc(r.job_class)}</td>
            <td class="text-muted small">${esc(r.summary) || '—'}</td>
            <td><span class="badge bg-azure-lt">${esc(r.queue)}</span></td>
            <td class="text-muted small">${esc(r.started_at)}</td>
            <td><span class="badge bg-orange-lt">${formatElapsed(r.elapsed_seconds)}</span></td>
        </tr>
    `).join('');
}

function renderRecent(rows) {
    const tbody = document.getElementById('recent-tbody');
    if (rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Chưa có job nào xử lý xong</td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => `
        <tr>
            <td class="font-weight-medium">${esc(r.job_class)}</td>
            <td class="text-muted small">${esc(r.summary) || '—'}</td>
            <td><span class="badge bg-${r.status === 'completed' ? 'green' : 'red'}-lt">${r.status === 'completed' ? 'Xong' : 'Fail'}</span></td>
            <td class="text-muted small">${esc(r.finished_at)}</td>
            <td class="text-muted small">${esc(r.duration_display)}</td>
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
setInterval(refreshQueueStats, 5000);

document.querySelectorAll('.btn-retry').forEach(btn => {
    btn.addEventListener('click', async function () {
        const uuid = this.dataset.uuid;
        this.disabled = true;
        try {
            const res = await fetch(`/admin/queue/failed/${uuid}/retry`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            });
            const data = await res.json();
            showToast(data.success ? 'success' : 'danger', data.message);
            if (data.success) document.getElementById(`failed-job-${uuid}`)?.remove();
        } catch (e) {
            showToast('danger', 'Lỗi kết nối: ' + e.message);
        }
        this.disabled = false;
    });
});

document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', async function () {
        if (!confirm('Xoá job này khỏi danh sách fail?')) return;
        const uuid = this.dataset.uuid;
        this.disabled = true;
        try {
            const res = await fetch(`/admin/queue/failed/${uuid}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            });
            const data = await res.json();
            showToast(data.success ? 'success' : 'danger', data.message);
            if (data.success) document.getElementById(`failed-job-${uuid}`)?.remove();
        } catch (e) {
            showToast('danger', 'Lỗi kết nối: ' + e.message);
        }
        this.disabled = false;
    });
});

document.getElementById('btn-delete-all')?.addEventListener('click', async function () {
    if (!confirm('Xoá VĨNH VIỄN toàn bộ job thất bại? Không thể hoàn tác.')) return;
    this.disabled = true;
    try {
        const res = await fetch(@json(route('admin.queue.destroy-all')), {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        });
        const data = await res.json();
        showToast(data.success ? 'success' : 'danger', data.message);
        if (data.success) setTimeout(() => location.reload(), 1500);
    } catch (e) {
        showToast('danger', 'Lỗi kết nối: ' + e.message);
    }
    this.disabled = false;
});

document.getElementById('btn-retry-all')?.addEventListener('click', async function () {
    if (!confirm('Retry toàn bộ job đang fail?')) return;
    this.disabled = true;
    try {
        const res = await fetch(@json(route('admin.queue.retry-all')), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        });
        const data = await res.json();
        showToast(data.success ? 'success' : 'danger', data.message);
        if (data.success) setTimeout(() => location.reload(), 1500);
    } catch (e) {
        showToast('danger', 'Lỗi kết nối: ' + e.message);
    }
    this.disabled = false;
});
</script>
@endpush

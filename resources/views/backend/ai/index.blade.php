@extends('layouts.admin')
@use('Illuminate\Support\Carbon')

@section('title', 'Quản lý AI')
@section('page_pretitle', 'Nội dung & dữ liệu')
@section('page_title', 'Quản lý AI')

@section('breadcrumbs')
    <i class="ti ti-chevron-right"></i><span class="current">Quản lý AI</span>
@endsection

@section('page_actions')
    <button type="button" class="btn btn-outline-secondary" onclick="location.reload()"><i class="ti ti-reload me-1"></i> Tải lại</button>
@endsection

@section('content')
@php
    $statusTone = ['ok' => 'ok', 'error' => 'bad', 'refused' => 'warn'];
    $statusText = ['ok' => 'Thành công', 'error' => 'Lỗi', 'refused' => 'Từ chối'];
    $kindText = ['chat' => 'Hỏi đáp', 'predict' => 'Dự đoán tuần'];
@endphp

@if(! $settings['enabled'])
    <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
        <i class="ti ti-player-pause fs-3"></i>
        <div><strong>AI đang bị tắt.</strong> Mọi lượt hỏi và dự đoán đều bị từ chối cho tới khi bật lại ở khung “Cài đặt” bên dưới.</div>
    </div>
@endif
@unless($overview['key_set'])
    <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
        <i class="ti ti-key-off fs-3"></i>
        <div>Chưa cấu hình <code>GROQ_API_KEY</code> trong <code>.env</code> nên mọi lượt gọi sẽ lỗi.</div>
    </div>
@endunless

<div class="row row-deck row-cards g-3 mb-3">
    <div class="col-sm-6 col-xl-3"><div class="card"><div class="ad-stat">
        <span class="ad-stat-icon blue"><i class="ti ti-message-chatbot"></i></span>
        <div class="ad-stat-body"><div class="ad-stat-label">Lượt gọi hôm nay</div><div class="ad-stat-value">{{ number_format($overview['today']) }}</div>
            <div class="ad-stat-sub">{{ number_format($overview['week']) }} trong 7 ngày</div></div>
    </div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card"><div class="ad-stat">
        <span class="ad-stat-icon {{ ($overview['success_rate'] ?? 100) >= 90 ? 'green' : 'orange' }}"><i class="ti ti-circle-check"></i></span>
        <div class="ad-stat-body"><div class="ad-stat-label">Tỉ lệ thành công · 7 ngày</div>
            <div class="ad-stat-value">{{ $overview['success_rate'] === null ? '—' : number_format($overview['success_rate'], 1, ',', '.').'%' }}</div>
            <div class="ad-stat-sub">{{ number_format($overview['week_error']) }} lỗi · {{ number_format($overview['week_refused']) }} bị từ chối</div></div>
    </div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card"><div class="ad-stat">
        <span class="ad-stat-icon purple"><i class="ti ti-stopwatch"></i></span>
        <div class="ad-stat-body"><div class="ad-stat-label">Thời gian trả lời trung bình</div>
            <div class="ad-stat-value">{{ $overview['avg_ms'] ? number_format($overview['avg_ms'] / 1000, 1, ',', '.').'s' : '—' }}</div>
            <div class="ad-stat-sub">chỉ tính lượt thành công</div></div>
    </div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card"><div class="ad-stat">
        <span class="ad-stat-icon orange"><i class="ti ti-users"></i></span>
        <div class="ad-stat-body"><div class="ad-stat-label">Người dùng AI · 7 ngày</div><div class="ad-stat-value">{{ number_format($overview['users_7d']) }}</div>
            <div class="ad-stat-sub">{{ number_format($overview['blocked_users']) }} tài khoản bị khóa AI</div></div>
    </div></div></div>
</div>

<div class="row row-deck row-cards g-3 mb-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-adjustments me-2 text-primary"></i>Cài đặt</h3></div>
            <form method="POST" action="{{ route('admin.ai.settings') }}">
                @csrf
                <div class="card-body">
                    <label class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="enabled" value="1" @checked($settings['enabled'])>
                        <span class="form-check-label"><strong>Bật tính năng AI</strong><br><span class="text-secondary small">Tắt là công tắc khẩn cấp: không còn lượt nào gọi ra Groq (tránh tốn phí khi bị lạm dụng).</span></span>
                    </label>
                    <div class="mb-0">
                        <label class="form-label" for="aiDailyLimit">Hạn mức mỗi tài khoản mỗi ngày</label>
                        <input id="aiDailyLimit" type="number" min="0" max="1000" name="daily_limit" class="form-control @error('daily_limit') is-invalid @enderror" value="{{ old('daily_limit', $settings['daily_limit']) }}">
                        @error('daily_limit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-hint">0 = không giới hạn theo ngày. Chạy cùng giới hạn theo phút có sẵn (1 dự đoán / {{ config('ai_limits.predict.interval_minutes') }} phút, {{ config('ai_limits.chat.max_questions') }} câu / {{ config('ai_limits.chat.window_minutes') }} phút). Lượt bị từ chối không tính vào hạn mức.</div>
                    </div>
                </div>
                <div class="card-footer text-end"><button class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i> Lưu cài đặt</button></div>
            </form>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-cpu me-2 text-primary"></i>Model</h3></div>
            <div class="card-body pb-2">
                <div class="text-secondary small mb-2">Thứ tự thử (model đầu bị lỗi thì sang model kế tiếp), đặt bằng <code>GROQ_MODELS</code> trong <code>.env</code>:</div>
                <div class="mb-3">
                    @foreach($overview['models'] as $m)<span class="badge bg-blue-lt me-1 mb-1">{{ $loop->iteration }}. {{ $m }}</span>@endforeach
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>Model đã trả lời · 7 ngày</th><th class="text-end">Lượt</th><th class="text-end">Trung bình</th><th>Gần nhất</th></tr></thead>
                    <tbody>
                    @forelse($byModel as $row)
                        <tr>
                            <td>@if($row->model)<code>{{ $row->model }}</code>@else<span class="text-secondary">cache (dự đoán tuần đã có)</span>@endif</td>
                            <td class="text-end tabular-nums">{{ number_format($row->answers) }}</td>
                            <td class="text-end tabular-nums">{{ number_format($row->avg_ms / 1000, 1, ',', '.') }}s</td>
                            <td class="text-nowrap">{{ Carbon::parse($row->last_at)->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-secondary text-center py-3">Chưa có lượt thành công nào trong 7 ngày.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h3 class="card-title"><i class="ti ti-user-bolt me-2 text-primary"></i>Dùng nhiều nhất · 7 ngày</h3></div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr><th>Tài khoản</th><th class="text-end">Tổng</th><th class="text-end">Hôm nay</th><th class="text-end">Lỗi</th><th class="text-end">Bị từ chối</th><th class="text-end"></th></tr></thead>
            <tbody>
            @forelse($topUsers as $row)
                <tr>
                    <td>
                        @if($canSeePeople)<div class="fw-semibold">{{ $row->user->name }}</div><div class="text-secondary small">{{ $row->user->email }}</div>
                        @else<div class="fw-semibold">Tài khoản #{{ $row->user->id }}</div>@endif
                    </td>
                    <td class="text-end tabular-nums">{{ number_format($row->total) }}</td>
                    <td class="text-end tabular-nums">{{ number_format($row->today) }}@if($settings['daily_limit'] > 0) <span class="text-secondary">/ {{ $settings['daily_limit'] }}</span>@endif</td>
                    <td class="text-end tabular-nums">{{ number_format($row->errors) }}</td>
                    <td class="text-end tabular-nums">{{ number_format($row->refused) }}</td>
                    <td class="text-end">
                        <form method="POST" action="{{ route('admin.ai.users.toggle-block', $row->user) }}" class="d-inline">
                            @csrf
                            @if($row->user->ai_blocked_at)
                                <button class="btn btn-sm btn-outline-success"><i class="ti ti-lock-open me-1"></i> Mở khóa AI</button>
                            @else
                                <button class="btn btn-sm btn-outline-danger"><i class="ti ti-lock me-1"></i> Khóa AI</button>
                            @endif
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-secondary text-center py-4">Chưa có ai dùng AI trong 7 ngày qua.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title"><i class="ti ti-history me-2 text-primary"></i>Các lượt gọi gần nhất</h3></div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr><th>Lúc</th><th>Tài khoản</th><th>Loại</th><th>Kết quả</th><th>Model</th><th class="text-end">Thời gian</th><th>Câu hỏi</th></tr></thead>
            <tbody>
            @forelse($recent as $r)
                <tr>
                    <td class="text-nowrap" title="{{ $r->created_at->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i:s') }}">{{ $r->created_at->diffForHumans() }}</td>
                    <td class="text-nowrap">@if(! $r->user)<span class="text-secondary">—</span>@elseif($canSeePeople){{ $r->user->name }}@else #{{ $r->user->id }}@endif</td>
                    <td>{{ $kindText[$r->kind] ?? $r->kind }}</td>
                    <td><span class="ad-dot {{ $statusTone[$r->status] ?? 'off' }}">{{ $statusText[$r->status] ?? $r->status }}</span></td>
                    <td>@if($r->model)<code>{{ $r->model }}</code>@else<span class="text-secondary">—</span>@endif</td>
                    <td class="text-end tabular-nums">{{ number_format($r->duration_ms / 1000, 1, ',', '.') }}s</td>
                    <td class="text-truncate" style="max-width:22rem">{{ $r->question ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-secondary text-center py-4">Chưa có lượt gọi nào được ghi lại — bảng này đầy dần khi người dùng hỏi AI.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@extends('layouts.admin')
@use('Illuminate\Support\Carbon')

@section('title', 'Bảo mật')
@section('page_pretitle', 'Hệ thống')
@section('page_title', 'Bảo mật')

@section('breadcrumbs')
    <i class="ti ti-chevron-right"></i><span class="current">Bảo mật</span>
@endsection

@section('page_actions')
    <button type="button" class="btn btn-outline-secondary" onclick="location.reload()"><i class="ti ti-reload me-1"></i> Tải lại</button>
@endsection

@section('content')
<div class="row row-deck row-cards g-3 mb-3">
    <div class="col-sm-6 col-xl-3"><div class="card"><div class="ad-stat">
        <span class="ad-stat-icon green"><i class="ti ti-login"></i></span>
        <div class="ad-stat-body"><div class="ad-stat-label">Đăng nhập thành công · 24h</div><div class="ad-stat-value">{{ number_format($overview['ok_24h']) }}</div></div>
    </div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card"><div class="ad-stat">
        <span class="ad-stat-icon {{ $overview['failed_24h'] > 0 ? 'orange' : 'green' }}"><i class="ti ti-shield-x"></i></span>
        <div class="ad-stat-body"><div class="ad-stat-label">Đăng nhập sai · 24h</div><div class="ad-stat-value">{{ number_format($overview['failed_24h']) }}</div>
            <div class="ad-stat-sub">từ {{ number_format($overview['ips_failed_24h']) }} địa chỉ IP</div></div>
    </div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card"><div class="ad-stat">
        <span class="ad-stat-icon {{ $overview['suspicious'] > 0 ? 'red' : 'green' }}"><i class="ti ti-alert-triangle"></i></span>
        <div class="ad-stat-body"><div class="ad-stat-label">IP nghi dò mật khẩu</div><div class="ad-stat-value">{{ number_format($overview['suspicious']) }}</div>
            <div class="ad-stat-sub">≥ {{ $threshold }} lần sai trong 1 giờ</div></div>
    </div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card"><div class="ad-stat">
        <span class="ad-stat-icon purple"><i class="ti ti-ban"></i></span>
        <div class="ad-stat-body"><div class="ad-stat-label">IP đang bị chặn</div><div class="ad-stat-value">{{ number_format($overview['blocked']) }}</div></div>
    </div></div></div>
</div>

<div class="row row-deck row-cards g-3 mb-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-alert-triangle me-2 text-danger"></i>Địa chỉ nghi dò mật khẩu · 1 giờ qua</h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>IP</th><th class="text-end">Lần sai</th><th class="text-end">E-mail đã thử</th><th>Gần nhất</th><th></th></tr></thead>
                    <tbody>
                    @forelse($suspicious as $row)
                        <tr>
                            <td><code>{{ $row->ip }}</code>@if($row->ip === $myIp) <span class="badge bg-blue-lt">bạn</span>@endif</td>
                            <td class="text-end tabular-nums">{{ number_format($row->failures) }}</td>
                            <td class="text-end tabular-nums">{{ number_format($row->emails) }}</td>
                            <td class="text-nowrap">{{ Carbon::parse($row->last_at)->diffForHumans() }}</td>
                            <td class="text-end">
                                @if($canChange)
                                    <form method="POST" action="{{ route('admin.security.block') }}" class="d-inline">@csrf
                                        <input type="hidden" name="ip" value="{{ $row->ip }}"><input type="hidden" name="reason" value="Nhiều lần đăng nhập sai ({{ $row->failures }} lần / giờ)">
                                        <button class="btn btn-sm btn-outline-danger"><i class="ti ti-ban me-1"></i> Chặn</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-secondary text-center py-4"><i class="ti ti-circle-check text-success"></i> Không có địa chỉ nào đáng ngờ.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-ban me-2 text-primary"></i>IP đã chặn</h3></div>
            @if($canChange)
            <form method="POST" action="{{ route('admin.security.block') }}" class="card-body border-bottom">
                @csrf
                <div class="row g-2">
                    <div class="col-5"><input name="ip" class="form-control" placeholder="203.0.113.7" required maxlength="45" aria-label="Địa chỉ IP"></div>
                    <div class="col-5"><input name="reason" class="form-control" placeholder="Lý do" maxlength="200" aria-label="Lý do"></div>
                    <div class="col-2"><button class="btn btn-primary w-100">Chặn</button></div>
                </div>
                <div class="form-hint mt-2">Không chặn được địa chỉ nội bộ, và không chặn chính bạn (<code>{{ $myIp }}</code>).</div>
            </form>
            @endif
            <div class="list-group list-group-flush">
                @forelse($blocked as $b)
                    <div class="list-group-item d-flex align-items-center gap-3">
                        <div class="flex-fill min-w-0"><div><code>{{ $b->ip }}</code></div><div class="text-secondary small text-truncate">{{ $b->reason ?: 'không ghi lý do' }} · {{ $b->created_at->diffForHumans() }}</div></div>
                        @if($canChange)
                            <form method="POST" action="{{ route('admin.security.unblock', $b) }}">@csrf @method('DELETE') <button class="btn btn-sm btn-outline-secondary">Bỏ chặn</button></form>
                        @endif
                    </div>
                @empty
                    <div class="list-group-item text-secondary text-center py-4">Chưa chặn địa chỉ nào.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="ti ti-history me-2 text-primary"></i>Lịch sử đăng nhập</h3>
        <div class="card-actions btn-group">
            <a href="{{ route('admin.security.index') }}" class="btn btn-sm {{ $failedOnly ? 'btn-outline-secondary' : 'btn-primary' }}">Tất cả</a>
            <a href="{{ route('admin.security.index', ['failed' => 1]) }}" class="btn btn-sm {{ $failedOnly ? 'btn-primary' : 'btn-outline-secondary' }}">Chỉ lần sai</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr><th>Lúc</th><th>Kết quả</th><th>E-mail đã nhập</th><th>Cổng</th><th>IP</th><th>Trình duyệt</th></tr></thead>
            <tbody>
            @forelse($recent as $r)
                <tr>
                    <td class="text-nowrap" title="{{ $r->created_at->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i:s') }}">{{ $r->created_at->diffForHumans() }}</td>
                    <td><span class="ad-dot {{ $r->success ? 'ok' : 'bad' }}">{{ $r->success ? 'Thành công' : 'Sai' }}</span></td>
                    <td class="text-truncate" style="max-width:15rem">{{ $r->email ?? '—' }}</td>
                    <td>{{ $r->door === 'admin' ? 'Admin' : 'Website' }}</td>
                    <td><code>{{ $r->ip ?? '—' }}</code></td>
                    <td class="text-secondary text-truncate" style="max-width:18rem" title="{{ $r->user_agent }}">{{ $r->user_agent ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-secondary text-center py-4">Chưa có lần đăng nhập nào được ghi lại.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

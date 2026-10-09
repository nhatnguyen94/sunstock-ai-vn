@extends('layouts.admin')

@section('title', 'Sức khỏe hệ thống')
@section('page_pretitle', 'Hệ thống')
@section('page_title', 'Sức khỏe hệ thống')

@section('breadcrumbs')
    <i class="ti ti-chevron-right"></i><span class="current">Sức khỏe hệ thống</span>
@endsection

@section('page_actions')
    <button type="button" class="btn btn-outline-secondary" onclick="location.reload()"><i class="ti ti-reload me-1"></i> Tải lại</button>
@endsection

@section('content')
@php
    $size = fn (int $b) => $b >= 1073741824 ? number_format($b / 1073741824, 2, ',', '.').' GB' : ($b >= 1048576 ? number_format($b / 1048576, 1, ',', '.').' MB' : number_format($b / 1024, 0, ',', '.').' KB');
    $beatText = ['ok' => 'Đang chạy', 'warn' => 'Chậm', 'bad' => 'Đã ngừng', 'off' => 'Chưa thấy nhịp nào'];
@endphp

<div class="row row-deck row-cards g-3 mb-3">
    <div class="col-sm-6 col-xl-3"><div class="card"><div class="ad-stat">
        <span class="ad-stat-icon {{ ['ok' => 'green', 'warn' => 'orange', 'bad' => 'red', 'off' => 'red'][$scheduler['state']] }}"><i class="ti ti-heartbeat"></i></span>
        <div class="ad-stat-body"><div class="ad-stat-label">Scheduler</div>
            <div class="ad-stat-value" style="font-size:1.35rem"><span class="ad-dot {{ $scheduler['state'] === 'warn' ? 'warn' : ($scheduler['state'] === 'ok' ? 'ok' : ($scheduler['state'] === 'off' ? 'off' : 'bad')) }}">{{ $beatText[$scheduler['state']] }}</span></div>
            <div class="ad-stat-sub">@if($scheduler['last'])nhịp cuối {{ $scheduler['last']->diffForHumans() }}@else chạy <code>schedule:work</code> hoặc cron @endif</div></div>
    </div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card"><div class="ad-stat">
        <span class="ad-stat-icon {{ $disk && $disk['free_percent'] < 10 ? 'red' : 'blue' }}"><i class="ti ti-device-floppy"></i></span>
        <div class="ad-stat-body"><div class="ad-stat-label">Ổ đĩa còn trống</div>
            <div class="ad-stat-value">{{ $disk ? $disk['free_percent'].'%' : '—' }}</div>
            <div class="ad-stat-sub">@if($disk){{ $size($disk['free']) }} / {{ $size($disk['total']) }}@else không đọc được @endif</div></div>
    </div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card"><div class="ad-stat">
        <span class="ad-stat-icon {{ $backupAge === null ? 'red' : ($backupAge > 14 ? 'orange' : 'green') }}"><i class="ti ti-database-export"></i></span>
        <div class="ad-stat-body"><div class="ad-stat-label">Sao lưu gần nhất</div>
            <div class="ad-stat-value">{{ $backupAge === null ? '—' : ($backupAge === 0 ? 'Hôm nay' : $backupAge.' ngày') }}</div>
            <div class="ad-stat-sub">{{ count($backups) }} bản được tìm thấy</div></div>
    </div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card"><div class="ad-stat">
        <span class="ad-stat-icon {{ $env['debug'] && $env['env'] === 'production' ? 'red' : 'purple' }}"><i class="ti ti-server"></i></span>
        <div class="ad-stat-body"><div class="ad-stat-label">Môi trường</div>
            <div class="ad-stat-value" style="font-size:1.35rem">{{ $env['env'] }}{{ $env['debug'] ? ' · debug' : '' }}</div>
            <div class="ad-stat-sub">PHP {{ $env['php'] }} · Laravel {{ $env['laravel'] }}</div></div>
    </div></div></div>
</div>

<div class="row row-deck row-cards g-3 mb-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-calendar-time me-2 text-primary"></i>Lịch chạy</h3><span class="text-secondary small">{{ count($schedule) }} tác vụ</span></div>
            <div class="table-responsive" style="max-height:26rem">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>Tác vụ</th><th>Lịch (cron)</th><th>Lần tới</th></tr></thead>
                    <tbody>
                    @foreach($schedule as $row)
                        <tr>
                            <td><code>{{ $row['task'] }}</code></td>
                            <td class="text-secondary tabular-nums">{{ $row['expression'] }}</td>
                            <td class="text-nowrap">@if($row['next'])<span title="{{ $row['next']->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') }}">{{ $row['next']->diffForHumans() }}</span>@else — @endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-database-export me-2 text-primary"></i>Sao lưu database</h3>
                @if($isAdmin)
                    <form method="POST" action="{{ route('admin.health.backup') }}" class="ms-auto">@csrf <button class="btn btn-sm btn-primary"><i class="ti ti-plus me-1"></i> Tạo ngay</button></form>
                @endif
            </div>
            <div class="list-group list-group-flush">
                @forelse($backups as $b)
                    <div class="list-group-item d-flex align-items-center gap-3">
                        <div class="flex-fill min-w-0"><div class="fw-semibold text-truncate">{{ $b['name'] }}</div><div class="text-secondary small">{{ date('d/m/Y H:i', $b['modified']) }} · {{ $size($b['size']) }}</div></div>
                        @if($isAdmin)<a href="{{ route('admin.health.backup.download', $b['id']) }}" class="btn btn-sm btn-outline-secondary"><i class="ti ti-download me-1"></i> Tải</a>@endif
                    </div>
                @empty
                    <div class="list-group-item text-secondary text-center py-4">Chưa có bản sao lưu nào. Lệnh <code>db:backup</code> tự chạy mỗi tuần.</div>
                @endforelse
            </div>
            <div class="card-footer text-secondary small">Bản sao lưu chứa mọi tài khoản và mã băm mật khẩu: chỉ vai trò admin được tạo và tải.</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title"><i class="ti ti-settings me-2 text-primary"></i>Cấu hình đang chạy</h3></div>
    <div class="card-body">
        <div class="row g-3">
            @foreach(['cache' => 'Cache', 'queue' => 'Hàng đợi', 'session' => 'Phiên đăng nhập'] as $key => $label)
                <div class="col-sm-4"><div class="ad-stat-label">{{ $label }}</div><div class="fw-semibold">{{ $env[$key] }}</div></div>
            @endforeach
        </div>
    </div>
</div>
@endsection

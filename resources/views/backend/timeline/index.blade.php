@extends('layouts.admin')

@section('title', 'Timeline Hệ thống')
@section('page_pretitle', 'Theo dõi hoạt động thực tế')
@section('page_title', 'Timeline Hệ thống')

@section('breadcrumbs')
    <i class="ti ti-chevron-right"></i><span class="current">Timeline</span>
@endsection

@section('content')
@php
    $config = \App\Models\ActivityLog::iconConfig();
    $current = request('type');
    $totalWeek = array_sum($counts ?? []);
    $byDay = $items->getCollection()->groupBy(fn ($log) => $log->created_at->timezone('Asia/Ho_Chi_Minh')->toDateString());
@endphp

{{-- Quick filters: event types with their 7-day volume --}}
<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('admin.timeline', request()->except(['type', 'page'])) }}" class="btn btn-sm {{ $current ? 'btn-outline-secondary' : 'btn-primary' }}">Tất cả <span class="badge bg-white text-dark ms-1">{{ number_format($totalWeek) }}</span></a>
    @foreach($config as $type => $cfg)
        @if(($counts[$type] ?? 0) > 0 || $current === $type)
            <a href="{{ route('admin.timeline', array_merge(request()->except('page'), ['type' => $type])) }}" class="btn btn-sm {{ $current === $type ? 'btn-primary' : 'btn-outline-secondary' }}">
                <i class="ti {{ $cfg['ti'] }} me-1"></i>{{ $cfg['label'] }} <span class="badge bg-{{ $cfg['color'] }}-lt ms-1">{{ number_format($counts[$type] ?? 0) }}</span>
            </a>
        @endif
    @endforeach
    <span class="text-secondary small align-self-center ms-1">7 ngày gần đây</span>
</div>

<div class="card">
    <div class="card-header">
        <form method="GET" action="{{ route('admin.timeline') }}" class="ad-toolbar flex-fill">
            <select class="form-select" name="type" aria-label="Loại hoạt động" style="max-width:220px">
                <option value="">Tất cả loại</option>
                @foreach($config as $type => $cfg)
                    <option value="{{ $type }}" {{ $current === $type ? 'selected' : '' }}>{{ $cfg['label'] }}</option>
                @endforeach
            </select>
            <div class="input-icon" style="min-width:240px">
                <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                <input type="search" class="form-control" name="search" value="{{ request('search') }}" placeholder="Tên user, mô tả…" aria-label="Tìm hoạt động">
            </div>
            <input type="date" class="form-control" name="date" value="{{ request('date') }}" aria-label="Ngày" style="max-width:170px">
            <button type="submit" class="btn btn-primary">Lọc</button>
            @if(request()->hasAny(['type', 'date', 'search']))
                <a href="{{ route('admin.timeline') }}" class="btn btn-ghost-secondary"><i class="ti ti-x me-1"></i>Xóa lọc</a>
            @endif
        </form>
        <span class="text-secondary small">{{ number_format($items->total()) }} bản ghi</span>
    </div>

    <div class="card-body">
        @if($items->count() > 0)
            @foreach($byDay as $day => $logs)
                @php $d = \Illuminate\Support\Carbon::parse($day, 'Asia/Ho_Chi_Minh'); @endphp
                <div class="ad-tl-day">{{ $d->isToday() ? 'Hôm nay' : ($d->isYesterday() ? 'Hôm qua' : $d->format('d/m/Y')) }} <span>{{ $logs->count() }} hoạt động</span></div>
                <div class="ad-tl">
                    @foreach($logs as $log)
                        @php $cfg = $config[$log->event_type] ?? ['ti' => 'ti-activity', 'color' => 'gray', 'label' => $log->event_type]; @endphp
                        <div class="ad-tl-item">
                            <span class="ad-tl-dot bg-{{ $cfg['color'] }}-lt text-{{ $cfg['color'] }}"><i class="ti {{ $cfg['ti'] }}"></i></span>
                            <div class="ad-tl-body">
                                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                    <div>
                                        <strong>{{ $log->user_name }}</strong>
                                        <span class="badge bg-{{ $cfg['color'] }}-lt ms-1">{{ $cfg['label'] }}</span>
                                    </div>
                                    <small class="text-secondary text-nowrap" title="{{ $log->created_at->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i:s') }}">{{ $log->created_at->diffForHumans() }}</small>
                                </div>
                                <div class="mt-1">{{ $log->description }}</div>
                                @if($log->properties)
                                    <div class="d-flex flex-wrap gap-1 mt-2">
                                        @foreach($log->properties as $k => $v)
                                            <span class="badge bg-secondary-lt font-monospace-sm">{{ $k }}: {{ is_array($v) ? json_encode($v) : $v }}</span>
                                        @endforeach
                                    </div>
                                @endif
                                @if($log->ip_address)<div class="text-secondary small mt-1"><i class="ti ti-map-pin"></i> {{ $log->ip_address }}</div>@endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @else
            <div class="empty">
                <div class="empty-icon"><i class="ti ti-timeline-event"></i></div>
                <p class="empty-title">Chưa có hoạt động nào</p>
                <p class="empty-subtitle text-secondary">Hoạt động sẽ xuất hiện khi users đăng ký, tạo portfolio, hoặc admin thực hiện thao tác.</p>
            </div>
        @endif
    </div>
    @if($items->hasPages())
        <div class="card-footer">{{ $items->links() }}</div>
    @endif
</div>
@endsection

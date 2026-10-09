@extends('layouts.admin')

@section('title', 'Chất lượng dữ liệu')
@section('page_pretitle', 'Nội dung & dữ liệu')
@section('page_title', 'Chất lượng dữ liệu')

@section('breadcrumbs')
    <i class="ti ti-chevron-right"></i><span class="current">Chất lượng dữ liệu</span>
@endsection

@section('page_actions')
    <form method="POST" action="{{ route('admin.data-quality.refresh') }}">@csrf <button class="btn btn-outline-secondary"><i class="ti ti-reload me-1"></i> Kiểm tra lại</button></form>
@endsection

@section('content')
<div class="alert alert-{{ $problems === 0 ? 'success' : 'warning' }} d-flex align-items-center gap-2" role="status">
    <i class="ti ti-{{ $problems === 0 ? 'circle-check' : 'alert-triangle' }} fs-3"></i>
    <div>
        @if($problems === 0) <strong>Không phát hiện vấn đề nào</strong> trong {{ count($checks) }} kiểm tra.
        @else <strong>{{ $problems }}/{{ count($checks) }} kiểm tra có vấn đề.</strong> @endif
        <span class="text-secondary">Kết quả lúc {{ $generatedAt->timezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y') }} (lưu 10 phút).</span>
    </div>
</div>

<div class="row row-cards g-3">
    @foreach($checks as $c)
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="card-title">{{ $c['title'] }}</h3>
                    <span class="badge ms-auto {{ $c['count'] === 0 ? 'bg-green-lt' : 'bg-orange-lt' }}">{{ number_format($c['count']) }}</span>
                </div>
                <div class="card-body">
                    <p class="text-secondary small">{{ $c['hint'] }}</p>
                    @if($c['count'] === 0)
                        <span class="text-success"><i class="ti ti-circle-check"></i> Ổn</span>
                    @else
                        @foreach($c['sample'] as $item)<span class="badge bg-secondary-lt me-1 mb-1">{{ $item }}</span>@endforeach
                        @if($c['count'] > count($c['sample']))<span class="text-secondary small">… và {{ number_format($c['count'] - count($c['sample'])) }} nữa</span>@endif
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection

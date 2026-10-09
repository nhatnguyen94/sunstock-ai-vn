{{-- Charts: plain HTML/CSS bars (no chart library needed in the admin). Aggregates only; the hourly chart needs the timeline permission. --}}
@php
    $signupMax = max(1, collect($insights['signups'])->max('count'));
    $hourMax = $activityHours ? max(1, max($activityHours)) : 1;
    $topWatchedMax = max(1, collect($insights['top_watched'])->max('count') ?: 1);
    $topHeldMax = max(1, collect($insights['top_held'])->max('count') ?: 1);
@endphp
<div class="row row-deck row-cards g-3 mb-3" id="adCharts">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-user-plus me-2 text-primary"></i>Đăng ký mới · 30 ngày</h3>
                <span class="badge bg-blue-lt">{{ number_format($insights['signups_total']) }} tài khoản</span>
            </div>
            <div class="card-body">
                <div class="ad-bars" role="img" aria-label="Số tài khoản đăng ký mới mỗi ngày trong 30 ngày qua">
                    @foreach($insights['signups'] as $d)
                        <div class="ad-bar" title="{{ $d['label'] }}: {{ $d['count'] }}">
                            <i style="--h: {{ $d['count'] / $signupMax * 100 }}%" class="{{ $d['count'] === 0 ? 'is-zero' : '' }}"></i>
                            <span>{{ $loop->first || $loop->last || $loop->iteration % 7 === 0 ? $d['label'] : '' }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="text-secondary small mt-2">Người dùng hoạt động trong 7 ngày qua: <strong>{{ number_format($insights['active_7d']) }}</strong></div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-clock-hour-4 me-2 text-primary"></i>Hoạt động theo giờ · 7 ngày</h3></div>
            <div class="card-body">
                @if($activityHours)
                    <div class="ad-bars ad-bars-hours" role="img" aria-label="Số sự kiện theo giờ trong ngày, giờ Việt Nam">
                        @foreach($activityHours as $hour => $n)
                            <div class="ad-bar" title="{{ sprintf('%02d', $hour) }}h: {{ $n }}">
                                <i style="--h: {{ $n / $hourMax * 100 }}%" class="{{ $n === 0 ? 'is-zero' : '' }}"></i>
                                <span>{{ $hour % 6 === 0 ? $hour.'h' : '' }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="text-secondary small mt-2">Giờ Việt Nam · cao nhất lúc <strong>{{ array_search(max($activityHours), $activityHours) }}h</strong></div>
                @else
                    <p class="text-secondary mb-0">Cần quyền xem timeline để hiện biểu đồ này.</p>
                @endif
            </div>
        </div>
    </div>
    @foreach([['Mã được theo dõi nhiều nhất', 'ti-star', $insights['top_watched'], $topWatchedMax, 'lượt ★'], ['Mã được nắm giữ nhiều nhất', 'ti-briefcase', $insights['top_held'], $topHeldMax, 'danh mục']] as [$title, $icon, $rows, $max, $unit])
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti {{ $icon }} me-2 text-primary"></i>{{ $title }}</h3></div>
            <div class="card-body">
                @forelse($rows as $row)
                    <div class="ad-hbar">
                        <a href="{{ url('/stock?symbol='.$row['symbol']) }}" target="_blank" rel="noopener" class="fw-semibold">{{ $row['symbol'] }}</a>
                        <span class="ad-hbar-track"><i style="width: {{ $row['count'] / $max * 100 }}%"></i></span>
                        <span class="tabular-nums text-secondary small">{{ number_format($row['count']) }} {{ $unit }}</span>
                    </div>
                @empty
                    <p class="text-secondary mb-0">Chưa có dữ liệu.</p>
                @endforelse
            </div>
        </div>
    </div>
    @endforeach
</div>

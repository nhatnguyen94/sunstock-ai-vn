@extends('layouts.app')

@section('title', 'Quỹ ETF – So sánh hiệu suất và thanh khoản | Sun Stock AI')

@section('head')
@vite(['resources/frontend/css/funds/funds.css', 'resources/frontend/css/etf/etf.css', 'resources/frontend/css/shared/watchlist.css'])
@endsection

@use('App\Support\VnFormat', 'F')
@use('App\Models\Etf')

@php
    $active = array_filter($filters, fn ($v, $k) => $v !== null && ! in_array($k, ['sort', 'dir'], true), ARRAY_FILTER_USE_BOTH);
    $sortLink = function (string $key) use ($filters, $active) {
        $dir = ($filters['sort'] === $key && $filters['dir'] === 'desc') ? 'asc' : 'desc';
        if ($key === 'symbol' && $filters['sort'] !== $key) { $dir = 'asc'; }
        return route('etf.index', $active + ['sort' => $key, 'dir' => $dir]);
    };
    $arrow = fn (string $key) => $filters['sort'] === $key ? ($filters['dir'] === 'asc' ? '↑' : '↓') : '';
    $sortKeep = ['sort' => $filters['sort'], 'dir' => $filters['dir']];
    $returns = ['r1m' => '1 tháng', 'r3m' => '3 tháng', 'r6m' => '6 tháng', 'ytd' => 'Đầu năm', 'r1y' => '1 năm', 'r3y' => '3 năm'];
    $tradeDate = collect($rows)->where('source', 'live')->pluck('as_of')->filter()->first();
@endphp

@section('content')
<section class="fd-header">
    <div class="container">
        <div class="row align-items-center fd-header-row">
            <div class="col-md-8">
                <div class="fd-badge"><i class="bi bi-bar-chart-steps"></i> Dữ liệu KBS · {{ $total }} quỹ niêm yết</div>
                <h1 class="fd-title"><i class="bi bi-bar-chart-steps"></i> Quỹ ETF</h1>
                <p class="fd-subtitle">So sánh các quỹ ETF và quỹ đóng niêm yết trên HOSE: giá, thanh khoản, hiệu suất và chỉ số theo dõi</p>
            </div>
            <div class="col-md-4 text-md-right fd-header-actions">
                <a href="{{ route('funds.index') }}" class="fd-btn"><i class="bi bi-pie-chart"></i> Quỹ mở</a>
            </div>
        </div>
    </div>
</section>

<div class="fd-main">
<div class="container">

    @if($error && $total === 0)
        <div class="fd-card fd-state">
            <i class="bi bi-cloud-slash fd-state-icon"></i>
            <h3>Chưa tải được danh sách quỹ ETF</h3>
            <p>{{ $error }}</p>
            <a class="fd-btn fd-btn-solid" href="{{ route('etf.index') }}"><i class="bi bi-arrow-clockwise"></i> Thử lại</a>
        </div>
    @else

    {{-- ── Kind cards (double as filter tabs) ── --}}
    <div class="fd-types">
        <a href="{{ route('etf.index', array_diff_key($active, ['kind' => 1]) + $sortKeep) }}" class="fd-type {{ $filters['kind'] === null ? 'active' : '' }}">
            <span class="fd-type-name">Tất cả</span>
            <span class="fd-type-count">{{ $total }}</span>
            <span class="fd-type-meta">quỹ niêm yết</span>
        </a>
        <a href="{{ route('etf.index', array_merge($active, ['kind' => Etf::KIND_ETF]) + $sortKeep) }}" class="fd-type {{ $filters['kind'] === Etf::KIND_ETF ? 'active' : '' }}">
            <span class="fd-type-name">Quỹ ETF</span>
            <span class="fd-type-count">{{ $counts[Etf::KIND_ETF] }}</span>
            <span class="fd-type-meta">bám theo một chỉ số</span>
        </a>
        <a href="{{ route('etf.index', array_merge($active, ['kind' => Etf::KIND_CLOSED]) + $sortKeep) }}" class="fd-type {{ $filters['kind'] === Etf::KIND_CLOSED ? 'active' : '' }}">
            <span class="fd-type-name">Quỹ đóng</span>
            <span class="fd-type-count">{{ $counts[Etf::KIND_CLOSED] }}</span>
            <span class="fd-type-meta">niêm yết, không bám chỉ số</span>
        </a>
    </div>

    <details class="fd-card etf-guide">
        <summary><i class="bi bi-lightbulb"></i> ETF là gì? Đọc bảng này thế nào?</summary>
        <div class="etf-guide-body">
            <ul>
                <li><strong>ETF</strong> là quỹ đầu tư niêm yết, mua và bán trên sàn như một cổ phiếu, mục tiêu là đi theo một chỉ số (ví dụ VN30) thay vì cố vượt chỉ số. Mua một mã ETF là sở hữu một phần “rổ” cổ phiếu của chỉ số đó.</li>
                <li><strong>Cùng chỉ số, khác quỹ:</strong> nhiều công ty quản lý cùng bám VN30. Hãy so sánh <em>thanh khoản</em> (mua bán dễ và sát giá không) và <em>hiệu suất</em> của các quỹ cùng chỉ số với nhau; chênh lệch nhỏ giữa chúng phản ánh phí và sai số bám sát.</li>
                <li><strong>GTGD TB 20 phiên</strong> là giá trị giao dịch trung bình mỗi phiên. Quỹ có GTGD thấp thường khó mua bán ở đúng giá thị trường.</li>
                <li><strong>Quỹ đóng</strong> (Thiên Việt, REIT…) cũng niêm yết như cổ phiếu nhưng không bám một chỉ số.</li>
            </ul>
            <p class="etf-guide-note"><i class="bi bi-info-circle"></i> Nguồn dữ liệu chưa có NAV, iNAV, danh mục nắm giữ hay phí quản lý của ETF, nên trang này không hiển thị mức chiết/phụ giá so với NAV. Hiệu suất tính từ giá đóng cửa của chứng chỉ quỹ trên sàn.</p>
        </div>
    </details>

    {{-- ── Filters ── --}}
    <form method="GET" action="{{ route('etf.index') }}" class="fd-card fd-filter">
        @if($filters['kind'])<input type="hidden" name="kind" value="{{ $filters['kind'] }}">@endif
        <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
        <input type="hidden" name="dir" value="{{ $filters['dir'] }}">
        <div class="form-row align-items-end">
            <div class="col-md-5 form-group mb-md-0">
                <label for="etfQ">Tìm quỹ</label>
                <input type="text" id="etfQ" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="Mã, tên hoặc công ty quản lý, ví dụ: VN30, SSIAM…" maxlength="40">
            </div>
            <div class="col-md-4 form-group mb-md-0">
                <label for="etfIndex">Chỉ số theo dõi</label>
                <select id="etfIndex" name="index" class="form-control">
                    <option value="">Tất cả</option>
                    @foreach($indices as $index)
                        <option value="{{ $index }}" @selected($filters['index'] === $index)>{{ $index }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 form-group mb-0 fd-filter-actions">
                <button type="submit" class="fd-btn fd-btn-solid"><i class="bi bi-search"></i> Lọc</button>
                <a href="{{ route('etf.index') }}" class="fd-btn fd-btn-ghost"><i class="bi bi-x-circle"></i> Xóa</a>
            </div>
        </div>
    </form>

    <div class="fd-meta">
        <span><strong>{{ count($rows) }}</strong> quỹ phù hợp</span>
        <span>
            @if($tradeDate) <i class="bi bi-clock-history"></i> Giá phiên {{ F::date($tradeDate) }} @endif
        </span>
    </div>

    {{-- ── Table ── --}}
    <div class="fd-card fd-table-card">
        <div class="table-responsive">
            <table class="fd-table etf-table" id="etfTable">
                <thead>
                    <tr>
                        <th class="fd-check" title="Chọn tối đa {{ $max }} quỹ để so sánh biểu đồ"><i class="bi bi-intersect"></i></th>
                        <th class="etf-star"></th>
                        <th><a href="{{ $sortLink('symbol') }}">Quỹ <span>{{ $arrow('symbol') }}</span></a></th>
                        <th class="num"><a href="{{ $sortLink('price') }}">Giá (₫) <span>{{ $arrow('price') }}</span></a></th>
                        <th class="num"><a href="{{ $sortLink('percent') }}">+/- % <span>{{ $arrow('percent') }}</span></a></th>
                        <th class="num"><a href="{{ $sortLink('value') }}" title="Giá trị giao dịch phiên gần nhất">GTGD <span>{{ $arrow('value') }}</span></a></th>
                        <th class="num"><a href="{{ $sortLink('avg_value') }}" title="Giá trị giao dịch trung bình 20 phiên gần nhất">GTGD TB 20p <span>{{ $arrow('avg_value') }}</span></a></th>
                        @foreach($returns as $key => $label)
                            <th class="num"><a href="{{ $sortLink($key) }}">{{ $label }} <span>{{ $arrow($key) }}</span></a></th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $r)
                    <tr>
                        <td class="fd-check"><input type="checkbox" class="fd-pick" value="{{ $r['symbol'] }}" aria-label="Chọn {{ $r['symbol'] }} để so sánh"></td>
                        <td class="etf-star"><button type="button" class="wl-star" data-watch="{{ $r['symbol'] }}" title="Theo dõi {{ $r['symbol'] }}" aria-label="Theo dõi {{ $r['symbol'] }}"><i class="bi bi-star"></i></button></td>
                        <td>
                            <a class="fd-code" href="{{ route('etf.show', $r['symbol']) }}">{{ $r['symbol'] }}</a>
                            <span class="fd-fullname">{{ $r['short'] }}</span>
                            @if($r['manager'] || $r['index'])
                                <span class="etf-tags">
                                    @if($r['index'])<span class="etf-tag etf-tag-index">{{ $r['index'] }}</span>@endif
                                    @if($r['manager'])<span class="etf-tag">{{ $r['manager'] }}</span>@endif
                                </span>
                            @endif
                        </td>
                        <td class="num">{{ F::number($r['price']) }}</td>
                        <td class="num"><span class="fd-pill {{ F::trendClass($r['percent']) }}">{{ F::percent($r['percent'], 2, true) }}</span></td>
                        <td class="num">{{ $r['value'] !== null ? F::bigMoney($r['value']) : '—' }}</td>
                        <td class="num">{{ $r['avg_value'] !== null ? F::bigMoney($r['avg_value']) : '—' }}</td>
                        @foreach($returns as $key => $label)
                            <td class="num"><span class="fd-pill {{ F::trendClass($r[$key]) }}">{{ F::percent($r[$key], 2, true) }}</span></td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ 7 + count($returns) }}" class="fd-empty">Không có quỹ nào khớp bộ lọc.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="fd-footnote">Giá và GTGD lấy từ bảng giá KBS (đơn vị đồng); hiệu suất tính từ giá đóng cửa, chưa trừ thuế phí giao dịch. “—” nghĩa là quỹ chưa đủ tuổi cho kỳ đó. Danh sách cập nhật hàng tuần @if($synced_at) (lần gần nhất {{ $synced_at->format('d/m/Y') }})@endif. Hiệu suất quá khứ không đảm bảo kết quả tương lai; đây không phải lời khuyên đầu tư.</p>
    @endif

</div>
</div>

{{-- Compare bar --}}
<div class="fd-compare-bar" id="fdCompareBar" hidden>
    <div class="container d-flex align-items-center justify-content-between flex-wrap" style="gap:.75rem;">
        <span><strong id="fdPickCount">0</strong> quỹ đã chọn <small id="fdPickList"></small></span>
        <span>
            <button type="button" class="fd-btn fd-btn-ghost" id="fdPickClear">Bỏ chọn</button>
            <a href="{{ route('stock.compare') }}" class="fd-btn fd-btn-solid" id="fdCompareGo"><i class="bi bi-intersect"></i> So sánh biểu đồ</a>
        </span>
    </div>
</div>
@endsection

@section('scripts')
<script>window.__ETF__ = { max: {{ $max }}, compareUrl: @json(route('stock.compare')) };</script>
<script>window.__WATCH__ = @json($watch);</script>
@vite('resources/frontend/js/shared/watchlist-init.js')
@vite('resources/frontend/js/etf/index.js')
@endsection

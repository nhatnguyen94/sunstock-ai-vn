@extends('layouts.app')

@section('head')
@vite('resources/frontend/css/stock/screener.css')
@endsection

@section('content')
<section class="screener-header">
    <div class="container">
        <div class="row align-items-center screener-header-row">
            <div class="col-md-8">
                <div class="screener-badge">
                    <i class="bi bi-funnel"></i>
                    Lọc theo chỉ số tài chính
                </div>
                <h1 class="screener-title">
                    <i class="bi bi-funnel-fill"></i> Stock Screener
                </h1>
                <p class="screener-subtitle">Tìm cổ phiếu theo P/E, P/B, ROE, tỷ suất cổ tức, nợ/vốn chủ sở hữu... dựa trên báo cáo tài chính đã đồng bộ</p>
            </div>
            <div class="col-md-4 text-md-right screener-header-actions">
                <a href="{{ url('/') }}" class="back-button">
                    <i class="bi bi-house"></i> Trang chủ
                </a>
            </div>
        </div>
    </div>
</section>

<div class="screener-main">
    <div class="container">
        <form method="GET" action="{{ route('stock.screener') }}" class="screener-filter-panel">
            <h3 class="screener-filter-title">
                <i class="bi bi-sliders"></i>
                Bộ lọc
            </h3>
            <div class="form-row">
                <div class="col-6 col-md-2 form-group screener-field">
                    <label>P/E từ</label>
                    <input type="number" step="0.1" name="pe_min" value="{{ $filters['pe_min'] ?? '' }}" class="form-control" placeholder="0">
                </div>
                <div class="col-6 col-md-2 form-group screener-field">
                    <label>P/E đến</label>
                    <input type="number" step="0.1" name="pe_max" value="{{ $filters['pe_max'] ?? '' }}" class="form-control" placeholder="20">
                </div>
                <div class="col-6 col-md-2 form-group screener-field">
                    <label>P/B từ</label>
                    <input type="number" step="0.1" name="pb_min" value="{{ $filters['pb_min'] ?? '' }}" class="form-control" placeholder="0">
                </div>
                <div class="col-6 col-md-2 form-group screener-field">
                    <label>P/B đến</label>
                    <input type="number" step="0.1" name="pb_max" value="{{ $filters['pb_max'] ?? '' }}" class="form-control" placeholder="3">
                </div>
                <div class="col-6 col-md-2 form-group screener-field">
                    <label>ROE từ (%)</label>
                    <input type="number" step="0.1" name="roe_min" value="{{ $filters['roe_min'] ?? '' }}" class="form-control" placeholder="15">
                </div>
                <div class="col-6 col-md-2 form-group screener-field">
                    <label>Cổ tức từ (%)</label>
                    <input type="number" step="0.1" name="dividend_yield_min" value="{{ $filters['dividend_yield_min'] ?? '' }}" class="form-control" placeholder="5">
                </div>
            </div>
            <div class="form-row align-items-end">
                <div class="col-6 col-md-3 form-group screener-field mb-0">
                    <label>Nợ/VCSH tối đa (%)</label>
                    <input type="number" step="1" name="debt_equity_max" value="{{ $filters['debt_equity_max'] ?? '' }}" class="form-control" placeholder="150">
                </div>
                <div class="col-12 col-md-9 form-group mb-0 screener-actions">
                    <button type="submit" class="screener-apply-btn">
                        <i class="bi bi-search"></i> Lọc
                    </button>
                    <a href="{{ route('stock.screener') }}" class="screener-reset-btn">
                        <i class="bi bi-arrow-clockwise"></i> Xóa lọc
                    </a>
                </div>
            </div>
        </form>

        <div class="screener-stats">
            <div class="screener-stat-card">
                <span class="screener-stat-number">{{ count($results) }}</span>
                <span class="screener-stat-label">Mã phù hợp</span>
            </div>
            <div class="screener-stat-card">
                <i class="bi bi-bank screener-stat-icon"></i>
                <span class="screener-stat-label">Nguồn: VNStock</span>
            </div>
        </div>

        @php
            $sortLink = function (string $key) use ($filters) {
                $dir = (($filters['sort'] ?? '') === $key && ($filters['dir'] ?? 'asc') === 'asc') ? 'desc' : 'asc';
                return route('stock.screener', array_merge($filters, ['sort' => $key, 'dir' => $dir]));
            };
            $sortArrow = function (string $key) use ($filters) {
                if (($filters['sort'] ?? '') !== $key) return '';
                return ($filters['dir'] ?? 'asc') === 'asc' ? '↑' : '↓';
            };
            $pillClass = function (?float $value, float $goodMin = null, float $badMax = null, bool $lowerIsBetter = false) {
                if ($value === null) return 'is-neutral';
                if ($lowerIsBetter) {
                    if ($badMax !== null && $value >= $badMax) return 'is-bad';
                    if ($goodMin !== null && $value <= $goodMin) return 'is-good';
                    return 'is-neutral';
                }
                if ($goodMin !== null && $value >= $goodMin) return 'is-good';
                if ($badMax !== null && $value <= $badMax) return 'is-bad';
                return 'is-neutral';
            };
        @endphp

        <div class="screener-table-card" data-aos="fade-up">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Mã CP</th>
                            <th><a href="{{ $sortLink('pe') }}">P/E <span class="sort-arrow">{{ $sortArrow('pe') }}</span></a></th>
                            <th><a href="{{ $sortLink('pb') }}">P/B <span class="sort-arrow">{{ $sortArrow('pb') }}</span></a></th>
                            <th><a href="{{ $sortLink('eps') }}">EPS <span class="sort-arrow">{{ $sortArrow('eps') }}</span></a></th>
                            <th><a href="{{ $sortLink('roe') }}">ROE (%) <span class="sort-arrow">{{ $sortArrow('roe') }}</span></a></th>
                            <th><a href="{{ $sortLink('roa') }}">ROA (%) <span class="sort-arrow">{{ $sortArrow('roa') }}</span></a></th>
                            <th><a href="{{ $sortLink('dividend_yield') }}">Cổ tức (%) <span class="sort-arrow">{{ $sortArrow('dividend_yield') }}</span></a></th>
                            <th><a href="{{ $sortLink('debt_equity') }}">Nợ/VCSH <span class="sort-arrow">{{ $sortArrow('debt_equity') }}</span></a></th>
                            <th>Cập nhật</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($results as $row)
                        <tr>
                            <td><a class="screener-symbol-badge" href="{{ url('/stock?symbol=' . $row['symbol']) }}">{{ $row['symbol'] }}</a></td>
                            <td>
                                @if(isset($row['pe']))
                                    <span class="screener-pill is-neutral">{{ number_format($row['pe'], 2) }}</span>
                                @else <span class="screener-muted">—</span> @endif
                            </td>
                            <td>
                                @if(isset($row['pb']))
                                    <span class="screener-pill is-neutral">{{ number_format($row['pb'], 2) }}</span>
                                @else <span class="screener-muted">—</span> @endif
                            </td>
                            <td>
                                @if(isset($row['eps']))
                                    <span class="screener-pill is-neutral">{{ number_format($row['eps'], 0) }}</span>
                                @else <span class="screener-muted">—</span> @endif
                            </td>
                            <td>
                                @if(isset($row['roe']))
                                    <span class="screener-pill {{ $pillClass($row['roe'], 15, 5) }}">{{ number_format($row['roe'], 2) }}</span>
                                @else <span class="screener-muted">—</span> @endif
                            </td>
                            <td>
                                @if(isset($row['roa']))
                                    <span class="screener-pill is-neutral">{{ number_format($row['roa'], 2) }}</span>
                                @else <span class="screener-muted">—</span> @endif
                            </td>
                            <td>
                                @if(isset($row['dividend_yield']))
                                    <span class="screener-pill {{ $pillClass($row['dividend_yield'], 5, 0) }}">{{ number_format($row['dividend_yield'], 2) }}</span>
                                @else <span class="screener-muted">—</span> @endif
                            </td>
                            <td>
                                @if(isset($row['debt_equity']))
                                    <span class="screener-pill {{ $pillClass($row['debt_equity'], 100, 200, lowerIsBetter: true) }}">{{ number_format($row['debt_equity'], 2) }}</span>
                                @else <span class="screener-muted">—</span> @endif
                            </td>
                            <td><span class="screener-muted">{{ $row['synced_at'] ?? '—' }}</span></td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9">
                                <div class="screener-empty">
                                    <i class="bi bi-inbox"></i>
                                    Không tìm thấy mã cổ phiếu nào khớp bộ lọc.
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <p class="screener-footnote">
            <i class="bi bi-info-circle"></i>
            Chỉ tính trên các mã đã có dữ liệu báo cáo tài chính. Danh sách sẽ đầy đủ hơn theo thời gian khi hệ thống tiếp tục đồng bộ.
        </p>
    </div>
</div>
@endsection

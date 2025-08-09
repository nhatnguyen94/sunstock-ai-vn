@extends('layouts.app')

@section('head')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/awesomplete/1.1.5/awesomplete.min.css" />
<style>
    .homepage-header {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        padding: 18px 24px;
        margin-bottom: 28px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-height: 70px;
    }
    .homepage-title {
        font-size: 1.7rem;
        font-weight: 700;
        color: #007bff;
        margin-bottom: 0;
    }
    .homepage-menu a {
        font-weight: 500;
        color: #333;
        margin-left: 24px;
        text-decoration: none;
        transition: color 0.2s;
        font-size: 1.05rem;
    }
    .homepage-menu a:hover {
        color: #007bff;
    }
    .main-content {
        max-width: 900px;
        margin: 0 auto;
    }
    .stock-highlight-card {
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        background: #fff;
        padding: 20px 24px;
        margin-bottom: 24px;
    }
    .popular-symbols .btn { margin: 0 6px 6px 0; }
    .awesomplete li b { color: #007bff; }
    .awesomplete li span { font-size: 90%; color: #d44a14; }
    .featured-row {
        display: flex;
        gap: 24px;
        justify-content: center;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }
    .featured-col {
        flex: 1 1 0;
        min-width: 220px;
        max-width: 300px;
        display: flex;
    }
    .featured-card {
        width: 100%;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        background: #fff;
        padding: 18px 16px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 170px;
        transition: box-shadow 0.2s;
    }
    .featured-card:hover {
        box-shadow: 0 6px 18px rgba(0,123,255,0.12);
    }
    @media (max-width: 900px) {
        .main-content { max-width: 100%; }
        .featured-row { gap: 12px; }
        .featured-col { min-width: 180px; }
    }
    @media (max-width: 600px) {
        .homepage-header { flex-direction: column; padding: 14px 8px; }
        .homepage-title { font-size: 1.2rem; }
        .featured-row { flex-direction: column; gap: 12px; }
        .featured-col { min-width: 100%; }
    }
</style>
@endsection

@section('content')
<div class="homepage-header">
    <div>
        <span class="homepage-title">VN Stock App</span>
        <span class="ml-3 text-muted d-none d-md-inline">Tra cứu giá cổ phiếu Việt Nam nhanh chóng, trực quan</span>
    </div>
    <nav class="homepage-menu">
        <a href="{{ url('/') }}">Trang chủ</a>
        <a href="{{ url('/stock') }}">Tra cứu chi tiết mã cổ phiếu</a>
    </nav>
</div>

<div class="main-content">
    <div class="stock-highlight-card mb-4">
        <h4 class="mb-3 text-primary text-center">Tra cứu mã chứng khoán</h4>
        <form method="GET" action="{{ url('/stock') }}" class="form-inline justify-content-center mb-3" autocomplete="off">
            <input type="text" id="symbol" name="symbol" class="form-control mr-2" placeholder="Nhập mã: FPT, VNM, VCB..." required autocomplete="off" style="min-width:120px;">
            <button type="submit" class="btn btn-primary">Tra cứu</button>
            <div id="notFoundMsg" class="text-danger ml-3" style="display:none;">Không tìm thấy mã phù hợp!</div>
        </form>
        <div class="popular-symbols text-center mb-2">
            <span class="text-muted mr-2">Mã nổi bật:</span>
            @foreach($featured as $stock)
                <a href="{{ url('/stock?symbol='.$stock['symbol']) }}" class="btn btn-outline-secondary btn-sm" title="{{ $stock['name'] }}">{{ $stock['symbol'] }}</a>
            @endforeach
        </div>
    </div>

    <div class="featured-row">
        @foreach($featured as $stock)
        <div class="featured-col">
            <div class="featured-card">
                <div class="mb-1">
                    <span class="badge badge-primary" style="font-size:1rem;">{{ $stock['symbol'] }}</span>
                    <span class="font-weight-bold ml-2">{{ $stock['name'] }}</span>
                </div>
                <div class="mb-1">
                    <span class="text-success font-weight-bold" style="font-size:1.1rem;">
                        {{ $stock['price'] ? number_format($stock['price']) . ' VND' : 'N/A' }}
                    </span>
                    @if($stock['change'] !== null)
                    <span class="ml-2 {{ $stock['change'] >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $stock['change'] >= 0 ? '+' : '' }}{{ number_format($stock['change'],2) }}%
                    </span>
                    @endif
                </div>
                <div class="mb-1">
                    <span class="text-muted">Sàn: {{ $stock['exchange'] }} | Ngành: {{ $stock['industry'] }}</span>
                </div>
                <a href="{{ url('/stock?symbol='.$stock['symbol']) }}" class="btn btn-outline-primary btn-sm mt-2">Xem chi tiết</a>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/awesomplete/1.1.5/awesomplete.min.js"></script>
<script>
let awesomplete = new Awesomplete(document.getElementById('symbol'), {
    minChars: 1,
    maxItems: 15,
    autoFirst: true,
    list: []
});
document.getElementById('symbol').addEventListener('input', function() {
    let val = this.value;
    if (val.length < 1) {
        document.getElementById('notFoundMsg').style.display = 'none';
        return;
    }
    fetch('/stocks-list?q=' + encodeURIComponent(val))
        .then(res => res.json())
        .then(data => {
            if (data.length === 0) {
                document.getElementById('notFoundMsg').style.display = '';
            } else {
                document.getElementById('notFoundMsg').style.display = 'none';
            }
            let list = data.map(item => ({
                label: `<b>${item.symbol}</b> - <span>${item.name}</span>`,
                value: item.symbol
            }));
            awesomplete.list = list;
        }).catch(err => {
        document.getElementById('notFoundMsg').style.display = '';
        console.error('Lỗi lấy danh sách mã:', err);
    });
});
</script>
@endsection
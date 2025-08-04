@extends('layouts.app')

@section('head')
<!-- Awesomplete CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/awesomplete/1.1.5/awesomplete.min.css" />
<style>
    .awesomplete li b { color: #007bff; }
    .awesomplete li span { font-size: 90%; color: #d44a14; }
</style>
@endsection

@section('content')
<div class="container">
    <h2 class="mb-4 text-primary">Thông tin cổ phiếu: <strong>{{ $symbol }}</strong></h2>

    {{-- Form chọn mã --}}
    <form method="GET" action="{{ url('/stock') }}" class="form-inline mb-4" autocomplete="off">
        <label for="symbol" class="mr-2">Nhập mã cổ phiếu:</label>
        <input type="text" id="symbol" name="symbol" value="{{ $symbol }}" class="form-control mr-2" required autocomplete="off">
        <button type="submit" class="btn btn-primary">Xem</button>
    </form>

    {{-- Thông báo lỗi --}}
    @if(isset($error))
        <div class="alert alert-danger">{{ $error }}</div>
    @endif
    @if (count($data) > 0)
        {{-- Biểu đồ đường --}}
        <canvas id="priceChart" height="100"></canvas>
        {{-- Biểu đồ nến --}}
        <canvas id="candlestickChart" height="100" class="mt-4"></canvas>

        {{-- Bảng dữ liệu --}}
        <table class="table mt-4 table-bordered">
            <thead class="thead-light">
                <tr>
                    <th>Ngày</th>
                    <th>Mở cửa</th>
                    <th>Cao nhất</th>
                    <th>Thấp nhất</th>
                    <th>Đóng cửa</th>
                    <th>Khối lượng</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data as $item)
                    <tr>
                        <td>{{ \Carbon\Carbon::createFromTimestampMs($item['time'])->format('d/m/Y') }}</td>
                        <td>{{ $item['open'] }}</td>
                        <td>{{ $item['high'] }}</td>
                        <td>{{ $item['low'] }}</td>
                        <td>{{ $item['close'] }}</td>
                        <td>{{ number_format($item['volume']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="alert alert-warning mt-4">Chưa có dữ liệu để hiển thị biểu đồ hoặc bảng.</div>
    @endif
</div>
@endsection

@section('scripts')
@if (count($data) > 0)
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-chart-financial"></script>
<script src="https://cdn.jsdelivr.net/npm/luxon@3/build/global/luxon.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-luxon"></script>
@endif
<!-- Awesomplete JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/awesomplete/1.1.5/awesomplete.min.js"></script>
<script>
@if (count($data) > 0)
    const rawData = @json($data);

    // Dữ liệu cho line chart
    const labels = rawData.map(item => {
        const date = new Date(item.time);
        return date.toLocaleDateString('vi-VN');
    });
    const prices = rawData.map(item => parseFloat(item.close));

    // Dữ liệu cho candlestick chart
    const candlestickData = rawData.map(item => ({
        x: item.time,
        o: parseFloat(item.open),
        h: parseFloat(item.high),
        l: parseFloat(item.low),
        c: parseFloat(item.close)
    }));

    // Line chart
    const ctx = document.getElementById('priceChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Giá đóng cửa',
                data: prices,
                borderColor: 'rgba(75, 192, 192, 1)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                fill: true,
                tension: 0.3,
                pointRadius: 3
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: true }
            },
            scales: {
                x: {
                    title: { display: true, text: 'Ngày' }
                },
                y: {
                    title: { display: true, text: 'Giá (VNĐ)' }
                }
            }
        }
    });

    // Candlestick chart
    const ctxCandle = document.getElementById('candlestickChart').getContext('2d');
    new Chart(ctxCandle, {
        type: 'candlestick',
        data: {
            datasets: [{
                label: 'Biểu đồ nến',
                data: candlestickData,
                color: {
                    up: '#26a69a',
                    down: '#ef5350',
                    unchanged: '#757575'
                }
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: true }
            },
            scales: {
                x: {
                    type: 'time',
                    adapters: {
                        date: {
                            zone: 'Asia/Ho_Chi_Minh',
                        }
                    },
                    time: {
                        unit: 'day',
                        tooltipFormat: 'dd/MM/yyyy'
                    },
                    title: { display: true, text: 'Ngày' }
                },
                y: {
                    title: { display: true, text: 'Giá (VNĐ)' }
                }
            }
        }
    });
@endif

// Awesomplete autocomplete cho input mã cổ phiếu
let awesomplete = new Awesomplete(document.getElementById('symbol'), {
    minChars: 1,
    maxItems: 15,
    autoFirst: true,
    list: []
});

document.getElementById('symbol').addEventListener('input', function() {
    let val = this.value;
    if (val.length < 1) return;
    fetch('/stocks-list?q=' + encodeURIComponent(val))
        .then(res => res.json())
        .then(data => {
            // Hiển thị cả mã và tên công ty
            let list = data.map(item => ({
                label: `<b>${item.symbol}</b> - <span>${item.name}</span>`,
                value: item.symbol
            }));
            awesomplete.list = list;
        }).catch(err => {
        console.error('Lỗi lấy danh sách mã:', err);
    });
});
</script>
@endsection
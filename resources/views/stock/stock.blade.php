@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-4 text-primary">Thông tin cổ phiếu: <strong>{{ $symbol }}</strong></h2>

    {{-- Form chọn mã --}}
    <form method="GET" action="{{ url('/stock') }}" class="form-inline mb-4">
        <label for="symbol" class="mr-2">Nhập mã cổ phiếu:</label>
        <input type="text" id="symbol" name="symbol" value="{{ $symbol }}" class="form-control mr-2" required>
        <button type="submit" class="btn btn-primary">Xem</button>
    </form>

    {{-- Thông báo lỗi --}}
    @if(isset($error))
        <div class="alert alert-danger">{{ $error }}</div>
    @endif
    @if (count($data) > 0)
        {{-- Biểu đồ --}}
        <canvas id="priceChart" height="100"></canvas>

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
<script>
    const rawData = @json($data);

    const labels = rawData.map(item => {
        const date = new Date(item.time); // dùng timestamp
        return date.toLocaleDateString('vi-VN');
    });

    const prices = rawData.map(item => parseFloat(item.close));

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
</script>
@endif
@endsection

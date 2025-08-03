@extends('layouts.app')

@section('content')
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

    {{-- Biểu đồ --}}
    @if(isset($data) && is_iterable($data) && count($data))
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Biểu đồ giá đóng cửa 30 ngày gần nhất</h5>
                <canvas id="priceChart" height="100"></canvas>
            </div>
        </div>

        {{-- Bảng dữ liệu --}}
        <div class="card stock-table">
            <div class="card-header bg-primary text-white">Lịch sử giao dịch</div>
            <div class="table-responsive">
                <table class="table table-bordered mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Ngày</th>
                            <th>Giá mở</th>
                            <th>Giá cao nhất</th>
                            <th>Giá thấp nhất</th>
                            <th>Giá đóng</th>
                            <th>Khối lượng</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($data as $item)
                            <tr>
                                <td>
                                    {{ \Carbon\Carbon::createFromTimestampMs($item['time'])->format('d/m/Y') }}
                                </td>
                                <td>{{ number_format($item['open'], 2) }}</td>
                                <td>{{ number_format($item['high'], 2) }}</td>
                                <td>{{ number_format($item['low'], 2) }}</td>
                                <td>{{ number_format($item['close'], 2) }}</td>
                                <td>{{ number_format($item['volume']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="alert alert-warning">Chưa có dữ liệu để hiển thị biểu đồ hoặc bảng.</div>
    @endif
@endsection

@section('scripts')
    @if(isset($data) && is_iterable($data))
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            const rawData = @json($data);
            const labels = rawData.map(item => {
                const date = new Date(item.date);
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
                        tension: 0.2
                    }]
                },
                options: {
                    responsive: true,
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

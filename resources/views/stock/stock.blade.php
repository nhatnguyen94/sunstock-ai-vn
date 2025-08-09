@extends('layouts.app')

@section('head')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/awesomplete/1.1.5/awesomplete.min.css" />
<style>
    .awesomplete li b { color: #007bff; }
    .awesomplete li span { font-size: 90%; color: #d44a14; }
    .chart-toggle-btn { margin-right: 10px; transition: background 0.2s; }
    .chart-toggle-btn.active { background: #007bff; color: #fff; border-color: #007bff; }
    .chart-toggle-btn[title] { cursor: pointer; }
    #chartLoading { display:none; text-align:center; margin:20px 0; }
    #candleChartWrap, #lineChartWrap { transition: opacity 0.3s; }
    .btn-back-home { margin-bottom: 20px; }
    @media (max-width: 768px) {
        .table-responsive { font-size: 13px; }
    }
</style>
@endsection

@section('content')
<div class="container">
    <a href="{{ url('/') }}" class="btn btn-outline-secondary btn-back-home">
        ← Quay lại trang chủ
    </a>
    <h2 class="mb-4 text-primary">Thông tin cổ phiếu: <strong>{{ $symbol }}</strong></h2>

    {{-- Tổng quan mã cổ phiếu --}}
    @if(isset($overview))
        <div class="mb-3">
            <span class="font-weight-bold">Tên công ty:</span> {{ $overview['name'] ?? '' }}<br>
            <span class="font-weight-bold">Sàn:</span> {{ $overview['exchange'] ?? '' }}<br>
            <span class="font-weight-bold">Ngành:</span> {{ $overview['industry'] ?? '' }}
        </div>
    @endif

    {{-- Form chọn mã --}}
    <form method="GET" action="{{ url('/stock') }}" class="form-inline mb-4" autocomplete="off">
        <label for="symbol" class="mr-2">Nhập mã cổ phiếu:</label>
        <input type="text" id="symbol" name="symbol" value="{{ $symbol }}" class="form-control mr-2" required autocomplete="off">
        <button type="submit" class="btn btn-primary">Xem</button>
        <div id="notFoundMsg" class="text-danger ml-3" style="display:none;">Không tìm thấy mã phù hợp!</div>
    </form>

    {{-- Thông báo lỗi --}}
    @if(isset($error))
        <div class="alert alert-danger">{{ $error }}</div>
    @endif

    @if (count($data) > 0)
        <div class="mb-3">
            <button id="btnCandle" class="btn btn-outline-primary chart-toggle-btn active" title="Xem biểu đồ nến (chi tiết giá từng phiên)">Biểu đồ nến</button>
            <button id="btnLine" class="btn btn-outline-secondary chart-toggle-btn" title="Xem biểu đồ đường (giá đóng cửa)">Biểu đồ đường</button>
        </div>
        <div id="chartLoading">
            <div class="spinner-border text-primary" role="status"></div>
            <span class="ml-2">Đang tải biểu đồ...</span>
        </div>
        {{-- Biểu đồ nến --}}
        <div id="candleChartWrap" style="opacity:1;">
            <canvas id="candlestickChart" height="100"></canvas>
        </div>
        {{-- Biểu đồ đường --}}
        <div id="lineChartWrap" style="display:none; opacity:0;">
            <canvas id="priceChart" height="100"></canvas>
        </div>

        {{-- Bảng dữ liệu --}}
        <div class="table-responsive">
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
                <tbody id="priceTableBody">
                    {{-- Phần này sẽ được phân trang bằng JS --}}
                </tbody>
            </table>
            <nav>
                <ul class="pagination justify-content-center" id="tablePagination"></ul>
            </nav>
        </div>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/awesomplete/1.1.5/awesomplete.min.js"></script>
<script>
@if (count($data) > 0)
    const rawData = @json($data);

    // Chart data
    const labels = rawData.map(item => {
        const date = new Date(item.time);
        return date.toLocaleDateString('vi-VN');
    });
    const prices = rawData.map(item => parseFloat(item.close));
    const candlestickData = rawData.map(item => ({
        x: item.time,
        o: parseFloat(item.open),
        h: parseFloat(item.high),
        l: parseFloat(item.low),
        c: parseFloat(item.close)
    }));

    // Chart instances
    const ctxCandle = document.getElementById('candlestickChart').getContext('2d');
    const candleChart = new Chart(ctxCandle, {
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
            plugins: { legend: { display: true } },
            scales: {
                x: {
                    type: 'time',
                    adapters: { date: { zone: 'Asia/Ho_Chi_Minh' } },
                    time: { unit: 'day', tooltipFormat: 'dd/MM/yyyy' },
                    title: { display: true, text: 'Ngày' }
                },
                y: { title: { display: true, text: 'Giá (VNĐ)' } }
            }
        }
    });

    const ctx = document.getElementById('priceChart').getContext('2d');
    const lineChart = new Chart(ctx, {
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
            plugins: { legend: { display: true } },
            scales: {
                x: { title: { display: true, text: 'Ngày' } },
                y: { title: { display: true, text: 'Giá (VNĐ)' } }
            }
        }
    });

    // Fade effect for chart switching
    function showChart(chartIdToShow, chartIdToHide) {
        document.getElementById('chartLoading').style.display = '';
        setTimeout(() => {
            document.getElementById(chartIdToHide).style.opacity = 0;
            setTimeout(() => {
                document.getElementById(chartIdToHide).style.display = 'none';
                document.getElementById(chartIdToShow).style.display = '';
                setTimeout(() => {
                    document.getElementById(chartIdToShow).style.opacity = 1;
                    document.getElementById('chartLoading').style.display = 'none';
                }, 200);
            }, 200);
        }, 100);
    }

    document.getElementById('btnCandle').addEventListener('click', function(e) {
        e.preventDefault();
        showChart('candleChartWrap', 'lineChartWrap');
        this.classList.add('active');
        document.getElementById('btnLine').classList.remove('active');
    });
    document.getElementById('btnLine').addEventListener('click', function(e) {
        e.preventDefault();
        showChart('lineChartWrap', 'candleChartWrap');
        this.classList.add('active');
        document.getElementById('btnCandle').classList.remove('active');
    });

    // Pagination for table
    const pageSize = 20;
    let currentPage = 1;
    function renderTable(page) {
        currentPage = page;
        const start = (page - 1) * pageSize;
        const end = start + pageSize;
        const pageData = rawData.slice().reverse().slice(start, end); // reverse để mới nhất lên đầu
        const tbody = document.getElementById('priceTableBody');
        tbody.innerHTML = pageData.map(item => `
            <tr>
                <td>${new Date(item.time).toLocaleDateString('vi-VN')}</td>
                <td>${item.open}</td>
                <td>${item.high}</td>
                <td>${item.low}</td>
                <td>${item.close}</td>
                <td>${Number(item.volume).toLocaleString()}</td>
            </tr>
        `).join('');
        renderPagination();
    }
    function renderPagination() {
        const totalPages = Math.ceil(rawData.length / pageSize);
        const pag = document.getElementById('tablePagination');
        let html = '';
        for (let i = 1; i <= totalPages; i++) {
            html += `<li class="page-item${i === currentPage ? ' active' : ''}">
                        <a class="page-link" href="#" onclick="renderTable(${i});return false;">${i}</a>
                    </li>`;
        }
        pag.innerHTML = html;
    }
    renderTable(1);

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
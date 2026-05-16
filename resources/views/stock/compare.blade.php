@extends('layouts.app')

@section('head')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/awesomplete/1.1.5/awesomplete.min.css" />
<style>
    .compare-header {
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        color: white;
        padding: 2.5rem 0;
        margin-bottom: 2rem;
        position: relative;
    }
    
    .symbol-tag {
        display: inline-flex;
        align-items: center;
        background: rgba(37, 99, 235, 0.1);
        border: 1px solid var(--primary-blue);
        color: var(--primary-blue);
        padding: 0.5rem 1rem;
        border-radius: 20px;
        margin: 0.5rem;
        font-weight: 600;
    }

    .symbol-tag .remove-btn {
        margin-left: 8px;
        color: var(--danger-red);
        cursor: pointer;
        font-size: 1.2rem;
    }

    .chart-container {
        background: white;
        border-radius: 16px;
        padding: 2rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--border-color);
        margin-bottom: 2rem;
        height: 500px;
    }

    .stats-table {
        background: white;
        border-radius: 16px;
        box-shadow: var(--shadow-md);
        overflow: hidden;
        border: 1px solid var(--border-color);
    }
</style>
@endsection

@section('content')
<section class="compare-header">
    <div class="container text-center">
        <h2><i class="bi bi-bar-chart-steps"></i> So Sánh Hiệu Suất Cổ Phiếu</h2>
        <p>So sánh biến động giá (tính bằng %) của tối đa 4 mã cổ phiếu</p>
    </div>
</section>

<div class="container mb-5">
    <!-- Input Section -->
    <div class="card custom-card mb-4">
        <div class="card-body-custom">
            <div class="row align-items-end">
                <div class="col-md-8">
                    <label>Thêm mã cổ phiếu để so sánh (Tối đa 4 mã):</label>
                    <div class="d-flex gap-2">
                        <input type="text" id="symbolInput" class="form-control form-control-lg" placeholder="VD: VCB, FPT...">
                        <button onclick="addSymbol()" class="btn btn-primary ml-2 px-4">
                            <i class="bi bi-plus-lg"></i> Thêm
                        </button>
                    </div>
                </div>
            </div>
            
            <div id="selectedSymbols" class="mt-3">
                <!-- Tags will go here -->
            </div>
        </div>
    </div>

    <!-- Loading State -->
    <div id="loadingState" class="text-center my-5" style="display: none;">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="mt-2 text-muted">Đang tải dữ liệu...</p>
    </div>

    <div id="compareContent" style="display: none;">
        <!-- Chart -->
        <div class="chart-container">
            <canvas id="compareChart"></canvas>
        </div>

        <!-- Table -->
        <div class="stats-table">
            <table class="table mb-0 text-center">
                <thead style="background: var(--light-blue)">
                    <tr>
                        <th>Mã CP</th>
                        <th>Tên Công Ty</th>
                        <th>Giá Hiện Tại</th>
                        <th>Biến Động (%)</th>
                        <th>Cao Nhất</th>
                        <th>Thấp Nhất</th>
                    </tr>
                </thead>
                <tbody id="statsTableBody">
                    <!-- Rows will go here -->
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
    let symbols = '{{ $symbols }}'.split(',').filter(s => s.trim() !== '');
    let chartInstance = null;
    const colors = ['#2563eb', '#10b981', '#f59e0b', '#ef4444']; // Blue, Green, Yellow, Red

    // Render initial tags
    function renderTags() {
        const container = document.getElementById('selectedSymbols');
        container.innerHTML = symbols.map((sym, idx) => `
            <div class="symbol-tag" style="border-color: ${colors[idx]}; color: ${colors[idx]}">
                ${sym}
                <i class="bi bi-x-circle-fill remove-btn" onclick="removeSymbol('${sym}')"></i>
            </div>
        `).join('');

        if (symbols.length > 0) {
            fetchCompareData();
        } else {
            document.getElementById('compareContent').style.display = 'none';
        }
    }

    function addSymbol() {
        const input = document.getElementById('symbolInput');
        const val = input.value.trim().toUpperCase();
        
        if (!val) return;
        if (symbols.includes(val)) {
            alert('Mã này đã được thêm!');
            return;
        }
        if (symbols.length >= 4) {
            alert('Chỉ so sánh tối đa 4 mã cùng lúc!');
            return;
        }

        symbols.push(val);
        input.value = '';
        
        // Update URL
        const url = new URL(window.location);
        url.searchParams.set('symbols', symbols.join(','));
        window.history.pushState({}, '', url);

        renderTags();
    }

    function removeSymbol(sym) {
        symbols = symbols.filter(s => s !== sym);
        
        const url = new URL(window.location);
        url.searchParams.set('symbols', symbols.join(','));
        window.history.pushState({}, '', url);

        renderTags();
    }

    // Handle Enter key
    document.getElementById('symbolInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') addSymbol();
    });

    async function fetchCompareData() {
        document.getElementById('loadingState').style.display = 'block';
        document.getElementById('compareContent').style.display = 'none';

        try {
            const res = await fetch(`/stock/compare-data?symbols=${symbols.join(',')}`);
            const data = await res.json();
            
            renderChart(data);
            renderTable(data);
            
            document.getElementById('loadingState').style.display = 'none';
            document.getElementById('compareContent').style.display = 'block';
        } catch (e) {
            console.error(e);
            alert('Lỗi tải dữ liệu. Vui lòng thử lại sau.');
            document.getElementById('loadingState').style.display = 'none';
        }
    }

    function renderChart(data) {
        const ctx = document.getElementById('compareChart').getContext('2d');
        
        if (chartInstance) {
            chartInstance.destroy();
        }

        // Convert timestamp to readable date (dd/mm/yyyy)
        const labels = data.length > 0 ? data[0].prices.map(p => {
            const d = new Date(p.time);
            return d.toLocaleDateString('vi-VN');
        }) : [];

        const datasets = data.map((stock, idx) => ({
            label: stock.symbol,
            data: stock.prices.map(p => p.percent),
            borderColor: colors[idx],
            backgroundColor: colors[idx] + '33', // with opacity
            borderWidth: 2,
            tension: 0.1,
            pointRadius: 0,
            pointHoverRadius: 6
        }));

        chartInstance = new Chart(ctx, {
            type: 'line',
            data: { labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Biểu đồ tăng trưởng (%)'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y + '%';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        title: { display: true, text: 'Tăng trưởng (%)' }
                    }
                }
            }
        });
    }

    function renderTable(data) {
        const tbody = document.getElementById('statsTableBody');
        tbody.innerHTML = data.map((stock, idx) => {
            const colorClass = stock.change_percent >= 0 ? 'text-success' : 'text-danger';
            const arrow = stock.change_percent >= 0 ? '↑' : '↓';
            
            return `
                <tr>
                    <td><strong style="color: ${colors[idx]}">${stock.symbol}</strong></td>
                    <td class="text-left">${stock.name}</td>
                    <td><b>${stock.latest_close.toLocaleString()}</b> đ</td>
                    <td class="${colorClass}"><b>${arrow} ${stock.change_percent}%</b></td>
                    <td>${stock.high.toLocaleString()} đ</td>
                    <td>${stock.low.toLocaleString()} đ</td>
                </tr>
            `;
        }).join('');
    }

    // Init
    if (symbols.length > 0) {
        renderTags();
    }
</script>
@endsection

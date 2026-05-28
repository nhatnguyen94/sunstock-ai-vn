function searchSymbol(symbol) {
    document.getElementById('symbol').value = symbol;
    document.querySelector('.search-section form').submit();
}

if (typeof rawData !== 'undefined' && rawData.length > 0) {
// Prepare full datasets
const candleSeriesFull = rawData.map(d => ({
    x: new Date(d.time),
    y: [parseFloat(d.open), parseFloat(d.high), parseFloat(d.low), parseFloat(d.close)]
}));
const lineSeries = rawData.map(d => [new Date(d.time).getTime(), parseFloat(d.close)]);
const volumeSeries = rawData.map(d => ({
    x: new Date(d.time),
    y: parseFloat(d.volume)
}));

let activeMonths = 0; // 0 = all

function filterByMonths(months) {
    if (!months) return { candle: candleSeriesFull, line: lineSeries, vol: volumeSeries };
    const cutoff = new Date();
    cutoff.setMonth(cutoff.getMonth() - months);
    return {
        candle: candleSeriesFull.filter(d => d.x >= cutoff),
        line: lineSeries.filter(d => d[0] >= cutoff.getTime()),
        vol: volumeSeries.filter(d => d.x >= cutoff)
    };
}

// ── ApexCharts: Candlestick ──
const candleOptions = {
    series: [{ name: 'Giá', data: candleSeriesFull }],
    chart: {
        type: 'candlestick',
        height: 480,
        toolbar: { show: true, tools: { download: true, selection: true, zoom: true, zoomin: true, zoomout: true, pan: true, reset: true } },
        animations: { enabled: true, easing: 'easeinout', speed: 600 },
        background: 'transparent',
        fontFamily: 'Inter, sans-serif',
    },
    plotOptions: {
        candlestick: {
            colors: { upward: '#10b981', downward: '#ef4444' },
            wick: { useFillColor: true }
        }
    },
    xaxis: {
        type: 'datetime',
        labels: {
            datetimeFormatter: { year: 'yyyy', month: "MM/yyyy", day: 'dd/MM' },
            style: { fontSize: '11px', colors: '#6b7280' }
        },
        axisBorder: { show: false },
        axisTicks: { show: false }
    },
    yaxis: {
        tooltip: { enabled: true },
        labels: {
            formatter: v => (v/1000).toFixed(0) + 'K',
            style: { fontSize: '11px', colors: '#6b7280' }
        }
    },
    tooltip: {
        theme: 'light',
        x: { format: 'dd/MM/yyyy' },
        y: {
            formatter: v => v ? Number(v).toLocaleString('vi-VN') + ' VNĐ' : ''
        }
    },
    grid: {
        borderColor: '#f3f4f6',
        strokeDashArray: 4
    }
};

// ── ApexCharts: Area Line ──
const lineOptions = {
    series: [{ name: 'Giá đóng cửa', data: lineSeries }],
    chart: {
        type: 'area',
        height: 480,
        toolbar: { show: true, tools: { download: true, selection: true, zoom: true, zoomin: true, zoomout: true, pan: true, reset: true } },
        animations: { enabled: true, easing: 'easeinout', speed: 800, animateGradually: { enabled: true, delay: 100 } },
        background: 'transparent',
        fontFamily: 'Inter, sans-serif',
    },
    stroke: { curve: 'smooth', width: 2.5, colors: ['#2563eb'] },
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.45,
            opacityTo: 0.0,
            stops: [0, 100],
            colorStops: [
                { offset: 0, color: '#2563eb', opacity: 0.4 },
                { offset: 100, color: '#2563eb', opacity: 0 }
            ]
        }
    },
    colors: ['#2563eb'],
    xaxis: {
        type: 'datetime',
        labels: {
            datetimeFormatter: { year: 'yyyy', month: "MM/yyyy", day: 'dd/MM' },
            style: { fontSize: '11px', colors: '#6b7280' }
        },
        axisBorder: { show: false },
        axisTicks: { show: false }
    },
    yaxis: {
        labels: {
            formatter: v => (v/1000).toFixed(0) + 'K',
            style: { fontSize: '11px', colors: '#6b7280' }
        }
    },
    tooltip: {
        theme: 'light',
        x: { format: 'dd/MM/yyyy' },
        y: { formatter: v => Number(v).toLocaleString('vi-VN') + ' VNĐ' }
    },
    grid: {
        borderColor: '#f3f4f6',
        strokeDashArray: 4
    },
    markers: { size: 0, hover: { size: 5 } },
    dataLabels: { enabled: false }
};

let candleChart = new ApexCharts(document.getElementById('apexCandleChart'), candleOptions);
let lineChart = new ApexCharts(document.getElementById('apexLineChart'), lineOptions);
candleChart.render();
lineChart.render();

// Toggle charts
document.getElementById('btnCandle').addEventListener('click', function() {
    document.getElementById('apexCandleChart').classList.add('active');
    document.getElementById('apexLineChart').classList.remove('active');
    this.classList.add('active');
    document.getElementById('btnLine').classList.remove('active');
    candleChart.updateOptions({}, false, true);
});
document.getElementById('btnLine').addEventListener('click', function() {
    document.getElementById('apexLineChart').classList.add('active');
    document.getElementById('apexCandleChart').classList.remove('active');
    this.classList.add('active');
    document.getElementById('btnCandle').classList.remove('active');
    lineChart.updateOptions({}, false, true);
});

// Period filter
document.querySelectorAll('.period-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const months = parseInt(this.dataset.months) || 0;
        const filtered = filterByMonths(months);
        candleChart.updateSeries([{ name: 'Giá', data: filtered.candle }]);
        lineChart.updateSeries([{ name: 'Giá đóng cửa', data: filtered.line }]);
    });
});

// Table
const pageSize = 20;
let currentPage = 1;

function renderTable(page) {
    currentPage = page;
    const start = (page - 1) * pageSize;
    const pageData = rawData.slice().reverse().slice(start, start + pageSize);
    const tbody = document.getElementById('priceTableBody');
    tbody.innerHTML = pageData.map(item => {
        const date = new Date(item.time).toLocaleDateString('vi-VN');
        const close = parseFloat(item.close);
        const open = parseFloat(item.open);
        const isUp = close >= open;
        return `<tr>
            <td style="font-weight:600;">${date}</td>
            <td>${Number(item.open).toLocaleString()}</td>
            <td class="price-positive">${Number(item.high).toLocaleString()}</td>
            <td class="price-negative">${Number(item.low).toLocaleString()}</td>
            <td style="font-weight:700;color:${isUp ? '#10b981' : '#ef4444'};">
                <i class="bi bi-${isUp ? 'arrow-up' : 'arrow-down'}"></i>
                ${Number(item.close).toLocaleString()}
            </td>
            <td class="volume-cell">${Number(item.volume).toLocaleString()}</td>
            <td><span class="badge badge-primary">${item.currency || 'VND'}</span></td>
        </tr>`;
    }).join('');
    renderPagination();
}

function renderPagination() {
    const totalPages = Math.ceil(rawData.length / pageSize);
    const p = document.getElementById('tablePagination');
    let h = '';
    if (currentPage > 1) h += `<li class="page-item"><a class="page-link" href="#" onclick="renderTable(${currentPage-1});return false;"><i class="bi bi-chevron-left"></i></a></li>`;
    const sp = Math.max(1, currentPage-2), ep = Math.min(totalPages, currentPage+2);
    if (sp > 1) { h += `<li class="page-item"><a class="page-link" href="#" onclick="renderTable(1);return false;">1</a></li>`; if (sp>2) h += `<li class="page-item disabled"><span class="page-link">...</span></li>`; }
    for (let i=sp;i<=ep;i++) h += `<li class="page-item${i===currentPage?' active':''}"><a class="page-link" href="#" onclick="renderTable(${i});return false;">${i}</a></li>`;
    if (ep < totalPages) { if (ep<totalPages-1) h += `<li class="page-item disabled"><span class="page-link">...</span></li>`; h += `<li class="page-item"><a class="page-link" href="#" onclick="renderTable(${totalPages});return false;">${totalPages}</a></li>`; }
    if (currentPage < totalPages) h += `<li class="page-item"><a class="page-link" href="#" onclick="renderTable(${currentPage+1});return false;"><i class="bi bi-chevron-right"></i></a></li>`;
    p.innerHTML = h;
}
renderTable(1);
} // end if rawData

// Awesomplete autocomplete
let awesomplete = new Awesomplete(document.getElementById('symbol'), { minChars: 1, maxItems: 15, autoFirst: true, list: [] });
let searchTimeout;
document.getElementById('symbol').addEventListener('input', function() {
    const val = this.value.trim();
    const notFoundMsg = document.getElementById('notFoundMsg');
    if (val.length < 1) { notFoundMsg.classList.remove('show'); return; }
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        fetch('/stocks-list?q=' + encodeURIComponent(val))
            .then(r => r.json())
            .then(data => {
                notFoundMsg.classList[data.length === 0 ? 'add' : 'remove']('show');
                awesomplete.list = data.map(item => ({ label: `<b>${item.symbol}</b><span>${item.name ? ' - '+item.name : ''}</span>`, value: item.symbol }));
            })
            .catch(() => notFoundMsg.classList.add('show'));
    }, 300);
});
document.getElementById('symbol').addEventListener('focus', () => document.getElementById('notFoundMsg').classList.remove('show'));
document.querySelector('.search-section form').addEventListener('submit', function() {
    const btn = this.querySelector('.search-btn');
    const btnText = btn.querySelector('.btn-text');
    const btnIcon = btn.querySelector('i');
    btn.disabled = true;
    if (btnIcon) btnIcon.className = 'loading-spinner';
    if (btnText) btnText.textContent = 'Đang tìm...';
    setTimeout(() => { btn.disabled = false; if (btnIcon) btnIcon.className = 'bi bi-search'; if (btnText) btnText.textContent = 'Tra cứu'; }, 5000);
});
document.addEventListener('keydown', e => { if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); document.getElementById('symbol').focus(); } });

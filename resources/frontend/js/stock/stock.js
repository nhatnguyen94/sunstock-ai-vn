function searchSymbol(symbol) {
    document.getElementById('symbol').value = symbol;
    document.querySelector('.search-section form').submit();
}

if (typeof rawData !== 'undefined' && rawData.length > 0) {

// â”€â”€â”€ BASE DATA â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const allCandles = rawData.map(d => ({
    x: new Date(d.time),
    y: [parseFloat(d.open), parseFloat(d.high), parseFloat(d.low), parseFloat(d.close)]
}));
const allCloses = rawData.map(d => ({ x: new Date(d.time), y: parseFloat(d.close) }));
const lineSeries = rawData.map(d => [new Date(d.time).getTime(), parseFloat(d.close)]);

let activeMonths = 0;

// â”€â”€â”€ INDICATOR MATH â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function calcSMA(pts, n) {
    return pts.map((p, i) => ({
        x: p.x,
        y: i < n - 1 ? null
            : parseFloat((pts.slice(i - n + 1, i + 1).reduce((s, c) => s + c.y, 0) / n).toFixed(2))
    }));
}

function calcEMAValues(values, n) {
    // values = array of numbers (may contain nulls)
    const k = 2 / (n + 1);
    const out = new Array(values.length).fill(null);
    const first = values.findIndex(v => v !== null);
    if (first < 0) return out;
    out[first] = values[first];
    for (let i = first + 1; i < values.length; i++) {
        const prev = out[i - 1] !== null ? out[i - 1] : values[i];
        out[i] = values[i] !== null
            ? parseFloat((values[i] * k + prev * (1 - k)).toFixed(4))
            : prev;
    }
    return out;
}

function calcBB(pts, n = 20, mult = 2) {
    const mid = calcSMA(pts, n);
    const upper = pts.map((p, i) => {
        if (mid[i].y === null) return { x: p.x, y: null };
        const slice = pts.slice(i - n + 1, i + 1).map(c => c.y);
        const std = Math.sqrt(slice.reduce((s, v) => s + (v - mid[i].y) ** 2, 0) / n);
        return { x: p.x, y: parseFloat((mid[i].y + mult * std).toFixed(2)) };
    });
    const lower = pts.map((p, i) => {
        if (mid[i].y === null) return { x: p.x, y: null };
        const slice = pts.slice(i - n + 1, i + 1).map(c => c.y);
        const std = Math.sqrt(slice.reduce((s, v) => s + (v - mid[i].y) ** 2, 0) / n);
        return { x: p.x, y: parseFloat((mid[i].y - mult * std).toFixed(2)) };
    });
    return { upper, mid, lower };
}

function calcRSI(pts, n = 14) {
    const out = pts.map(p => ({ x: p.x, y: null }));
    if (pts.length < n + 1) return out;
    let avgGain = 0, avgLoss = 0;
    for (let i = 1; i <= n; i++) {
        const d = pts[i].y - pts[i - 1].y;
        if (d > 0) avgGain += d; else avgLoss -= d;
    }
    avgGain /= n; avgLoss /= n;
    out[n].y = parseFloat((100 - 100 / (1 + avgGain / (avgLoss || 1e-10))).toFixed(2));
    for (let i = n + 1; i < pts.length; i++) {
        const d = pts[i].y - pts[i - 1].y;
        avgGain = (avgGain * (n - 1) + Math.max(0, d)) / n;
        avgLoss = (avgLoss * (n - 1) + Math.max(0, -d)) / n;
        out[i].y = parseFloat((100 - 100 / (1 + avgGain / (avgLoss || 1e-10))).toFixed(2));
    }
    return out;
}

function calcMACD(pts, fast = 12, slow = 26, signal = 9) {
    const prices = pts.map(p => p.y);
    const ema12v = calcEMAValues(prices, fast);
    const ema26v = calcEMAValues(prices, slow);
    const macdV  = prices.map((_, i) =>
        ema12v[i] !== null && ema26v[i] !== null
            ? parseFloat((ema12v[i] - ema26v[i]).toFixed(4)) : null);
    const sigV   = calcEMAValues(macdV, signal);
    return {
        macd:      pts.map((p, i) => ({ x: p.x, y: macdV[i] !== null ? parseFloat(macdV[i].toFixed(2)) : null })),
        signal:    pts.map((p, i) => ({ x: p.x, y: sigV[i]  !== null ? parseFloat(sigV[i].toFixed(2))  : null })),
        histogram: pts.map((p, i) => ({
            x: p.x,
            y: macdV[i] !== null && sigV[i] !== null ? parseFloat((macdV[i] - sigV[i]).toFixed(2)) : null
        }))
    };
}

// â”€â”€â”€ PRE-COMPUTE (full dataset) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const PRE = {
    ma20:  calcSMA(allCloses, 20),
    ma50:  calcSMA(allCloses, 50),
    ma200: calcSMA(allCloses, 200),
    bb:    calcBB(allCloses, 20, 2),
    rsi:   calcRSI(allCloses, 14),
    macd:  calcMACD(allCloses, 12, 26, 9),
};

// â”€â”€â”€ ACTIVE INDICATORS STATE â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const activeIndicators = new Set();

// â”€â”€â”€ DATE FILTER HELPER â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function getStartIndex(months) {
    if (!months) return 0;
    const cutoff = Date.now() - months * 30.5 * 24 * 3600000;
    const idx = rawData.findIndex(d => d.time >= cutoff);
    return idx >= 0 ? idx : 0;
}

// â”€â”€â”€ BUILD MAIN CHART SERIES â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function buildMainSeries(months) {
    const s = getStartIndex(months);
    const series = [{ name: 'GiÃ¡', type: 'candlestick', data: allCandles.slice(s) }];

    if (activeIndicators.has('MA20'))
        series.push({ name: 'MA20',     type: 'line', data: PRE.ma20.slice(s),    color: '#f59e0b' });
    if (activeIndicators.has('MA50'))
        series.push({ name: 'MA50',     type: 'line', data: PRE.ma50.slice(s),    color: '#3b82f6' });
    if (activeIndicators.has('MA200'))
        series.push({ name: 'MA200',    type: 'line', data: PRE.ma200.slice(s),   color: '#ec4899' });
    if (activeIndicators.has('BB')) {
        series.push({ name: 'BB Upper', type: 'line', data: PRE.bb.upper.slice(s), color: '#8b5cf6' });
        series.push({ name: 'BB Mid',   type: 'line', data: PRE.bb.mid.slice(s),   color: '#8b5cf680' });
        series.push({ name: 'BB Lower', type: 'line', data: PRE.bb.lower.slice(s), color: '#8b5cf6' });
    }
    return series;
}

// â”€â”€â”€ APEXCHARTS: CANDLESTICK â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const candleOptions = {
    series: [{ name: 'GiÃ¡', type: 'candlestick', data: allCandles }],
    chart: {
        id: 'mainChart',
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
    stroke: { width: [1, 1.5, 1.5, 1.5, 1, 1, 1] },
    legend: { show: false },
    xaxis: {
        type: 'datetime',
        labels: {
            datetimeFormatter: { year: 'yyyy', month: 'MM/yyyy', day: 'dd/MM' },
            style: { fontSize: '11px', colors: '#6b7280' }
        },
        axisBorder: { show: false },
        axisTicks: { show: false }
    },
    yaxis: {
        tooltip: { enabled: true },
        labels: {
            formatter: v => v ? (v / 1000).toFixed(0) + 'K' : '',
            style: { fontSize: '11px', colors: '#6b7280' }
        }
    },
    tooltip: {
        theme: 'light',
        x: { format: 'dd/MM/yyyy' },
        y: { formatter: v => v ? Number(v).toLocaleString('vi-VN') + ' VNÄ' : '' }
    },
    grid: { borderColor: '#f3f4f6', strokeDashArray: 4 }
};

// â”€â”€â”€ APEXCHARTS: AREA LINE â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const lineOptions = {
    series: [{ name: 'GiÃ¡ Ä‘Ã³ng cá»­a', data: lineSeries }],
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
            datetimeFormatter: { year: 'yyyy', month: 'MM/yyyy', day: 'dd/MM' },
            style: { fontSize: '11px', colors: '#6b7280' }
        },
        axisBorder: { show: false },
        axisTicks: { show: false }
    },
    yaxis: {
        labels: {
            formatter: v => (v / 1000).toFixed(0) + 'K',
            style: { fontSize: '11px', colors: '#6b7280' }
        }
    },
    tooltip: {
        theme: 'light',
        x: { format: 'dd/MM/yyyy' },
        y: { formatter: v => Number(v).toLocaleString('vi-VN') + ' VNÄ' }
    },
    grid: { borderColor: '#f3f4f6', strokeDashArray: 4 },
    markers: { size: 0, hover: { size: 5 } },
    dataLabels: { enabled: false }
};

// â”€â”€â”€ APEXCHARTS: RSI SUB-CHART â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const rsiOptions = {
    series: [{ name: 'RSI', data: [] }],
    chart: { type: 'line', height: 160, toolbar: { show: false }, background: 'transparent', fontFamily: 'Inter, sans-serif' },
    stroke: { width: 1.5, colors: ['#06b6d4'] },
    colors: ['#06b6d4'],
    xaxis: {
        type: 'datetime',
        labels: { datetimeFormatter: { day: 'dd/MM' }, style: { fontSize: '10px', colors: '#9ca3af' } },
        axisBorder: { show: false }, axisTicks: { show: false }
    },
    yaxis: {
        min: 0, max: 100,
        tickAmount: 4,
        labels: { formatter: v => v.toFixed(0), style: { fontSize: '10px', colors: '#9ca3af' } }
    },
    annotations: {
        yaxis: [
            { y: 70, borderColor: '#ef4444', borderWidth: 1, strokeDashArray: 4, label: { text: '70', style: { color: '#ef4444', fontSize: '10px', background: 'transparent' } } },
            { y: 30, borderColor: '#10b981', borderWidth: 1, strokeDashArray: 4, label: { text: '30', style: { color: '#10b981', fontSize: '10px', background: 'transparent' } } },
        ]
    },
    tooltip: { theme: 'light', x: { format: 'dd/MM/yyyy' }, y: { formatter: v => v !== null ? v.toFixed(2) : '' } },
    grid: { borderColor: '#f3f4f6', strokeDashArray: 4 },
    dataLabels: { enabled: false },
    legend: { show: false }
};

// â”€â”€â”€ APEXCHARTS: MACD SUB-CHART â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const macdOptions = {
    series: [
        { name: 'MACD',      type: 'line', data: [] },
        { name: 'Signal',    type: 'line', data: [] },
        { name: 'Histogram', type: 'bar',  data: [] },
    ],
    chart: { type: 'line', height: 180, toolbar: { show: false }, background: 'transparent', fontFamily: 'Inter, sans-serif' },
    stroke: { width: [1.5, 1.5, 0] },
    colors: ['#10b981', '#f59e0b', '#6b7280'],
    plotOptions: { bar: { colors: { ranges: [{ from: -1e9, to: 0, color: '#ef4444' }, { from: 0, to: 1e9, color: '#10b981' }] } } },
    xaxis: {
        type: 'datetime',
        labels: { datetimeFormatter: { day: 'dd/MM' }, style: { fontSize: '10px', colors: '#9ca3af' } },
        axisBorder: { show: false }, axisTicks: { show: false }
    },
    yaxis: { labels: { formatter: v => v !== null ? v.toFixed(2) : '', style: { fontSize: '10px', colors: '#9ca3af' } } },
    tooltip: { theme: 'light', x: { format: 'dd/MM/yyyy' } },
    grid: { borderColor: '#f3f4f6', strokeDashArray: 4 },
    dataLabels: { enabled: false },
    legend: { show: true, fontSize: '11px', position: 'top', horizontalAlign: 'left', offsetY: -5, itemMargin: { horizontal: 8 } }
};

// â”€â”€â”€ RENDER CHARTS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
let candleChart = new ApexCharts(document.getElementById('apexCandleChart'), candleOptions);
let lineChart   = new ApexCharts(document.getElementById('apexLineChart'),   lineOptions);
let rsiChart    = null;
let macdChart   = null;
candleChart.render();
lineChart.render();

// â”€â”€â”€ CHART TYPE TOGGLE â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
document.getElementById('btnCandle').addEventListener('click', function () {
    document.getElementById('apexCandleChart').classList.add('active');
    document.getElementById('apexLineChart').classList.remove('active');
    document.getElementById('indicatorToolbar').style.display = '';
    this.classList.add('active');
    document.getElementById('btnLine').classList.remove('active');
    candleChart.updateOptions({}, false, true);
});
document.getElementById('btnLine').addEventListener('click', function () {
    document.getElementById('apexLineChart').classList.add('active');
    document.getElementById('apexCandleChart').classList.remove('active');
    document.getElementById('indicatorToolbar').style.display = 'none';
    this.classList.add('active');
    document.getElementById('btnCandle').classList.remove('active');
    lineChart.updateOptions({}, false, true);
});

// â”€â”€â”€ REFRESH SUB-CHARTS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function refreshSubCharts(months) {
    const s = getStartIndex(months);
    if (rsiChart)  rsiChart.updateSeries([{ name: 'RSI', data: PRE.rsi.slice(s) }]);
    if (macdChart) macdChart.updateSeries([
        { name: 'MACD',      type: 'line', data: PRE.macd.macd.slice(s) },
        { name: 'Signal',    type: 'line', data: PRE.macd.signal.slice(s) },
        { name: 'Histogram', type: 'bar',  data: PRE.macd.histogram.slice(s) },
    ]);
}

// â”€â”€â”€ PERIOD FILTER â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
document.querySelectorAll('.period-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        activeMonths = parseInt(this.dataset.months) || 0;
        const s = getStartIndex(activeMonths);
        // Main charts
        candleChart.updateOptions({ series: buildMainSeries(activeMonths) }, false, false);
        lineChart.updateSeries([{ name: 'GiÃ¡ Ä‘Ã³ng cá»­a', data: lineSeries.slice(s) }]);
        // Sub-charts
        refreshSubCharts(activeMonths);
    });
});

// â”€â”€â”€ INDICATOR TOGGLES â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function handleIndicatorToggle(name, checked) {
    if (checked) activeIndicators.add(name); else activeIndicators.delete(name);

    // RSI â€” separate sub-chart
    if (name === 'RSI') {
        const wrapper = document.getElementById('rsiChartWrapper');
        if (checked) {
            wrapper.style.display = '';
            if (!rsiChart) {
                const s = getStartIndex(activeMonths);
                rsiChart = new ApexCharts(document.getElementById('rsiChart'),
                    Object.assign({}, rsiOptions, { series: [{ name: 'RSI', data: PRE.rsi.slice(s) }] }));
                rsiChart.render();
            } else {
                refreshSubCharts(activeMonths);
            }
        } else {
            wrapper.style.display = 'none';
        }
        return;
    }

    // MACD â€” separate sub-chart
    if (name === 'MACD') {
        const wrapper = document.getElementById('macdChartWrapper');
        if (checked) {
            wrapper.style.display = '';
            const s = getStartIndex(activeMonths);
            if (!macdChart) {
                const opts = JSON.parse(JSON.stringify(macdOptions));
                opts.series[0].data = PRE.macd.macd.slice(s);
                opts.series[1].data = PRE.macd.signal.slice(s);
                opts.series[2].data = PRE.macd.histogram.slice(s);
                macdChart = new ApexCharts(document.getElementById('macdChart'), opts);
                macdChart.render();
            } else {
                refreshSubCharts(activeMonths);
            }
        } else {
            wrapper.style.display = 'none';
        }
        return;
    }

    // Overlay indicators (MA20/50/200, BB) â€” rebuild main chart series
    candleChart.updateOptions({ series: buildMainSeries(activeMonths) }, false, false);
}

document.getElementById('indMA20').addEventListener('change',  e => handleIndicatorToggle('MA20',  e.target.checked));
document.getElementById('indMA50').addEventListener('change',  e => handleIndicatorToggle('MA50',  e.target.checked));
document.getElementById('indMA200').addEventListener('change', e => handleIndicatorToggle('MA200', e.target.checked));
document.getElementById('indBB').addEventListener('change',    e => handleIndicatorToggle('BB',    e.target.checked));
document.getElementById('indRSI').addEventListener('change',   e => handleIndicatorToggle('RSI',   e.target.checked));
document.getElementById('indMACD').addEventListener('change',  e => handleIndicatorToggle('MACD',  e.target.checked));

// â”€â”€â”€ DATA TABLE â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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
        const open  = parseFloat(item.open);
        const isUp  = close >= open;
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
    if (currentPage > 1) h += `<li class="page-item"><a class="page-link" href="#" data-page="${currentPage - 1}"><i class="bi bi-chevron-left"></i></a></li>`;
    const sp = Math.max(1, currentPage - 2), ep = Math.min(totalPages, currentPage + 2);
    if (sp > 1) { h += `<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`; if (sp > 2) h += `<li class="page-item disabled"><span class="page-link">...</span></li>`; }
    for (let i = sp; i <= ep; i++) h += `<li class="page-item${i === currentPage ? ' active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
    if (ep < totalPages) { if (ep < totalPages - 1) h += `<li class="page-item disabled"><span class="page-link">...</span></li>`; h += `<li class="page-item"><a class="page-link" href="#" data-page="${totalPages}">${totalPages}</a></li>`; }
    if (currentPage < totalPages) h += `<li class="page-item"><a class="page-link" href="#" data-page="${currentPage + 1}"><i class="bi bi-chevron-right"></i></a></li>`;
    p.innerHTML = h;
}
renderTable(1);

// Pagination event delegation
document.getElementById('tablePagination').addEventListener('click', function (e) {
    const link = e.target.closest('[data-page]');
    if (!link) return;
    e.preventDefault();
    renderTable(parseInt(link.dataset.page));
});

} // end if rawData

// ─── FINANCE SECTION ─────────────────────────────────────────────────────────
(function () {
    const btnLoad = document.getElementById('btnLoadFinance');
    if (!btnLoad) return;

    let finType   = 'income';
    let finPeriod = 'quarter';
    let loaded    = false;

    function formatFinVal(val) {
        if (val === null || val === undefined) return '<span style="color:#d1d5db;">—</span>';
        const num = parseFloat(val);
        if (isNaN(num)) return String(val);
        const abs = Math.abs(num);
        let formatted;
        if (abs >= 1e9)       formatted = (num / 1e9).toFixed(1) + ' tỷ';
        else if (abs >= 1e6)  formatted = (num / 1e6).toFixed(1) + ' tr';
        else if (abs >= 1e3)  formatted = (num / 1e3).toFixed(1) + 'K';
        else                  formatted = num.toLocaleString('vi-VN', { maximumFractionDigits: 2 });
        return `<span style="color:${num < 0 ? '#ef4444' : 'inherit'}">${formatted}</span>`;
    }

    function renderFinanceTable(data, periods) {
        if (!data || !data.length) {
            return '<p style="color:#9ca3af;padding:1.5rem;">Không có dữ liệu.</p>';
        }
        const cols = periods.slice(-8).reverse();
        let html = '<div class="table-responsive"><table class="table data-table"><thead><tr>';
        html += '<th style="min-width:200px;">Chỉ tiêu</th>';
        cols.forEach(c => { html += `<th style="text-align:right;font-size:0.85rem;">${c}</th>`; });
        html += '</tr></thead><tbody>';

        data.forEach(row => {
            const isHeader = row.levels === 1;
            const rowStyle = isHeader ? 'font-weight:700;background:#f8fafc;color:#1e3a5f;' : '';
            const cellStyle = isHeader ? 'font-weight:700;' : 'padding-left:2rem;color:#374151;';
            html += `<tr style="${rowStyle}">`;
            html += `<td style="${cellStyle}">${row.item || ''}</td>`;
            cols.forEach(c => {
                html += `<td style="text-align:right;font-size:0.88rem;">${formatFinVal(row[c])}</td>`;
            });
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        html += `<p style="font-size:0.78rem;color:#9ca3af;margin-top:8px;"><i class="bi bi-info-circle"></i> Đơn vị: triệu VNĐ &nbsp;|&nbsp; Hiển thị ${cols.length} kỳ gần nhất</p>`;
        return html;
    }

    function loadFinance() {
        const symbol = (typeof stockSymbol !== 'undefined') ? stockSymbol : '';
        if (!symbol) return;
        const body = document.getElementById('financeBody');
        body.innerHTML = '<div style="text-align:center;padding:2rem;color:#6b7280;"><i class="bi bi-hourglass-split" style="font-size:2rem;"></i><p style="margin-top:12px;">Đang tải dữ liệu tài chính...</p></div>';

        fetch(`/stock/finance?symbol=${encodeURIComponent(symbol)}&type=${finType}&period=${finPeriod}`)
            .then(r => r.json())
            .then(json => {
                if (json.error) {
                    body.innerHTML = `<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> ${json.error}</div>`;
                    return;
                }
                body.innerHTML = renderFinanceTable(json.data || [], json.periods || []);
            })
            .catch(() => {
                body.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle"></i> Lỗi kết nối. Vui lòng thử lại.</div>';
            });
    }

    btnLoad.addEventListener('click', function () {
        if (!loaded) {
            loaded = true;
            document.getElementById('financeTabBar').style.display = '';
            this.closest('div').style.display = 'none';
        }
        loadFinance();
    });

    document.addEventListener('click', function (e) {
        const typeBtn   = e.target.closest('.fin-type-btn');
        const periodBtn = e.target.closest('.fin-period-btn');
        if (typeBtn && document.getElementById('financeTabBar')) {
            finType = typeBtn.dataset.type;
            document.querySelectorAll('.fin-type-btn').forEach(b => b.classList.toggle('active', b === typeBtn));
            loadFinance();
        }
        if (periodBtn && document.getElementById('financeTabBar')) {
            finPeriod = periodBtn.dataset.period;
            document.querySelectorAll('.fin-period-btn').forEach(b => b.classList.toggle('active', b === periodBtn));
            loadFinance();
        }
    });
})();

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
    if (btnText) btnText.textContent = 'Äang tÃ¬m...';
    setTimeout(() => { btn.disabled = false; if (btnIcon) btnIcon.className = 'bi bi-search'; if (btnText) btnText.textContent = 'Tra cá»©u'; }, 5000);
});
document.addEventListener('keydown', e => { if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); document.getElementById('symbol').focus(); } });

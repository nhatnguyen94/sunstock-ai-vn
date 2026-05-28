# Phân tích Định lượng & Backtest (Quant & Backtesting)

Nhóm tính năng này đặc biệt được thiết kế cho các AI Agent thực hiện nhiệm vụ mô phỏng giao dịch (Trading Simulator) hoặc đánh giá chiến lược định lượng. Nó cho phép kết xuất hình ảnh đường cong lợi nhuận (Equity Curve) và chi tiết từng lệnh mua/bán (Backtest).

---

## 1. Đường cong Lợi nhuận & Drawdown (Equity Curve)

Vẽ đồ thị kép bao gồm: Đường cong giá trị tài sản (Equity Curve) ở phía trên và Biểu đồ sụt giảm tài sản (Drawdown) ở phía dưới. Có thể so sánh trực quan với đường Benchmark (ví dụ: VN-Index).

**Cú pháp:**
```python
Chart.equity_curve(data, benchmark=None, title='Equity Curve & Drawdown', figsize=(10, 6), **kwargs) -> Tuple[plt.Figure, plt.Axes, plt.Axes]
```

**Tham số chính:**
- `data` (pd.Series | pd.DataFrame): Dữ liệu giá trị danh mục theo thời gian.
- `benchmark` (pd.Series | pd.DataFrame | None): Dữ liệu giá trị tham chiếu để so sánh.
- `title` (str): Tiêu đề đồ thị.
- `**kwargs`: Các tham số định dạng tuỳ biến từ `Chart.apply_chart_style()`.

**Ví dụ:**
```python
import pandas as pd
from vnstock_ezchart import Chart

# Dữ liệu mô phỏng
dates = pd.date_range('2023-01-01', periods=100)
portfolio_values = pd.Series([100000 * (1.002 ** i) for i in range(100)], index=dates)
vnindex_values = pd.Series([100000 * (1.001 ** i) for i in range(100)], index=dates)

fig, ax_eq, ax_dd = Chart.equity_curve(
    data=portfolio_values,
    benchmark=vnindex_values,
    title="Đường cong Lợi nhuận & Drawdown so với VN-Index"
)
```

---

## 2. Trực quan hoá Backtest Toàn diện (Backtest Visualization)

Hàm chuyên dụng để kết xuất một khung nhìn toàn cảnh về quá trình chạy chiến lược thuật toán. Nó vẽ đồ thị nến Nhật làm nền, hiển thị khối lượng (volume), đè các đường MA (overlays), và đánh dấu trực tiếp các điểm Mua (Tam giác xanh hướng lên) / Bán (Tam giác đỏ hướng xuống) lên đồ thị.

> **Đặc điểm "Institutional-grade":** Bố cục này được mô phỏng theo chuẩn của các phần mềm backtest chuyên nghiệp thế giới, tối ưu hoá không gian để người dùng có thể đối chiếu ngay lập tức việc vào lệnh có khớp với tín hiệu kỹ thuật hay không.

**Cú pháp:**
```python
Chart.backtest(data, trades=None, portfolio=None, title='Backtest Results', figsize=(14, 10), volume=True, overlays=None, **kwargs) -> Tuple[plt.Figure, plt.Axes]
```

**Tham số chính:**
- `data` (pd.DataFrame): Dữ liệu giá (OHLCV) của cổ phiếu được backtest.
- `trades` (pd.DataFrame | list | np.ndarray): Lịch sử giao dịch (Danh sách các lệnh). Khuyến nghị sử dụng cấu trúc có các trường `['time', 'price', 'direction']` (trong đó direction là `1` cho Mua, `-1` cho Bán).
- `portfolio` (pd.DataFrame): Tuỳ chọn bổ sung, cung cấp dữ liệu giá trị danh mục theo thời gian để vẽ thêm một đồ thị phụ bên dưới.
- `volume` (bool): Hiển thị thanh khoản ở trục dưới đồ thị chính.
- `overlays` (list): Các cột chỉ báo kỹ thuật để vẽ đè lên đồ thị giá (VD: `['SMA_20', 'SMA_50']`).

**Ví dụ:**
```python
# Giả sử 'df' chứa dữ liệu OHLC và cột SMA_20
# 'trades_df' là DataFrame chứa cột 'time', 'price', 'direction' (1: Mua, -1: Bán)

fig, ax = Chart.backtest(
    data=df,
    trades=trades_df,
    title="Kết quả Backtest Thuật toán Giao cắt SMA",
    volume=True,
    overlays=['SMA_20']
)
```

---
**Tài nguyên Tham khảo:**  
Mã nguồn ví dụ sinh đồ thị Backtest và Equity Curve (từ quá trình sinh dữ liệu ngẫu nhiên hoặc mô phỏng) được đặt trong [Github: docs/examples/07_backtest.py](https://github.com/vnstock-hq/vnstock_ezchart/blob/main/docs/examples/07_backtest.py) và [Github: docs/examples/01_performance_risk.py](https://github.com/vnstock-hq/vnstock_ezchart/blob/main/docs/examples/01_performance_risk.py). AI Agent nên tham chiếu các file này để hiểu định dạng đầu vào chuẩn xác nhất cho `trades`.

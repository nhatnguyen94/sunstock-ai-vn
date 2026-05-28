# Biểu đồ Tài chính & Cổ phiếu (Financial Charts)

Các hàm trong tài liệu này thuộc nhóm `FinancialMixin` và một phần của `SpecializedMixin`, được thiết kế đặc thù cho việc hiển thị dữ liệu giá cổ phiếu, phân tích kỹ thuật và tóm tắt chỉ số tài chính.

## 1. Biểu đồ Nến Nhật (Candlestick)

Vẽ biểu đồ nến Nhật kèm theo thanh khoản (volume) và có thể thêm các đường chỉ báo kỹ thuật (overlays) hoặc đồ thị phụ (subplots) như RSI, MACD. Đây là biểu đồ quan trọng nhất trong phân tích kỹ thuật.

**Cú pháp:**
```python
Chart.candle(data, title='', figsize=(12, 8), volume=True, overlays=None, subplots=None, **kwargs) -> Tuple[plt.Figure, plt.Axes]
```
*(Lưu ý: `candlestick` là hàm alias của `candle`, có thể dùng thay thế cho nhau).*

**Tham số chính:**
- `data` (pd.DataFrame): Dữ liệu giá cổ phiếu. Bắt buộc phải có index là Datetime và các cột `Open`, `High`, `Low`, `Close`. Cột `Volume` bắt buộc nếu `volume=True`.
- `title` (str): Tiêu đề đồ thị.
- `volume` (bool): Có hiển thị đồ thị cột khối lượng giao dịch phía dưới hay không (Mặc định: `True`).
- `overlays` (list): Danh sách các cột phụ (ví dụ: đường SMA) trong `data` để vẽ đè lên đồ thị giá chính.
- `subplots` (list): Danh sách các cột (ví dụ: RSI, MACD) trong `data` để vẽ thành các đồ thị phụ tách biệt ở phía dưới đồ thị giá.

**Ví dụ cơ bản:**
```python
import pandas as pd
from vnstock_ezchart import Chart
from vnstock import Quote

# Lấy dữ liệu VNSTOCK (Free)
quote = Quote("vci", "SSI")
df = quote.history("2023-01-01", "2023-12-31")
df.set_index('time', inplace=True)
df.rename(columns={'open': 'Open', 'high': 'High', 'low': 'Low', 'close': 'Close', 'volume': 'Volume'}, inplace=True)

fig, ax = Chart.candle(
    data=df, 
    title="Biểu đồ nến cổ phiếu SSI", 
    volume=True
)
```

**Ví dụ Nâng cao (có overlays & subplots):**
```python
# Giả sử df đã có thêm các cột 'SMA20', 'SMA50' và 'RSI'
fig, ax = Chart.candle(
    data=df,
    title="Phân tích Kỹ thuật Nâng cao SSI",
    overlays=['SMA20', 'SMA50'],
    subplots=['RSI']
)
```

---

## 2. Thẻ Tóm tắt Cổ phiếu (Summary Card)

Tạo một thẻ hình ảnh trực quan dạng UI (như các nền tảng tài chính) hiển thị các thông tin tóm tắt về một mã cổ phiếu.

**Cú pháp:**
```python
Chart.summary_card(ticker, company_name, current_price, price_change, price_change_pct, metrics, sparkline_data, signal, **kwargs)
```

**Tham số chính:**
- `ticker` (str): Mã cổ phiếu (VD: `"FPT"`).
- `company_name` (str): Tên công ty đầy đủ.
- `current_price` (float): Giá hiện tại.
- `price_change` (float): Mức thay đổi giá tuyệt đối.
- `price_change_pct` (float): Phần trăm thay đổi giá.
- `metrics` (dict): Từ điển chứa các chỉ số cơ bản (VD: `{"P/E": "15.2", "P/B": "2.1", "Vốn hoá": "100K Tỷ"}`).
- `sparkline_data` (pd.Series): Dữ liệu chuỗi thời gian ngắn để vẽ đồ thị đường siêu nhỏ (sparkline) thể hiện xu hướng giá.
- `signal` (str): Tín hiệu định giá hoặc kỹ thuật (VD: `"TÍCH CỰC"`, `"TIÊU CỰC"`, `"TRUNG LẬP"`). Màu sắc của thẻ sẽ thay đổi tương ứng theo tín hiệu này.

**Ví dụ:**
```python
# Dữ liệu mẫu cho sparkline
sparkline_prices = pd.Series([80, 81, 79, 82, 85, 84, 88])

fig, ax = Chart.summary_card(
    ticker="FPT",
    company_name="CTCP FPT",
    current_price=88000,
    price_change=4000,
    price_change_pct=4.76,
    metrics={"P/E": "18.5", "ROE": "25%", "Vốn hoá": "110K Tỷ"},
    sparkline_data=sparkline_prices,
    signal="TÍCH CỰC"
)
```

---
**Tài nguyên Tham khảo:**  
Các báo cáo phân tích, Sổ lệnh (Orderbook) hay Giao dịch khối ngoại (Foreign Trade) được minh hoạ bằng việc kết hợp linh hoạt màu sắc trong các biểu đồ cơ bản (như Bar Chart). Hãy tham khảo [Github: docs/examples/04_market_data.py](https://github.com/vnstock-hq/vnstock_ezchart/blob/main/docs/examples/04_market_data.py) và [Github: docs/examples/06_summary_cards.py](https://github.com/vnstock-hq/vnstock_ezchart/blob/main/docs/examples/06_summary_cards.py) để có cấu trúc mã nguồn hoàn chỉnh.

# Layer 2: Market Data (Dữ Liệu Giao Dịch Thị Trường)

## 📌 Tổng Quan

**Market Layer** cung cấp dữ liệu **realtime & historical** về giá, khối lượng, vốn hóa, thanh khoản ngay từ các sàn giao dịch và data providers. Đây là dữ liệu **thay đổi liên tục** và phục vụ cho trading, phân tích kỹ thuật, và monitoring portfolio.

## 🏗️ Cấu Trúc Domain

```python
Market()
├── .equity(symbol)        # Thị trường cổ phiếu
├── .index(symbol)         # Thị trường chỉ số
├── .futures(symbol)       # Thị trường hợp đồng tương lai
├── .warrant(symbol)       # Thị trường chứng quyền
├── .etf(symbol)           # Thị trường ETF
├── .fund(symbol)          # Thị trường quỹ đầu tư mở
├── .crypto(symbol)        # Tiền mã hoá
├── .forex(symbol)         # Ngoại hối
├── .commodity(symbol)     # Hàng hoá quốc tế
└── .quote(symbols_list)   # Bảng giá nhiều mã
```

## 📋 Chi Tiết Các Domain

### 1. Equity Market (Thị Trường Cổ Phiếu)

**Nguồn chính:** KBS (kbs), VCI (vci)  
**Registry Key:** `"market.equity"`

#### Phương Thức

| Method | Mô Tả | Return |
|--------|------|--------|
| `ohlcv()` | Giá OHLCV lịch sử | DataFrame |
| `trade_history()` | Lịch sử thống kê giao dịch (giá, khối lượng, giá trị) | DataFrame |
| `trades()` | Lệnh giao dịch chi tiết (Time & Sales) | DataFrame |
| `order_book()` | Cấp độ mua/bán | DataFrame |
| `quote()` | Giá hiện tại / Bảng giá | DataFrame |
| `session_stats()` | Thống kê phiên giao dịch | DataFrame |
| `foreign_flow()` | Dòng tiền nước ngoài | DataFrame |
| `proprietary_flow()` | Dòng tiền tự doanh | DataFrame |
| `block_trades()` | Giao dịch thỏa thuận | DataFrame |
| `odd_lot()` | Giao dịch lô lẻ | DataFrame |
| `volume_profile()` | Phân bố khối lượng theo giá | DataFrame |
| `summary()` | Tổng hợp thông tin cổ phiếu | DataFrame |

#### Ví Dụ

```python
from vnstock_data import Market

mkt = Market()

# ===== OHLCV Data (Giá & Khối lượng) =====
df_ohlc = mkt.equity("VIC").ohlcv(
    start="2026-02-01",
    end="2026-03-01"
)
print(df_ohlc)
# Columns: ['time', 'open', 'high', 'low', 'close', 'volume']

# ===== Lịch sử Thống kê Giao dịch =====
history_stats = mkt.equity("VIC").trade_history(
    start="2026-02-01",
    end="2026-03-01"
)
print(history_stats)

# ===== Intraday Trades (Chi tiết lệnh) =====
df_trades = mkt.equity("TCB").trades()
print(df_trades)

# ===== Order Book (Cấp độ mua/bán) =====
df_orderbook = mkt.equity("VNM").order_book()
print(df_orderbook)

# ===== Quote (Bảng giá) =====
quote = mkt.equity("HPG").quote()
print(quote)

# ===== Thống kê Phiên (session_stats) =====
session = mkt.equity("VIC").session_stats()
print(session)

# ===== Tổng hợp Cổ phiếu (summary) =====
summary_info = mkt.equity("VIC").summary()
print(summary_info)

# ===== Dòng tiền Nước ngoài =====
foreign = mkt.equity("VIC").foreign_flow()
print(foreign)

# ===== Dòng tiền Tự doanh =====
proprietary = mkt.equity("VIC").proprietary_flow()
print(proprietary)

# ===== Giao dịch Thỏa thuận =====
blocks = mkt.equity("VIC").block_trades()
print(blocks)

# ===== Giao dịch Lô lẻ =====
odds = mkt.equity("VIC").odd_lot()
print(odds)

# ===== Volume Profile =====
vol_profile = mkt.equity("VJC").volume_profile()
print(vol_profile)
```

---

### 2. Index Market (Thị Trường Chỉ Số)

**Nguồn:** KBS (kbs)  
**Registry Key:** `"market.index"`

#### Phương Thức

| Method | Mô Tả | Return |
|--------|------|--------|
| `ohlcv()` | Điểm chỉ số lịch sử | DataFrame |
| `quote()` | Điểm chỉ số hiện tại | DataFrame |
| `summary()` | Tổng hợp chỉ số | DataFrame |

#### Ví Dụ

```python
from vnstock_data import Market

mkt = Market()

# Lịch sử điểm VNIndex
df_vnindex = mkt.index("VNINDEX").ohlcv(
    start="2026-01-01",
    end="2026-03-01"
)
print(df_vnindex)
# Columns: ['time', 'open', 'high', 'low', 'close', 'volume']

# Điểm hiện tại
quote_index = mkt.index("VNINDEX").quote()
print(quote_index)

# Tổng hợp chỉ số
summary_index = mkt.index("VNINDEX").summary()
print(summary_index)
```

---

### 3. Futures Market (Thị Trường Hợp Đồng Tương Lai)

**Nguồn:** KBS (kbs)  
**Registry Key:** `"market.futures"`

#### Phương Thức

| Method | Mô Tả | Return |
|--------|------|--------|
| `ohlcv()` | Giá hợp đồng lịch sử | DataFrame |
| `quote()` | Giá hiện tại | DataFrame |
| `trades()` | Giao dịch chi tiết | DataFrame |
| `order_book()` | Cấp độ mua/bán | DataFrame |
| `summary()` | Thông tin hợp đồng | DataFrame |

#### Ví Dụ

```python
from vnstock_data import Market

mkt = Market()

# Lịch sử VN30F (truy cập trực tiếp, KHÔNG qua derivatives)
df_vn30f = mkt.futures("VN30F2503").ohlcv(
    start="2026-02-01",
    end="2026-03-01"
)
print(df_vn30f)

# Giá hiện tại
quote_vn30f = mkt.futures("VN30F2503").quote()
print(quote_vn30f)

# Lệnh giao dịch chi tiết
trades_vn30f = mkt.futures("VN30F2503").trades()

# Cấp độ mua/bán
orderbook_vn30f = mkt.futures("VN30F2503").order_book()

# Tổng hợp hợp đồng
summary_vn30f = mkt.futures("VN30F2503").summary()
```

---

### 4. Warrant Market (Thị Trường Chứng Quyền)

**Nguồn:** KBS (kbs)  
**Registry Key:** `"market.warrant"`

#### Phương Thức

| Method | Mô Tả | Return |
|--------|------|--------|
| `ohlcv()` | Giá chứng quyền lịch sử | DataFrame |
| `quote()` | Giá hiện tại | DataFrame |
| `trades()` | Giao dịch chi tiết | DataFrame |
| `order_book()` | Cấp độ mua/bán | DataFrame |
| `summary()` | Thông tin chứng quyền | DataFrame |

#### Ví Dụ

```python
from vnstock_data import Market

mkt = Market()

# Lịch sử giá warrant (truy cập trực tiếp)
df_warrant = mkt.warrant("CACB2511").ohlcv(
    start="2026-02-01",
    end="2026-03-01"
)
print(df_warrant)

# Giá hiện tại warrant
quote_warrant = mkt.warrant("CACB2511").quote()
print(quote_warrant)

# Lệnh giao dịch chi tiết
trades_warrant = mkt.warrant("CACB2511").trades()

# Cấp độ mua/bán
orderbook_warrant = mkt.warrant("CACB2511").order_book()

# Tổng hợp chứng quyền
summary_warrant = mkt.warrant("CACB2511").summary()
```

---

### 5. ETF Market (Thị Trường ETF)

**Nguồn:** KBS (kbs), VCI (vci)  
**Registry Key:** `"market.etf"`

#### Phương Thức

Giống Equity Market (đầy đủ): `ohlcv()`, `trades()`, `order_book()`, `quote()`, `session_stats()`, `foreign_flow()`, `proprietary_flow()`, `block_trades()`, `odd_lot()`, `volume_profile()`, `summary()`.

#### Ví Dụ

```python
from vnstock_data import Market

mkt = Market()

# Lịch sử giá ETF
df_etf = mkt.etf("E1VFVN30").ohlcv(
    start="2026-02-01",
    end="2026-03-01"
)
print(df_etf)

# Giá hiện tại
quote_etf = mkt.etf("E1VFVN30").quote()
print(quote_etf)
```

---

### 6. Fund Market (Thị Trường Quỹ Đầu Tư Mở)

**Nguồn:** FMarket (fmarket)  
**Registry Key:** `"market.fund"`

#### Phương Thức

| Method | Mô Tả | Return |
|--------|------|--------|
| `history()` | Lịch sử NAV quỹ | DataFrame |
| `top_holding()` | Top cổ phiếu nắm giữ | DataFrame |
| `industry_holding()` | Nắm giữ theo ngành | DataFrame |
| `asset_holding()` | Nắm giữ theo loại tài sản | DataFrame |

#### Ví Dụ

```python
from vnstock_data import Market

mkt = Market()

# Lịch sử NAV quỹ
df_nav = mkt.fund("VFIBS").history()
print(df_nav)

# Top cổ phiếu trong quỹ
top_holding = mkt.fund("VFIBS").top_holding()
print(top_holding)

# Nắm giữ theo ngành
industry = mkt.fund("VFIBS").industry_holding()
print(industry)
```

---

### 7. Market Wide (Bảng Giá Nhiều Mã)

**Nguồn:** KBS (kbs)

#### Phương Thức

| Method | Tham Số | Mô Tả | Return |
|--------|---------|------|--------|
| `quote()` | `symbols_list` | Giá nhiều mã cùng lúc | DataFrame |

#### Ví Dụ

```python
from vnstock_data import Market

mkt = Market()

# Bảng giá nhiều mã cùng lúc
df_quotes = mkt.quote(["VIC", "TCB", "HPG", "VNM"])
print(df_quotes)
```

---

### 8. Thị Trường Quốc Tế (International Market)

#### Crypto Market (Thị Trường Tiền Mã Hóa)

**Nguồn chính:** Binance (Spot Trading API)  
**Registry Key:** `"market.crypto"`

Dữ liệu Crypto Market được liên kết trực tiếp từ **Binance Spot API**. Hỗ trợ truy xuất OHLCV theo khung thời gian (interval) tuỳ chỉnh, Orderbook Horizontal chuẩn hoá đa cấp độ (L1-L10), lịch sử giao dịch (Intraday Trades với cơ chế map Taker/Maker) và báo giá tổng hợp 24h.

| Method | Mô Tả | Return |
|--------|------|--------|
| `ohlcv()` | Giá lịch sử Klines đa khung thời gian (1m, 1h, 1d) | DataFrame |
| `quote()` | Báo giá ticker lũy kế 24 giờ dạng Snapshot | DataFrame |
| `intraday()` | Khớp lệnh chi tiết gần nhất (Time & Sales) | DataFrame |
| `order_book()` | Sổ lệnh L1 - L10 (được dàn đều theo chiều ngang) | DataFrame |
| `trade_history()` | Truy vấn lịch sử giao dịch bằng ID lệnh cũ (`/historicalTrades`) | DataFrame |
| `vwap()` | Giá trung bình theo khối lượng (`/avgPrice`) | DataFrame |
| `daily_stats()` | Thống kê phiên giao dịch (`/ticker/tradingDay`) | DataFrame |
| `last_price()` | Mức giá khớp lệnh cuối cùng (`/ticker/price`) | DataFrame |
| `rolling_stats()` | Thống kê theo cửa sổ trượt (Rolling Window Ticker) | DataFrame |
| `reference_price(mode)` | Giá tham chiếu (`price`) hoặc cấu trúc tính toán (`calc`) | DataFrame |

#### Ví dụ Truy xuất Crypto
```python
from vnstock_data import Market

mkt = Market()
crypto = mkt.crypto("BTCUSDT")

# Báo giá Ticker hiện tại (24HR Rolling)
df_quote = crypto.quote()

# Dữ liệu OHLCV (ví dụ khung 1 ngày)
df_ohlcv = crypto.ohlcv(interval="1d", limit=500)

# Sổ lệnh (Order Book 10 mức giá Mua/Bán)
df_orderbook = crypto.order_book(limit=10)

# Lịch sử khớp lệnh trong phiên (Intraday)
df_trades = crypto.intraday()

# Trích xuất VWAP
df_vwap = crypto.vwap()
```

#### Forex, Commodity & Global Index (Thị Trường Cầu & Hàng hóa)

**Nguồn chính:** Dukascopy  
**Registry Key:** `"market.forex"`, `"market.commodity"`, `"market.index"`

Các domain Forex, Commodity, Index (Chỉ số Quốc Tế) giờ đây được hợp nhất kiến trúc và trực tiếp truy xuất Data **Tick/Phút liên tục** thông qua `Dukascopy`. Hệ thống còn cho phép cơ chế *Resampling* nội bộ đối với các khung cao (1h, 4h, 1d...).

| Method | Tham Số Chính | Mô Tả | Return |
|--------|---------------|------|--------|
| `ohlcv()` | `interval`, `length`, `timezone` | Lịch sử giá theo khoảng thời gian tuỳ chọn | DataFrame |
| `intraday()`| `timezone` | Dữ liệu Tick (Khớp lệnh) | DataFrame |

> **🌟 Tính năng Timezone Configuration Parameter**:  
> Lịch sử giá Dukascopy và Quốc tế mặc định được tự động map về quy chuẩn giờ Hệ thống Việt Nam (`Asia/Ho_Chi_Minh` / GMT+7). Tuy nhiên, có thể tuỳ ý ghi đè múi giờ gốc (UTC) hoặc bất kỳ giờ khu vực nào qua tham số `timezone`.

#### Ví Dụ
```python
mkt = Market()

# Lịch sử tỷ giá tiền tệ theo giờ nội địa VN
df_eurusd = mkt.forex("EURUSD").ohlcv(interval="1h", length=15)

# Định dạng nến trả về đúng múi giờ quốc tế gốc UTC!
df_utc_eurusd = mkt.forex("EURUSD", timezone="UTC").ohlcv(interval="1h", length=15)

# Lịch sử giá vàng
df_gold = mkt.commodity("XAUUSD").ohlcv(interval="1d", length=5)

# Lấy dữ liệu Chỉ Số chứng khoán Mỹ (Dow Jones USA30) qua tham số scope="global"
df_djia = mkt.index("USA30", scope="global").ohlcv(interval="4h")
```

> **Tip**: Có thể dùng `Reference().search.symbol("tên_tài_sản")` để tìm mã Symbol nếu không chắc chắn.

---

## 💡 Best Practices

### 1. Gọi nhiều mã cùng lúc

```python
# ❌ Không tối ưu - gọi 100 lần
mkt = Market()
for symbol in symbols:
    quote = mkt.equity(symbol).quote()

# ✅ Tốt - gọi 1 lần
all_quotes = mkt.quote(symbols)
```

### 2. Xử lý lỗi

```python
mkt = Market()

try:
    df = mkt.equity("INVALID").ohlcv(start="2026-02-01", end="2026-02-28")
except ValueError as e:
    print(f"Symbol không tồn tại: {e}")
except Exception as e:
    print(f"Lỗi: {e}")
```

### 3. Tra cứu tính năng khả dụng

```python
from vnstock_data import show_api, Market

# Xem tất cả methods trong Market layer
show_api(Market())
```

---

## ⚠️ Lưu Ý Quan Trọng

1. **Realtime vs Historical**: Dữ liệu intraday có thể bị delay 15-30 phút tùy provider
2. **Giờ giao dịch**: Dữ liệu chỉ cập nhật trong giờ giao dịch (9h-15h)
3. **Ngày nghỉ**: Không có dữ liệu vào các ngày nghỉ lễ
4. **Giới hạn tần suất**: Một số nguồn có giới hạn số lượng yêu cầu → nên gọi nhiều mã cùng lúc hoặc lưu tạm
5. **Deprecated `derivatives()`**: Dùng `mkt.futures(symbol)` / `mkt.warrant(symbol)` trực tiếp
6. **Deprecated `pe()`/`pb()`**: Đã chuyển sang `Analytics().valuation(index).pe()/.pb()`

---

## 🚦 Next Steps

- **Fundamental Layer**: Để phân tích các chỉ số tài chính
- **Analytics Layer**: Để định giá thị trường (P/E, P/B)
- **Insights Layer**: Để xem xếp hạng và lọc cổ phiếu
- **Macro Layer**: Để xem dữ liệu kinh tế toàn cảnh

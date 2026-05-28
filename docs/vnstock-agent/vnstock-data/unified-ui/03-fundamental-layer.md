# Layer 3: Fundamental Data (Dữ Liệu Cơ Bản)

## 📌 Tổng Quan

**Fundamental Layer** cung cấp dữ liệu **báo cáo tài chính** (BCTC) và **tỷ số tài chính** phục vụ cho phân tích cơ bản của doanh nghiệp. Ở phương thức nâng cao, hệ thống trang bị tính năng **quy chuẩn hoá (Force scorecard)** giúp định hình ma trận tài chính cực mạnh cho các mô hình AI dự báo.

Bao gồm Báo cáo kết quả kinh doanh, Bảng cân đối kế toán, Lưu chuyển tiền tệ, Tỷ số tài chính, Thuyết minh BCTC và Báo cáo sức khỏe tài chính tổng hợp (Auto Scorecard).

## 🏗️ Cấu Trúc Domain

Mô đun Fundamental hỗ trợ 2 dạng cú pháp linh hoạt (Proxy chaining):
1. **Dạng hướng đối tượng (Object Oriented):** `fun.equity("TCB").cash_flow()`
2. **Dạng hàm tiện ích (Utility Function):** `fun.equity.cash_flow("TCB")`

```python
Fundamental()
└── .equity                  # Dữ liệu tài chính chứng khoán (Cổ phiếu)
    ├── .income_statement()  # Báo cáo kết quả kinh doanh
    ├── .balance_sheet()     # Cân đối kế toán
    ├── .cash_flow()         # Lưu chuyển tiền tệ
    ├── .ratio()             # Tỷ số tài chính
    ├── .note()              # Thuyết minh cơ cấu BCTC
    └── .financial_health()  # Báo cáo hợp nhất với Auto Scorecard
```

## 📋 Chi Tiết Các Phương Thức

### 1. Income Statement (Báo Cáo Kết Quả Kinh Doanh)

**Source:** KBS  
**Registry Key:** `"equity.fundamental.income_statement"`  
**Tham số:** `limit` (int), `period_type` (1=Năm, 2=Quý), `lang` ("vi"/"en"), `scorecard`

**Ví Dụ:**
```python
from vnstock_data import Fundamental
fun = Fundamental()

# Báo cáo kết quả kinh doanh (4 kỳ gần nhất)
df_income = fun.equity("TCB").income_statement(limit=4)
print(df_income)
```

---

### 2. Balance Sheet (Cân Đối Kế Toán)

**Source:** KBS  
**Registry Key:** `"equity.fundamental.balance_sheet"`  

**Ví Dụ:**
```python
# Cân đối kế toán dạng hàm tiện ích
df_bs = fun.equity.balance_sheet("VIC", limit=4)
print(df_bs)
```

---

### 3. Cash Flow (Lưu Chuyển Tiền Tệ)

**Source:** KBS  
**Registry Key:** `"equity.fundamental.cash_flow"`  

**Ví Dụ:**
```python
df_cf = fun.equity("VNM").cash_flow(limit=4)
print(df_cf)
```

---

### 4. Financial Ratio (Tỷ Số Tài Chính)

**Source:** KBS  
**Registry Key:** `"equity.fundamental.ratio"`  

Chứa các tỷ số quan trọng như PE, PB, ROE, Debt/Equity.  

**Ví Dụ:**
```python
df_ratio = fun.equity("HPG").ratio(limit=4)
```

---

### 5. Note (Thuyết Minh BCTC)

**Source:** VCI  
**Registry Key:** `"equity.fundamental.note"`  

Trích xuất tự động các thuyết minh con/ghi chú chi tiết đính kèm trên báo cáo tài chính hàng quý.

**Ví Dụ:**
```python
df_note = fun.equity("FPT").note()
```

---

### 6. Financial Health (Bảng Tổng Hợp Scorecard)

**Từ Vnstock 3.1.0, hàm này được trang bị nhằm đáp ứng nhu cầu khắt khe của Quant Trading và AI Model:**
Thay vì để phơi nhiễm cấu trúc JSON thô tuỳ thích của nguồn cấp, `financial_health` sẽ:
- Dò tìm mã ngành ICB tự động (qua `Reference` layer).
- **Ép buộc (force)** dữ liệu tài chính đi qua **Scorecard** (Ngân hàng, CK, Bảo hiểm, chung...).
- Chỉ cho phép lọt qua các fields có trong thẻ Scorecard (các cấu trúc rác bị loại bỏ hoàn toàn).
- Nối BCTC theo phương ngang dựa vào index là `period`.
- Áp `lang="vi"` để ánh xạ ra giao diện Terminal TCBS.

**Tham số bổ sung:**
- `scorecard`: `"auto"`, `"banking"`, `"securities"`, `"insurance"`, `"generic"`
- `reports`: List các loại báo cáo muốn hợp nhất (Mặc định: cả 4 fundamental reports).

**Ví Dụ:**
```python
# 1. Tự động phát hiện Chứng khoán và lấy scorecard VI
df_ssi = fun.equity("SSI").financial_health(scorecard="auto", lang="vi", limit=4)

# 2. Ép sử dụng Scorecard Ngân hàng với chuẩn EN
df_tcb = fun.equity("TCB").financial_health(scorecard="banking", lang="en", limit=4)
```

---

## 🔗 Registry Mapping Config

```python
FUNDAMENTAL_SOURCES = {
    "equity.fundamental": {
        "income_statement": ("kbs", "financial", "Finance", "income_statement"),
        "balance_sheet": ("kbs", "financial", "Finance", "balance_sheet"),
        "cash_flow": ("kbs", "financial", "Finance", "cash_flow"),
        "ratio": ("kbs", "financial", "Finance", "ratio"),
        "note": ("vci", "financial", "Finance", "note")
    }
}
```

## ⚠️ Lưu Ý Quan Trọng
1. **Tần Suất Update**: BCTC được công bố cuối mỗi kỳ, không có realtime.
2. **Khoảng Trống**: Một số doanh nghiệp có thể che dấu field hoặc không phát sinh nghiệp vụ, khi rập scorecard sẽ tự quy thành `NaN` chống gãy logic.
3. **Múc Tiền Tệ**: Mặc định là VND trừ phi có scale format tại tầng ứng dụng.

## 🚦 Next Steps
- **Market Layer**: Để lấy giá hiện tại và lịch sử giao dịch so kè PE, PB.
- **Insights Layer**: Gửi bảng financial_health cho các model phân tích tín hiệu.

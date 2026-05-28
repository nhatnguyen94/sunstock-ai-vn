# Biểu đồ Cơ bản (Basic Charts)

Nhóm `BasicMixin` trong `vnstock_ezchart` cung cấp các API để vẽ các dạng biểu đồ tĩnh phổ biến nhất như đường (line), cột (bar), phân bổ (hist), tròn (pie), phân tán (scatter), và biểu đồ kết hợp (combo).

> **Lưu ý cho AI Agent:** Khi người dùng cung cấp dữ liệu cơ bản (ví dụ: chuỗi thời gian đơn giản, tần suất phân bổ), hãy ưu tiên sử dụng các biểu đồ trong nhóm này thay vì các hàm phức tạp. Tất cả các hàm đều trả về đối tượng `Figure` và `Axes` của `matplotlib` (hoặc mảng `Axes`).

---

## 1. Biểu đồ Đường (Line Chart)

Thể hiện sự thay đổi của dữ liệu theo thời gian (timeseries) hoặc theo chuỗi liên tục. Hàm `timeseries` hiện đã bị deprecated, hãy dùng hàm `line` thay thế.

**Cú pháp:**
```python
Chart.line(data, **kwargs) -> Tuple[plt.Figure, plt.Axes]
```

**Tham số chính:**
- `data` (pd.DataFrame | pd.Series): Dữ liệu đầu vào, bắt buộc. Index của dataframe/series thường nên là thời gian (datetime).
- `**kwargs`: Các tuỳ chọn tuỳ biến hiển thị từ `Chart.apply_chart_style()` (như `title`, `figsize`, `xlabel`, `ylabel`, `color_palette`, `data_labels`, v.v.).

**Ví dụ:**
```python
import pandas as pd
from vnstock_ezchart import Chart

# Dữ liệu mẫu (sử dụng pandas DataFrame hoặc Series)
# df = ...
fig, ax = Chart.line(df, title="Lợi nhuận theo quý", figsize=(10, 6))
```

---

## 2. Biểu đồ Cột (Bar Chart)

Dùng để so sánh giá trị giữa các danh mục hoặc các mốc thời gian riêng biệt.

**Cú pháp:**
```python
Chart.bar(data, **kwargs) -> Tuple[plt.Figure, plt.Axes]
```

**Tham số chính:**
- `data` (pd.DataFrame | pd.Series): Dữ liệu đầu vào.
- `**kwargs`: Các tham số định dạng thẩm mỹ (ví dụ: `bar_edge_color`, `background_color`).

**Ví dụ:**
```python
fig, ax = Chart.bar(
    df_revenue, 
    title="Doanh thu các chi nhánh", 
    data_labels=True, 
    data_label_format='1K'  # Hiển thị số rút gọn dạng ngàn
)
```

---

## 3. Biểu đồ Phân bổ (Histogram)

Thể hiện phân phối xác suất của một tập hợp dữ liệu.

**Cú pháp:**
```python
Chart.hist(data, **kwargs) -> Tuple[plt.Figure, plt.Axes]
```

**Tham số chính:**
- `data` (pd.DataFrame | pd.Series): Tập dữ liệu dạng chuỗi số.

---

## 4. Biểu đồ Tròn (Pie Chart)

Mô tả cơ cấu hoặc phần trăm của từng thành phần trong tổng thể.

**Cú pháp:**
```python
Chart.pie(data, labels, **kwargs) -> Tuple[plt.Figure, plt.Axes]
```

**Tham số chính:**
- `data` (list | pd.Series): Danh sách giá trị số.
- `labels` (list): Danh sách các nhãn tương ứng với từng lát cắt.
- `**kwargs`: Các tuỳ chọn như `color_palette`, `legend_title`, `show_legend`.

**Ví dụ:**
```python
values = [40, 30, 20, 10]
labels = ['Cổ phiếu', 'Trái phiếu', 'Tiền mặt', 'Vàng']

fig, ax = Chart.pie(
    data=values, 
    labels=labels, 
    title="Cấu trúc Danh mục", 
    color_palette='vnstock'
)
```

---

## 5. Biểu đồ Phân tán (Scatter Plot)

Đánh giá mối tương quan giữa hai biến số lượng khác nhau.

**Cú pháp:**
```python
Chart.scatter(data, x, y, **kwargs) -> Tuple[plt.Figure, plt.Axes]
```

**Tham số chính:**
- `data` (pd.DataFrame): Bảng dữ liệu chứa hai cột sẽ đem so sánh.
- `x` (str): Tên cột sẽ làm trục hoành.
- `y` (str): Tên cột sẽ làm trục tung.

---

## 6. Biểu đồ Kết hợp (Combo Chart)

Kết hợp giữa một biểu đồ cột (Bar) ở trục Y thứ nhất và một biểu đồ đường (Line) ở trục Y thứ hai (twinx). Hàm `combo_chart` hiện đã bị deprecated, thay vào đó hãy dùng `combo`.

**Cú pháp:**
```python
Chart.combo(bar_data, line_data, left_ylabel='Bar Data', right_ylabel='Line Data', **kwargs) -> Tuple[plt.Figure, plt.Axes, plt.Axes]
```

**Tham số chính:**
- `bar_data` (pd.Series | pd.DataFrame): Dữ liệu cho biểu đồ cột.
- `line_data` (pd.Series | pd.DataFrame): Dữ liệu cho biểu đồ đường.
- `left_ylabel` (str): Tiêu đề cho trục tung bên trái.
- `right_ylabel` (str): Tiêu đề cho trục tung bên phải.

**Ví dụ:**
```python
fig, ax1, ax2 = Chart.combo(
    bar_data=df['Volume'], 
    line_data=df['ClosePrice'], 
    left_ylabel="Khối lượng", 
    right_ylabel="Giá đóng cửa",
    title="Diễn biến Giá và Khối lượng"
)
```

---
**Tài nguyên Tham khảo:**  
AI Agent có thể tra cứu các file code mẫu đầy đủ tại [Github: docs/examples/02_technical_analysis.py](https://github.com/vnstock-hq/vnstock_ezchart/blob/main/docs/examples/02_technical_analysis.py) hoặc trong thư mục `docs/vnstock_ezchart/examples` của workspace hiện tại.

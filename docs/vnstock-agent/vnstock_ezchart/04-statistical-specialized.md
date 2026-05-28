# Biểu đồ Thống kê & Đặc biệt (Statistical & Specialized)

Nhóm chức năng này (bao gồm `StatisticalMixin` và `SpecializedMixin`) cung cấp các công cụ trực quan hoá nâng cao, rất hữu ích khi cần phân tích sự phân bổ, cấu trúc phân lớp danh mục, tương quan đa biến hoặc thậm chí xử lý ngôn ngữ tự nhiên (tâm lý thị trường).

---

## 1. Biểu đồ Hộp (Boxplot)

Biểu đồ Boxplot giúp AI Agent tóm tắt phân phối thống kê của một tập dữ liệu (min, max, median, quartiles) và dễ dàng phát hiện các điểm bất thường (outliers). Rất hữu ích cho việc quan sát tính chu kỳ (Seasonality).

**Cú pháp:**
```python
Chart.boxplot(data, **kwargs) -> Tuple[plt.Figure, plt.Axes]
```

**Tham số chính:**
- `data` (pd.DataFrame): Dữ liệu chứa các cột số liệu cần thống kê. Mỗi cột sẽ tương ứng với một "hộp" (box).
- `**kwargs`: Tương tự các hàm vẽ cơ bản (như `title`, `color_palette`, `figsize`, `ylabel`).

---

## 2. Biểu đồ Phân tán Đa biến (Pairplot)

Vẽ một ma trận biểu đồ phân tán để kiểm tra sự tương quan chéo giữa nhiều biến số trong dữ liệu.

**Cú pháp:**
```python
Chart.pairplot(data, **kwargs) -> sns.PairGrid
```
*(Lưu ý: Do sử dụng Seaborn ở backend, hàm có thể trả về đối tượng `PairGrid` thay vì `Figure` tiêu chuẩn)*

**Tham số chính:**
- `data` (pd.DataFrame): Dữ liệu phân tích đa biến.
- `**kwargs`: Các tham số tuỳ biến thêm.

---

## 3. Bản đồ Nhiệt (Heatmap)

Dùng để biểu diễn dữ liệu dạng ma trận bằng màu sắc, ứng dụng mạnh nhất vào ma trận hệ số tương quan (Correlation Matrix).

**Cú pháp:**
```python
Chart.heatmap(data, **kwargs) -> Tuple[plt.Figure, plt.Axes]
```

**Tham số chính:**
- `data` (pd.DataFrame): Ma trận số liệu (ví dụ: `df.corr()`).
- `**kwargs`: Các tuỳ chọn thẩm mỹ. Có thể dùng thêm tham số `cmap` (color map) hoặc dùng `color_palette` mặc định của thư viện.

**Ví dụ:**
```python
# df_returns là dataframe chứa tỷ suất sinh lời của nhiều mã cổ phiếu
corr_matrix = df_returns.corr()

fig, ax = Chart.heatmap(
    corr_matrix, 
    title="Ma trận Tương quan Cổ phiếu", 
    color_palette='vnstock'
)
```

---

## 4. Biểu đồ Cây (Treemap)

Treemap hiển thị dữ liệu có cấu trúc phân cấp bằng các hình chữ nhật lồng nhau, trong đó diện tích hình chữ nhật tỷ lệ thuận với giá trị. Ứng dụng phổ biến nhất là biểu diễn Tỷ trọng Danh mục đầu tư hoặc Vốn hoá các mã trong VN30.

> **Yêu cầu cài đặt:** Cần cài đặt gói bổ trợ `squarify` (`pip install vnstock-ezchart[all]`).

**Cú pháp:**
```python
Chart.treemap(values, labels, title='', color_palette='vnstock', palette_shuffle=False, figsize=(10, 8), title_fontsize=14, **kwargs)
```

**Tham số chính:**
- `values` (list | pd.Series): Danh sách giá trị tỷ trọng hoặc giá trị tuyệt đối.
- `labels` (list): Tên các danh mục/cổ phiếu tương ứng.
- `color_palette` (str): Tên bộ màu sử dụng.
- `palette_shuffle` (bool): Đảo thứ tự màu ngẫu nhiên (tránh việc các ô cạnh nhau bị trùng màu).

**Ví dụ:**
```python
values = [500, 300, 200, 100, 50]
labels = ["Ngân hàng", "Bất động sản", "Bán lẻ", "Thép", "Công nghệ"]

fig, ax = Chart.treemap(
    values=values, 
    labels=labels, 
    title="Cơ cấu Vốn hoá theo Ngành"
)
```

---

## 5. Đám mây Từ vựng (Wordcloud)

Biểu diễn trực quan tần suất xuất hiện của từ ngữ trong một đoạn văn bản. Thích hợp cho AI Agent khi phân tích Tâm lý thị trường (Market Sentiment) qua các bản tin tức.

> **Yêu cầu cài đặt:** Cần cài đặt gói bổ trợ `wordcloud` (`pip install vnstock-ezchart[all]`).

**Cú pháp:**
```python
Chart.wordcloud(text, title='Word Cloud', color_palette='vnstock', max_words=100, **kwargs)
```

**Tham số chính:**
- `text` (str): Một chuỗi văn bản (đã được nối lại từ các từ khóa).
- `max_words` (int): Số lượng từ tối đa được hiển thị.

**Ví dụ:**
```python
sentiment_text = "MUA MẠNH TĂNG_TRƯỞNG BÙNG_NỔ THANH_KHOẢN VCB VCB TÍCH_LŨY KHỐI_NGOẠI GOM"

fig, ax = Chart.wordcloud(
    sentiment_text, 
    title="Tâm lý Thị trường (Market Sentiment)",
    color_palette='trend'
)
```

---

## 6. Biểu diễn Bảng (Table Image)

Hỗ trợ Agent kết xuất một pandas DataFrame ra dạng hình ảnh bảng tĩnh (Table Image) với UI chuyên nghiệp để dán vào báo cáo, tránh tình trạng bảng markdown bị vỡ giao diện trên một số hệ thống.

**Cú pháp:**
```python
Chart.table(data, title='', figsize=(5.5, 6), **kwargs)
```

**Tham số chính:**
- `data` (pd.DataFrame): Dữ liệu dạng bảng.
- `title` (str): Tiêu đề của bảng.

---
**Tài nguyên Tham khảo:**  
Bạn có thể tìm mã nguồn chạy thử các đồ thị Thống kê & Phân bổ Danh mục tại [Github: docs/examples/03_portfolio_management.py](https://github.com/vnstock-hq/vnstock_ezchart/blob/main/docs/examples/03_portfolio_management.py) hoặc xem cách tạo Wordcloud trong file `examples/04_market_data.py`.

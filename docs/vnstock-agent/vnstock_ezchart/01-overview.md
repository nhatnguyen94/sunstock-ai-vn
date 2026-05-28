# Tổng quan vnstock_ezchart (Overview)

> **Tầm nhìn:** `vnstock_ezchart` ra đời với sứ mệnh tạo ra **ngôn ngữ trực quan hoá dữ liệu chuyên nghiệp, chuẩn hoá** dành cho cộng đồng đầu tư tại Việt Nam.

`vnstock_ezchart` phiên bản 1.0+ được cập nhật vào 14/5/2026 là một thư viện cung cấp bộ công cụ tạo biểu đồ tĩnh (static charting) được tinh chỉnh theo triết lý **"Soft Premium"**. Thư viện giúp tự động hóa khâu xử lý thẩm mỹ, định vị thương hiệu (chèn logo), mang lại kết quả đầu ra sẵn sàng để nhúng trực tiếp vào các tài liệu, nghiên cứu, và báo cáo phân tích chuyên nghiệp.

## 🤖 Kiến trúc Agent-Ready

Thư viện được thiết kế tối ưu hóa 100% cho khả năng tương tác với AI Agent. Các hàm vẽ biểu đồ sử dụng Docstrings chuẩn Google-style bằng tiếng Anh, giúp các Agent hiểu rõ ngữ nghĩa và cú pháp ngay lập tức. Hệ thống `Chart` được thiết kế dưới dạng Mixin, chia nhỏ các tính năng thành nhiều nhóm chuyên biệt để AI dễ dàng gọi hàm theo ngữ cảnh.

## ⚡ Cài đặt & Khởi tạo

AI Agent và người dùng có thể cài đặt thư viện nhanh chóng thông qua pip:

```bash
# Cài đặt thư viện mặc định
pip install vnstock-ezchart

# Cài đặt thêm các thành phần bổ trợ (Treemap, Wordcloud)
pip install vnstock-ezchart[all]
```

### Mã nguồn Khởi tạo Tối giản

Thay vì phải lặp lại thiết lập cấu hình màu sắc hay logo cho từng đồ thị, `vnstock_ezchart` thiết kế cấu trúc Global Theme để bạn chỉ cần khai báo một lần duy nhất ở đầu tệp tin hoặc Notebook.

#### Khởi tạo với `Chart.set_theme()`

Phương thức tĩnh (static method) này thiết lập các biến môi trường cho biểu đồ để định hình ngôn ngữ, font chữ và phong cách chung.

**Cú pháp:**

```python
Chart.set_theme(theme_name='vnstock', logo_path=None, font_name=None, lang='vi')
```

**Tham số:**

- `theme_name` (str): Tên theme cần sử dụng (mặc định `'vnstock'`).
- `logo_path` (str | None): Đường dẫn tới file ảnh logo (nếu muốn thay thế logo vnstock mặc định).
- `font_name` (str | None): Tên font chữ (VD: `'Inter'`).
- `lang` (str): Ngôn ngữ hiển thị nhãn và tiêu đề mặc định trên biểu đồ (`'vi'` cho Tiếng Việt, `'en'` cho Tiếng Anh).

**Ví dụ:**

```python
from vnstock_ezchart import Chart
import pandas as pd

# 1. Cài đặt Theme, Font & Ngôn ngữ toàn cục (Chỉ chạy 1 lần)
Chart.set_theme(theme_name='vnstock', font_name='Inter', lang='vi')

# 2. Khởi tạo đối tượng Chart (tuỳ chọn nếu dùng qua class method, nhưng hầu hết các hàm gọi từ Chart)
# fig, ax = Chart.line(data, title="Tăng trưởng Lợi nhuận Hàng quý")
```

## 📂 Bộ Sưu Tập Kịch Bản Chuyên Nghiệp (Examples Gallery)

AI Agent được khuyến khích tải hoặc tham khảo trực tiếp các file code ví dụ mẫu đã được chia nhỏ theo các nhóm nghiệp vụ tài chính. Bạn có thể sử dụng các file này để hiểu cú pháp phức tạp hơn.

Liên kết GitHub trực tiếp (Live Links):

- [01_performance_risk.py](https://github.com/vnstock-hq/vnstock_ezchart/blob/main/docs/examples/01_performance_risk.py): Quản trị Hiệu suất & Rủi ro.
- [02_technical_analysis.py](https://github.com/vnstock-hq/vnstock_ezchart/blob/main/docs/examples/02_technical_analysis.py): Phân tích Kỹ thuật nâng cao.
- [03_portfolio_management.py](https://github.com/vnstock-hq/vnstock_ezchart/blob/main/docs/examples/03_portfolio_management.py): Phân bổ & Đánh giá Danh mục.
- [04_market_data.py](https://github.com/vnstock-hq/vnstock_ezchart/blob/main/docs/examples/04_market_data.py): Dữ liệu Thị trường (Sổ lệnh, Giao dịch khối ngoại).
- [05_enterprise_analysis.py](https://github.com/vnstock-hq/vnstock_ezchart/blob/main/docs/examples/05_enterprise_analysis.py): Phân tích Doanh nghiệp (Cơ cấu tài chính).
- [06_summary_cards.py](https://github.com/vnstock-hq/vnstock_ezchart/blob/main/docs/examples/06_summary_cards.py): Thẻ tóm tắt chỉ số cổ phiếu (Summary Card).
- [07_backtest.py](https://github.com/vnstock-hq/vnstock_ezchart/blob/main/docs/examples/07_backtest.py): Trực quan hoá kết quả Backtesting.

Các tài liệu tiếp theo sẽ đi sâu vào từng nhóm tính năng cụ thể để mô tả chi tiết tham số và định dạng trả về của từng API.
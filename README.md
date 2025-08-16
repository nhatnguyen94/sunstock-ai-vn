# Sun Stock AI – Vietnam’s Smart Stock App

Ứng dụng web giúp tra cứu giá cổ phiếu Việt Nam, sử dụng **Laravel 12 (PHP)** và **Python**.  
Tự động cập nhật dữ liệu, giao diện đẹp, dễ dùng, có autocomplete tìm mã cổ phiếu.

A web application for viewing Vietnamese stock prices, built with **Laravel 12 (PHP)** and **Python**.  
Auto-update data, beautiful responsive UI, fast autocomplete for stock symbols.

---

## 🚀 Tính năng nổi bật / Key Features

- **Tra cứu giá lịch sử cổ phiếu / View historical stock prices**: Bảng giá, biểu đồ nến/đường, khối lượng giao dịch.
- **Tìm kiếm mã cổ phiếu nhanh / Fast stock symbol search**: Autocomplete, cập nhật danh sách mã tự động từ Python.
- **Lưu dữ liệu vào MySQL / Save data to MySQL**: Tránh crawl trùng lặp, chỉ cập nhật khi cần.
- **Tích hợp Python / Python integration**: Lấy dữ liệu từ vnstock, cập nhật cả giá và danh sách mã.
- **Giao diện responsive / Responsive UI**: Đẹp, dễ dùng trên mọi thiết bị, Bootstrap 4 + icon.
- **Kiến trúc chuẩn SOLID / SOLID architecture**: Controller, Service, Repository, Interface rõ ràng, dễ mở rộng.
- **Footer cá nhân hóa / Personalized footer**: Hiển thị thông tin tác giả, email, GitHub, LinkedIn ở mọi trang.
- **Tỷ giá ngoại tệ Vietcombank / Vietcombank exchange rates**: Hiển thị tỷ giá 3 ngày gần nhất.
- **Top 30 công ty hot theo ngành / Top 30 hot companies by industry**: Bảng các mã nổi bật theo ngành, số lượng linh động.
- **🔥 Tích hợp AI Model Chat / Integrated AI Model Chat**: Popup chat bubble ở góc phải dưới, hỏi đáp về cổ phiếu, ngành, tỷ giá, tài chính.  
  Hỗ trợ chọn ngôn ngữ (Tiếng Việt/English), đổi model AI (gemma3:1b, mistral...), giao diện đẹp, chuyên nghiệp.

---

## 🆕 Nhật ký cập nhật / Update Log

- **2025-08-16:**  
  - Tích hợp AI Model Chat (Ollama: gemma3:1b, mistral) vào toàn bộ app, popup chat bubble hiện đại, chọn ngôn ngữ, đổi model dễ dàng.
  - Cải thiện UI/UX chat, thêm icon lá cờ, bo tròn, bóng đổ, nút xóa lịch sử chat.
  - Bổ sung hướng dẫn cài đặt và sử dụng AI vào README.

- **2025-08-10:**  
  - Thêm tỷ giá ngoại tệ Vietcombank (3 ngày gần nhất).
  - Thêm bảng top 30 công ty hot theo ngành, số lượng linh động.
  - Refactor Controller, Repository, Service theo chuẩn SOLID, dùng dependency injection.
  - Thêm file database mẫu (`stock_app.sql`) để dễ import.

- **2025-08-09:**  
  - Chuẩn hóa Controller theo SOLID, tách Service/Repository/Interface.  
    Refactored Controller to SOLID, separated Service/Repository/Interface.
  - Footer đẹp, có icon, thông tin cá nhân.  
    Improved footer with icons and author info.
  - Sửa UI homepage: header gọn, card mã nổi bật đều, responsive tốt.  
    Homepage UI improved: compact header, even featured cards, better responsive.
  - Cập nhật README, bổ sung hướng dẫn, tính năng mới.  
    Updated README, added instructions and new features.

- **Trước đó / Earlier:**  
  - Thêm autocomplete tìm kiếm mã cổ phiếu.  
    Added autocomplete for stock symbol search.
  - Tối ưu giao diện, sửa lỗi encoding Python.  
    UI improvements, fixed Python encoding bug.
  - Tích hợp crawl giá lịch sử, lưu DB, kiểm tra trùng lặp, giao diện Bootstrap.  
    Integrated historical price crawling, DB saving, duplicate check, Bootstrap UI.

---

## 📸 Ảnh màn hình / Screenshots

![Screenshot](public/images/Screenshot_5.png)
![Screenshot](public/images/Screenshot_6.png)
![Screenshot](public/images/Screenshot_2.png)
![Screenshot](public/images/Screenshot_3.png)
![Screenshot](public/images/Screenshot_4.png)
![Screenshot](public/images/Screenshot_7.png)

---

## ⚡ Hướng dẫn cài đặt / Quick Start

1. **Clone & cài đặt PHP / Clone & install PHP dependencies:**
    ```bash
    git clone https://github.com/nhatnguyen94/stock-app.git
    cd stock-app
    composer install
    cp .env.example .env
    php artisan key:generate
    ```

2. **Cấu hình MySQL trong `.env` / Configure MySQL in `.env`**

3. **Tạo database `stock_app` trong MySQL / Create database `stock_app` in MySQL**

4. **Chạy migrate / Run migrations:**
    ```bash
    php artisan migrate
    ```

5. **Thêm database mẫu / Add sample database:**  
   Đã thêm file `stock_app.sql` chứa dữ liệu mẫu.  
   Để import vào MySQL, chạy lệnh sau:  
    ```bash
    mysql -u root -p stock_app < stock_app.sql
    ```
   (Thay `root` bằng user của bạn nếu khác / Replace `root` with your MySQL user if different)

6. **Cài Python & vnstock / Install Python & vnstock:**
    ```bash
    pip install vnstock
    ```

7. **Kiểm tra script Python / Test Python scripts:**
    ```bash
    python py/get_stock.py FPT
    python py/get_stock_list.py
    python py/get_hot_industries.py 30
    ```
    (Có thể đổi số 30 thành số bạn muốn / You can change 30 to any number you want)

8. **Chạy server / Start server:**
    ```bash
    php artisan serve
    ```
    Truy cập: http://127.0.0.1:8000/  
    Visit: http://127.0.0.1:8000/

---

## 🤖 Hướng dẫn cài đặt & sử dụng AI Model Chat

### 1. Cài đặt Ollama & Model AI

- Tải Ollama tại [https://ollama.com/download](https://ollama.com/download)
- Cài xong, mở terminal và chạy:
    ```bash
    ollama pull gemma3:1b
    ollama run gemma3:1b
    # Hoặc dùng model mạnh hơn:
    ollama pull mistral
    ollama run mistral
    ```
- Đảm bảo Ollama đang chạy trên `localhost:11434`

### 2. Sử dụng AI Chat trên web

- Nhấn vào icon 💬 ở góc phải dưới để mở popup chat AI.
- Chọn ngôn ngữ (🇻🇳/🇺🇸), nhập câu hỏi về cổ phiếu, ngành, tỷ giá, tài chính...
- AI sẽ trả lời bằng tiếng Việt hoặc English theo lựa chọn.
- Có thể đổi model AI bằng cách sửa tên model trong file `app/Services/AiService.php`:
    ```php
    public function askOllama($prompt, $model = 'gemma3:1b')
    ```
    hoặc `'mistral'` nếu muốn dùng model mạnh hơn.

---

## 💡 Cách sử dụng / Usage

- Truy cập trang chủ, nhập mã cổ phiếu (ví dụ: FPT, VCB, E1VFVN30...)
- Xem bảng giá lịch sử, biểu đồ, thông tin chi tiết.
- Tìm kiếm mã cổ phiếu nhanh với autocomplete.
- Xem tỷ giá ngoại tệ Vietcombank 3 ngày gần nhất.
- Xem top 30 công ty hot theo ngành, số lượng linh động.
- Footer luôn hiển thị thông tin tác giả.
- **Chat AI thông minh về tài chính, cổ phiếu, tỷ giá ngay trên web!**

Go to homepage, enter stock symbol (e.g. FPT, VCB, E1VFVN30...)
View historical price table, charts, and details.
Fast autocomplete for stock symbol search.
See Vietcombank exchange rates for the last 3 days.
See top 30 hot companies by industry, dynamic limit.
Footer always shows author info.
**Smart AI Chat about finance, stocks, exchange rates directly on the web!**

---

## 🛠️ Kiến trúc & Công nghệ / Architecture & Technology

- **Laravel 12** (PHP)
- **Python 3 + vnstock**
- **Bootstrap 4, Bootstrap Icons**
- **Ollama AI Model Chat (gemma3:1b, mistral...)**
- **SOLID: Controller, Service, Repository, Interface**
- **MySQL**

---

## 👤 Tác giả / Author

**Sun Nguyen**  
Email: [nhat.nguyenminh94@gmail.com](mailto:nhat.nguyenminh94@gmail.com)  
GitHub: [nhatnguyen94/stock-app](https://github.com/nhatnguyen94/stock-app)  
LinkedIn: [Sun Nguyen](https://www.linkedin.com/in/sunnguyen3011/)

---

MIT License © 2025 Sun
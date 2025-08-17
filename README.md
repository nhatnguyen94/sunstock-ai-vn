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

- **2025-08-17:**  
  - Sửa lỗi hiển thị tỷ giá ngoại tệ: xử lý dữ liệu chuỗi từ Python với `parseRate()`, tránh lỗi `number_format()`.  
    Fixed exchange rate display bug: handle string data from Python with `parseRate()`, avoid `number_format()` error.
  - Cải thiện UI/UX form tìm kiếm: sửa input bị thu hẹp do Awesomplete, responsive design tốt hơn.  
    Enhanced search form UI/UX: fixed input shrinking issue from Awesomplete, better responsive design.
  - Thêm hiển thị tên cổ phiếu trong header và title để user dễ nhận diện.  
    Added stock name display in header and title for better user recognition.
  - Tối ưu CSS cho trang tỷ giá ngoại tệ và trang cổ phiếu: button styling, animation, hover effects.  
    Optimized CSS for exchange rate and stock pages: button styling, animations, hover effects.
  - Enhanced autocomplete: hiển thị cả mã và tên cổ phiếu trong dropdown, debounce search, loading states.  
    Enhanced autocomplete: show both symbol and stock name in dropdown, debounced search, loading states.

- **2025-08-16:**  
  - Tích hợp AI Model Chat (Ollama: gemma3:1b, mistral) vào toàn bộ app, popup chat bubble hiện đại, chọn ngôn ngữ, đổi model dễ dàng.  
    Integrated AI Model Chat (Ollama: gemma3:1b, mistral) into entire app, modern popup chat bubble, language selection, easy model switching.
  - Cải thiện UI/UX chat, thêm icon lá cờ, bo tròn, bóng đổ, nút xóa lịch sử chat.  
    Improved chat UI/UX, added flag icons, rounded corners, shadows, clear chat history button.
  - Bổ sung hướng dẫn cài đặt và sử dụng AI vào README.  
    Added AI installation and usage guide to README.

- **2025-08-10 đến 2025-08-15:**  
  - Thêm tỷ giá ngoại tệ Vietcombank (3 ngày gần nhất) với trang riêng và tính năng tìm kiếm theo ngày.  
    Added Vietcombank exchange rates (last 3 days) with dedicated page and date search feature.
  - Thêm bảng top 30 công ty hot theo ngành, số lượng linh động.  
    Added top 30 hot companies by industry table, flexible quantity.
  - Refactor Controller, Repository, Service theo chuẩn SOLID, dùng dependency injection.  
    Refactored Controller, Repository, Service following SOLID principles, using dependency injection.
  - Thêm file database mẫu (`stock_app.sql`) để dễ import.  
    Added sample database file (`stock_app.sql`) for easy import.
  - Chuẩn hóa Controller theo SOLID, tách Service/Repository/Interface.  
    Standardized Controller following SOLID, separated Service/Repository/Interface.
  - Footer đẹp, có icon, thông tin cá nhân.  
    Beautiful footer with icons and personal information.
  - Sửa UI homepage: header gọn, card mã nổi bật đều, responsive tốt.  
    Homepage UI improved: compact header, even featured cards, better responsive.

- **Các phiên bản trước đó / Earlier versions:**  
  - Thêm autocomplete tìm kiếm mã cổ phiếu với AJAX.  
    Added autocomplete stock symbol search with AJAX.
  - Tối ưu giao diện Bootstrap, sửa lỗi encoding Python.  
    Optimized Bootstrap UI, fixed Python encoding bug.
  - Tích hợp crawl giá lịch sử từ vnstock, lưu MySQL, kiểm tra trùng lặp.  
    Integrated historical price crawling from vnstock, MySQL saving, duplicate checking.
  - Biểu đồ nến/đường với Chart.js, bảng dữ liệu có phân trang.  
    Candlestick/line charts with Chart.js, paginated data tables.
  - Kiến trúc Laravel 12 + Python 3, responsive UI.  
    Laravel 12 + Python 3 architecture, responsive UI.

---

## 📸 Ảnh màn hình / Screenshots

![Screenshot](public/images/Screenshot_1.png)
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
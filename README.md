# Xem Giá Cổ Phiếu Việt Nam

Ứng dụng web giúp tra cứu giá cổ phiếu Việt Nam, sử dụng **Laravel 12 (PHP)** và **Python**.  
Tự động cập nhật dữ liệu, giao diện đẹp, dễ dùng, có autocomplete tìm mã cổ phiếu.

---

## 🚀 Tính năng nổi bật

- **Tra cứu giá lịch sử cổ phiếu**: Xem bảng giá, biểu đồ nến/đường, khối lượng giao dịch.
- **Tìm kiếm mã cổ phiếu nhanh**: Autocomplete, cập nhật danh sách mã tự động từ Python.
- **Lưu dữ liệu vào MySQL**: Tránh crawl trùng lặp, chỉ cập nhật khi cần.
- **Tích hợp Python**: Lấy dữ liệu từ vnstock, cập nhật cả giá và danh sách mã.
- **Giao diện responsive**: Đẹp, dễ dùng trên mọi thiết bị, Bootstrap 4 + icon.
- **Kiến trúc chuẩn SOLID**: Controller, Service, Repository, Interface rõ ràng, dễ mở rộng.
- **Footer cá nhân hóa**: Hiển thị thông tin tác giả, email, GitHub, LinkedIn ở mọi trang.

---

## 🆕 Nhật ký cập nhật

- **2025-08-10:**  
  - Refactor lại StockController, StockRepository, và StockService; bổ sung method mới, cải thiện cấu trúc.
  - Tích hợp lại các script Python để lấy dữ liệu cổ phiếu.
  - Đã thêm file database mẫu (`stock_app.sql`) để người dùng dễ dàng import và sử dụng.

- **2025-08-09:**  
  - Chuẩn hóa Controller theo SOLID, tách Service/Repository/Interface.
  - Footer đẹp, có icon, thông tin cá nhân.
  - Sửa UI homepage: header gọn, card mã nổi bật đều, responsive tốt.
  - Cập nhật README, bổ sung hướng dẫn, tính năng mới.
- **Trước đó:**  
  - Thêm autocomplete tìm kiếm mã cổ phiếu.
  - Tối ưu giao diện, sửa lỗi encoding Python.
  - Tích hợp crawl giá lịch sử, lưu DB, kiểm tra trùng lặp, giao diện Bootstrap.

---

## 📸 Ảnh màn hình

![Screenshot](public/images/Screenshot_1.png)
![Screenshot](public/images/Screenshot_2.png)
![Screenshot](public/images/Screenshot_3.png)
![Screenshot](public/images/Screenshot_4.png)

---

## ⚡ Hướng dẫn cài đặt

1. **Clone & cài đặt PHP:**
    ```bash
    git clone https://github.com/nhatnguyen94/stock-app.git
    cd stock-app
    composer install
    cp .env.example .env
    php artisan key:generate
    ```

2. **Cấu hình MySQL trong `.env`**

3. **Tạo database `stock_app` trong MySQL**

4. **Chạy migrate:**
    ```bash
    php artisan migrate
    ```

5. **Thêm database mẫu:**  
   Đã thêm file `stock_app.sql` chứa dữ liệu mẫu.  
   Để import vào MySQL, chạy lệnh sau:
    ```bash
    mysql -u root -p stock_app < stock_app.sql
    ```
   (Thay `root` bằng user của bạn nếu khác)

6. **Cài Python & vnstock:**
    ```bash
    pip install vnstock
    ```

7. **Kiểm tra script Python:**
    ```bash
    python get_stock.py FPT
    python get_stock_list.py
    ```

8. **Chạy server:**
    ```bash
    php artisan serve
    ```
    Truy cập: http://127.0.0.1:8000/

---

## ⚡ Quick Start

1. **Clone & install PHP dependencies:**
    ```bash
    git clone https://github.com/nhatnguyen94/stock-app.git
    cd stock-app
    composer install
    cp .env.example .env
    php artisan key:generate
    ```

2. **Configure MySQL in `.env`**

3. **Create database `stock_app` in MySQL**

4. **Run migrations:**
    ```bash
    php artisan migrate
    ```

5. **Add sample database:**  
   Sample data file `stock_app.sql` is included.  
   To import into MySQL, run:
    ```bash
    mysql -u root -p stock_app < stock_app.sql
    ```
   (Replace `root` with your MySQL user if different)

6. **Install Python & vnstock:**
    ```bash
    pip install vnstock
    ```

7. **Test Python scripts:**
    ```bash
    python get_stock.py FPT
    python get_stock_list.py
    ```

8. **Start server:**
    ```bash
    php artisan serve
    ```
    Visit: http://127.0.0.1:8000/

---

## 💡 Cách sử dụng

- Truy cập trang chủ, nhập mã cổ phiếu (ví dụ: FPT, VCB, E1VFVN30...)
- Xem bảng giá lịch sử, biểu đồ, thông tin chi tiết.
- Tìm kiếm mã cổ phiếu nhanh với autocomplete.
- Footer luôn hiển thị thông tin tác giả.

---

## 🛠️ Kiến trúc & Công nghệ

- **Laravel 12** (PHP)
- **Python 3 + vnstock**
- **Bootstrap 4, Bootstrap Icons**
- **SOLID: Controller, Service, Repository, Interface**
- **MySQL**

---

## 👤 Tác giả

**Sun Nguyen**  
Email: [nhat.nguyenminh94@gmail.com](mailto:nhat.nguyenminh94@gmail.com)  
GitHub: [nhatnguyen94/stock-app](https://github.com/nhatnguyen94/stock-app)  
LinkedIn: [Sun Nguyen](https://www.linkedin.com/in/sunnguyen3011/)

---

MIT License © 2025 Sun Nguyen

---

# Vietnamese Stock Price Viewer

A web application for viewing Vietnamese stock prices, built with **Laravel 12 (PHP)** and **Python**.  
Auto-update data, beautiful responsive UI, fast autocomplete for stock symbols.

---

## 🚀 Key Features

- **View historical stock prices**: Table, candlestick/line chart, volume.
- **Fast stock symbol search**: Autocomplete, auto-update symbol list from Python.
- **Save data to MySQL**: Avoid redundant crawling, update only when needed.
- **Python integration**: Crawl data from vnstock, update both prices and symbol list.
- **Responsive UI**: Beautiful, easy to use on all devices, Bootstrap 4 + icons.
- **SOLID architecture**: Clear Controller, Service, Repository, Interface.
- **Personalized footer**: Author info, email, GitHub, LinkedIn on every page.

---

## 🆕 Update Log

- **2025-08-10:**  
  - Refactored StockController, StockRepository, and StockService; added new methods and improved structure.
  - Reintroduced Python scripts for stock data retrieval.
  - Added sample database file (`stock_app.sql`) for easy import and usage.

- **2025-08-09:**  
  - Refactored Controller to SOLID, separated Service/Repository/Interface.
  - Improved footer with icons and author info.
  - Homepage UI improved: compact header, even featured cards, better responsive.
  - Updated README, added instructions and new features.

- **Earlier:**  
  - Added autocomplete for stock symbol search.
  - UI improvements, fixed Python encoding bug.
  - Integrated historical price crawling, DB saving, duplicate check, Bootstrap UI.

## 📸 Screenshots

![Screenshot](public/images/Screenshot_1.png)
![Screenshot](public/images/Screenshot_2.png)
![Screenshot](public/images/Screenshot_3.png)
![Screenshot](public/images/Screenshot_4.png)

---

## ⚡ Quick Start

1. **Clone & install PHP dependencies:**
    ```bash
    git clone https://github.com/nhatnguyen94/stock-app.git
    cd stock-app
    composer install
    cp .env.example .env
    php artisan key:generate
    ```

2. **Configure MySQL in `.env`**

3. **Create database `stock_app` in MySQL**

4. **Run migrations:**
    ```bash
    php artisan migrate
    ```

5. **Add sample database:**  
   Sample data file `stock_app.sql` is included.  
   To import into MySQL, run:
    ```bash
    mysql -u root -p stock_app < stock_app.sql
    ```
   (Replace `root` with your MySQL user if different)

6. **Install Python & vnstock:**
    ```bash
    pip install vnstock
    ```

7. **Test Python scripts:**
    ```bash
    python get_stock.py FPT
    python get_stock_list.py
    ```

8. **Start server:**
    ```bash
    php artisan serve
    ```
    Visit: http://127.0.0.1:8000/

---

## 💡 Usage

- Go to homepage, enter stock symbol (e.g. FPT, VCB, E1VFVN30...)
- View historical price table, charts, and details.
- Fast autocomplete for stock symbol search.
- Footer always shows author info.

---

## 🛠️ Architecture & Technology

- **Laravel 12** (PHP)
- **Python 3 + vnstock**
- **Bootstrap 4, Bootstrap Icons**
- **SOLID: Controller, Service, Repository, Interface**
- **MySQL**

---

## 👤 Author

**Sun Nguyen**  
Email: [nhat.nguyenminh94@gmail.com](mailto:nhat.nguyenminh94@gmail.com)  
GitHub: [nhatnguyen94/stock-app](https://github.com/nhatnguyen94/stock-app)  
LinkedIn: [Sun Nguyen](https://www.linkedin.com/in/sunnguyen3011/)

---

MIT License © 2025 Sun Nguyen
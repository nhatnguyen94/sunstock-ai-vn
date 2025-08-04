# Ứng dụng Xem Giá Cổ Phiếu

## Screenshot

![Screenshot](public/images/Screenshot_1.png)

**Xem dữ liệu lịch sử cổ phiếu với Laravel 12 + Python (vnstock)**  
_Cập nhật ngày 08/04/2025: Đã thêm tính năng tìm kiếm mã cổ phiếu nhanh (autocomplete)._

---

## Tính năng

- Tìm kiếm và xem dữ liệu lịch sử cổ phiếu (ngày, giá mở/cao/thấp/đóng, khối lượng)
- Lưu dữ liệu vào MySQL, tránh crawl trùng lặp
- Tìm kiếm mã cổ phiếu nhanh (autocomplete)
- Tích hợp Python để lấy dữ liệu từ vnstock
- Giao diện đẹp, responsive với Bootstrap 4
- Tự động kiểm tra và cập nhật danh sách mã cổ phiếu mới

---

## Hướng dẫn cài đặt

1. **Clone & cài đặt:**
    ```bash
    git clone https://github.com/nhatnguyen94/stock-app.git
    cd stock-app
    composer install
    cp .env.example .env
    php artisan key:generate
    ```

2. **Cấu hình `.env` cho MySQL**  
   (Điền thông tin DB vào file .env)

3. **Tạo database `stock_app` trong MySQL**

4. **Chạy migrate:**
    ```bash
    php artisan migrate
    ```

5. **Cài Python & vnstock:**
    ```bash
    pip install vnstock
    ```

6. **Kiểm tra script Python:**
    ```bash
    python get_stock.py E1VFVN30
    ```

7. **Chạy server:**
    ```bash
    php artisan serve
    ```
    Truy cập: http://127.0.0.1:8000/stock?symbol=FPT

---

## Cách sử dụng

- Truy cập trang chủ, nhập mã cổ phiếu (ví dụ: FPT, VCB, E1VFVN30...)
- Xem bảng giá lịch sử, biểu đồ, và thông tin chi tiết
- Tìm kiếm mã cổ phiếu nhanh với autocomplete

---

## Nhật ký cập nhật

- **08/04/2025:** Thêm tính năng autocomplete tìm kiếm mã cổ phiếu, tối ưu giao diện, sửa lỗi encoding Python.
- **Trước đó:** Tích hợp lấy dữ liệu lịch sử giá, lưu vào DB, kiểm tra trùng lặp, giao diện Bootstrap.

---

## Tác giả

Sun Nguyen  
Email: nhat.nguyenminh94@gmail.com  
GitHub: https://github.com/nhatnguyen94/stock-app

---

MIT License © 2025

---

---

# Laravel Stock App

**A simple Laravel 12 + Python (vnstock) stock data viewer.**  
_Updated 2025-08-04: Added fast stock symbol search/autocomplete feature._

---

## Features

- Search and view historical stock data (date, open, high, low, close, volume)
- Save data to MySQL, avoid redundant crawling
- Fast stock symbol autocomplete
- Python integration for data crawling (vnstock)
- Responsive UI with Bootstrap 4
- Auto-update stock symbol list

---

## Quick Start

1. **Clone & install dependencies:**
    ```bash
    git clone https://github.com/nhatnguyen94/stock-app.git
    cd stock-app
    composer install
    cp .env.example .env
    php artisan key:generate
    ```

2. **Configure `.env` for MySQL**

3. **Create database `stock_app` in MySQL**

4. **Run migrations:**
    ```bash
    php artisan migrate
    ```

5. **Install Python & vnstock:**
    ```bash
    pip install vnstock
    ```

6. **Test Python script:**
    ```bash
    python get_stock.py E1VFVN30
    ```

7. **Start server:**
    ```bash
    php artisan serve
    ```
    Visit: http://127.0.0.1:8000/stock?symbol=FPT

---

## Usage

- Access homepage, enter stock code (e.g. FPT, VCB, E1VFVN30...)
- View historical price table, charts, and details
- Use fast autocomplete to search stock symbols

---

## Update Log

- **2025-08-04:** Added autocomplete for stock symbol search, UI improvements, fixed Python encoding bug.
- **Earlier:** Integrated historical price crawling, DB saving, duplicate check, Bootstrap UI.

---

## Author

Sun Nguyen  
Email: nhat.nguyenminh94@gmail.com  
GitHub: https://github.com/nhatnguyen94/stock-app

---

MIT License © 2025
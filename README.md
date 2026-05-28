
# Sun Stock AI – Vietnam Stock Platform

> 🇬🇧 [English](#english) | 🇻🇳 [Tiếng Việt](#tiếng-việt)

---

<a name="english"></a>
# 🇬🇧 English

**Sun Stock AI** is a web platform for tracking and managing Vietnamese stocks, built with Laravel 12 and Python. It features AI-powered market analysis, portfolio management, exchange rates, news, and a full role-based admin system.

## 📸 Screenshots

| Homepage | Stock Detail |
|---|---|
| ![Homepage](public/images/ss_homepage.png) | ![Stock VCB](public/images/ss_stock_vcb.png) |

| Exchange Rates | Compare Stocks |
|---|---|
| ![Exchange](public/images/ss_exchange.png) | ![Compare](public/images/ss_compare.png) |

| Portfolio | Login |
|---|---|
| ![Portfolio](public/images/ss_portfolio.png) | ![Login](public/images/ss_login.png) |

| Register |
|---|
| ![Register](public/images/ss_register.png) |

| AI CHAT BOT |
|---|
| ![AI CHAT BOT](public/images/Screenshot_7.png) |

## 🚀 Features

- **Stock Viewer** — Search any Vietnamese stock symbol with autocomplete, view historical price charts and data tables.
- **Compare Stocks** — Side-by-side chart comparison for multiple symbols.
- **Hot Industries** — Discover top-performing stocks in Banking, Real Estate, and IT sectors.
- **Portfolio Management** — Create portfolios, track holdings, monitor profit/loss in real time, set price targets and stop-loss alerts, get AI rebalancing suggestions.
- **Exchange Rates** — View Vietcombank (VCB) foreign exchange rates, updated daily.
- **Market News** — Real-time news feed from VnExpress RSS.
- **AI Chat & Prediction** — Ask financial questions and get market predictions powered by OpenRouter AI (supports multiple LLM models).
- **User Accounts** — Register, log in, email verification, profile management.
- **Role-Based Access Control (RBAC)** — Separate admin login, role management (Admin, Webadmin, AdminSupport, User), permission gates.
- **Admin Panel** — Manage users, stocks, news, portfolios, and view activity timeline.

## 🛠️ Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12 (PHP 8.2+) |
| Data Fetching | Python 3 + vnstock library |
| AI | OpenRouter API (supports any OpenRouter-compatible LLM) |
| Database | MySQL |
| Frontend | Blade Templates + Bootstrap 5 + Bootstrap Icons |
| Architecture | SOLID — Controller / Service / Repository / Interface |

## ⚡ Quick Start

**Requirements:** PHP 8.2+, Composer, MySQL, Node.js, Python 3.10+

```bash
# 1. Clone & install dependencies
git clone https://github.com/nhatnguyen94/sunstock-ai-vn.git
cd stock-app
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
```

```bash
# 2. Configure .env
DB_DATABASE=stock_app
DB_USERNAME=root
DB_PASSWORD=
OPENROUTER_API_KEY=your_key_here
```

```bash
# 3. Database setup (choose one)
php artisan migrate --seed            # Fresh setup with seeds
# OR import sample data:
mysql -u root -p stock_app < stock_app.sql
```

```bash
# 4. Install Python dependencies
pip install vnstock
```

```bash
# 5. Sync stock data
php artisan stock:sync          # Sync stock symbols
php artisan stock:sync-prices   # Sync historical prices
```

```bash
# 6. Run the app
php artisan serve
# Visit: http://127.0.0.1:8000
```

## 🤖 AI Chat Setup

1. Get a free API key at [openrouter.ai](https://openrouter.ai)
2. Add to `.env`: `OPENROUTER_API_KEY=your_key_here`
3. The chat widget appears on the bottom-right of every page
4. To change the AI model, edit `app/Frontend/Services/AiService.php`
5. Browse available models at [openrouter.ai/models](https://openrouter.ai/models)

## 📁 Project Structure

```
app/
  Frontend/   Controllers, Services, Repositories, Interfaces (user-facing)
  Backend/    Controllers (admin panel)
  Models/     Shared Eloquent models
py/           Python scripts (stock data, exchange rates, AI)
resources/views/  Blade templates
docs/         Developer documentation
```

> See [docs/STRUCTURE.md](docs/STRUCTURE.md) for the full architecture reference.

## 🆕 Changelog

| Date | Update |
|---|---|
| 2026-05-28 | Full documentation audit & expansion |
| 2026-05-16 | Improved search, added ETF support, backend pipeline fixes |
| 2026-05-02 | RBAC system, separate admin login, role management |
| 2026-05-01 | Architecture cleanup, removed legacy namespaces |
| 2025-08-25 | Email verification, AI market prediction |
| 2025-08-23 | Switched AI to OpenRouter, multi-model support |

> Full history: [docs/HISTORY.md](docs/HISTORY.md)

## 👤 Author

**Sun Nguyen**  
Email: [nhat.nguyenminh94@gmail.com](mailto:nhat.nguyenminh94@gmail.com)  
GitHub: [nhatnguyen94/sunstock-ai-vn](https://github.com/nhatnguyen94/sunstock-ai-vn)  
LinkedIn: [Sun Nguyen](https://www.linkedin.com/in/sunnguyen3011/)

MIT License © 2025–2026

---

<a name="tiếng-việt"></a>
# 🇻🇳 Tiếng Việt

**Sun Stock AI** là nền tảng web tra cứu và quản lý cổ phiếu Việt Nam, xây dựng trên Laravel 12 và Python. Tích hợp AI phân tích thị trường, quản lý danh mục đầu tư, tỷ giá ngoại tệ, tin tức và hệ thống phân quyền admin đầy đủ.

## 🚀 Tính năng

- **Xem cổ phiếu** — Tìm kiếm mã cổ phiếu với autocomplete, xem biểu đồ giá lịch sử và bảng dữ liệu.
- **So sánh cổ phiếu** — So sánh biểu đồ nhiều mã cổ phiếu cùng lúc.
- **Ngành hot** — Khám phá cổ phiếu nổi bật trong các ngành Ngân hàng, Bất động sản, CNTT.
- **Quản lý danh mục** — Tạo danh mục đầu tư, theo dõi lợi nhuận/lỗ theo thời gian thực, đặt mức giá mục tiêu và cắt lỗ, gợi ý cân bằng danh mục bằng AI.
- **Tỷ giá ngoại tệ** — Xem tỷ giá Vietcombank cập nhật hàng ngày.
- **Tin tức thị trường** — Cập nhật tin tức từ VnExpress RSS.
- **AI Chat & Dự đoán** — Hỏi đáp tài chính và dự đoán thị trường qua OpenRouter AI (hỗ trợ nhiều model LLM).
- **Tài khoản người dùng** — Đăng ký, đăng nhập, xác thực email, quản lý hồ sơ.
- **Phân quyền (RBAC)** — Đăng nhập admin riêng biệt, quản lý vai trò (Admin, Webadmin, AdminSupport, User).
- **Trang quản trị** — Quản lý người dùng, cổ phiếu, tin tức, danh mục và xem nhật ký hoạt động.

## 🛠️ Công nghệ sử dụng

| Lớp | Công nghệ |
|---|---|
| Backend | Laravel 12 (PHP 8.2+) |
| Lấy dữ liệu | Python 3 + thư viện vnstock |
| AI | OpenRouter API |
| Cơ sở dữ liệu | MySQL |
| Giao diện | Blade Templates + Bootstrap 5 + Bootstrap Icons |
| Kiến trúc | SOLID — Controller / Service / Repository / Interface |

## ⚡ Cài đặt nhanh

**Yêu cầu:** PHP 8.2+, Composer, MySQL, Node.js, Python 3.10+

```bash
# 1. Clone & cài đặt
git clone https://github.com/nhatnguyen94/sunstock-ai-vn.git
cd stock-app
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
```

```bash
# 2. Cấu hình .env
DB_DATABASE=stock_app
DB_USERNAME=root
DB_PASSWORD=
OPENROUTER_API_KEY=your_key_here
```

```bash
# 3. Khởi tạo database (chọn một trong hai)
php artisan migrate --seed            # Tạo mới từ đầu
# HOẶC import dữ liệu mẫu:
mysql -u root -p stock_app < stock_app.sql
```

```bash
# 4. Cài Python
pip install vnstock
```

```bash
# 5. Đồng bộ dữ liệu cổ phiếu
php artisan stock:sync          # Đồng bộ danh sách mã
php artisan stock:sync-prices   # Đồng bộ giá lịch sử
```

```bash
# 6. Khởi động server
php artisan serve
# Truy cập: http://127.0.0.1:8000
```

## 🤖 Cài đặt AI Chat

1. Lấy API key miễn phí tại [openrouter.ai](https://openrouter.ai)
2. Thêm vào `.env`: `OPENROUTER_API_KEY=your_key_here`
3. Widget chat xuất hiện ở góc phải dưới màn hình
4. Để đổi model AI, chỉnh sửa `app/Frontend/Services/AiService.php`
5. Xem danh sách model tại [openrouter.ai/models](https://openrouter.ai/models)

## 🆕 Nhật ký cập nhật

| Ngày | Nội dung |
|---|---|
| 2026-05-28 | Rà soát và mở rộng toàn bộ tài liệu |
| 2026-05-16 | Cải tiến tìm kiếm, bổ sung ETF, sửa lỗi UI/UX |
| 2026-05-02 | Hệ thống RBAC, đăng nhập admin riêng, quản lý vai trò |
| 2026-05-01 | Dọn dẹp kiến trúc, xóa namespace cũ |
| 2025-08-25 | Xác thực email, AI dự đoán thị trường |
| 2025-08-23 | Chuyển AI sang OpenRouter, hỗ trợ nhiều model |

> Xem đầy đủ: [docs/HISTORY.md](docs/HISTORY.md)

## 👤 Tác giả

**Sun Nguyen**  
Email: [nhat.nguyenminh94@gmail.com](mailto:nhat.nguyenminh94@gmail.com)  
GitHub: [nhatnguyen94/sunstock-ai-vn](https://github.com/nhatnguyen94/sunstock-ai-vn)  
LinkedIn: [Sun Nguyen](https://www.linkedin.com/in/sunnguyen3011/)

MIT License © 2025–2026

# Sun Stock AI – Vietnam Stock Platform

> 🇬🇧 [English](#english) | 🇻🇳 [Tiếng Việt](#tiếng-việt)

---

<a name="english"></a>
# 🇬🇧 English

**Sun Stock AI** is a web platform for tracking and managing Vietnamese stocks, built with Laravel 12 and Python. It features AI-powered market analysis, portfolio management with price alerts, exchange rates, news, a stock screener, and a full role-based admin system with self-service, DB-driven permission management.

## 📸 Screenshots

> Captured on 2026-09-20 from the running Docker stack with real market data. Signed-in pages use a throwaway demo account with sample holdings (removed afterwards). No credentials, tokens, e-mail addresses, admin/security pages or `.env` values appear in any image. A page-by-page walkthrough with more detail is in **[docs/WEBSITE_TOUR.md](docs/WEBSITE_TOUR.md)**.

### Market at a glance

| Home — hero, live ticker, search | Home — market overview (indices, breadth, liquidity, movers) |
|---|---|
| ![Home](docs/screenshots/01-home.jpg) | ![Market overview](docs/screenshots/02-home-market.jpg) |

### Analysis

| Stock chart — candles, volume, MA/Bollinger, RSI, MACD | Company profile |
|---|---|
| ![Stock chart](docs/screenshots/03-stock-chart.jpg) | ![Company profile](docs/screenshots/04-company-fpt.jpg) |

| Compare stocks (relative growth) | Stock screener (P/E, P/B, ROE, dividend…) |
|---|---|
| ![Compare](docs/screenshots/05-compare.jpg) | ![Screener](docs/screenshots/06-screener.jpg) |

### AI

| AI chat assistant | AI weekly market prediction (grounded in real data) |
|---|---|
| ![AI chat](docs/screenshots/11-ai-chat.jpg) | ![AI prediction](docs/screenshots/12-ai-predict.jpg) |

### Funds, gold and FX

| Open-end funds | Fund detail (returns + NAV curve) |
|---|---|
| ![Funds](docs/screenshots/07-funds.jpg) | ![Fund detail](docs/screenshots/07b-fund-detail.jpg) |

| Gold prices (SJC · BTMC · world) | Exchange rates |
|---|---|
| ![Gold](docs/screenshots/08-gold.jpg) | ![Exchange rates](docs/screenshots/09-exchange-rate.jpg) |

### Your money (demo account)

| Portfolios | Portfolio detail — P&L, performance, allocation |
|---|---|
| ![Portfolios](docs/screenshots/14-portfolio-list.jpg) | ![Portfolio detail](docs/screenshots/15-portfolio-detail.jpg) |

| Holdings, buy/sell ledger actions | Watchlist |
|---|---|
| ![Holdings](docs/screenshots/15b-portfolio-holdings.jpg) | ![Watchlist](docs/screenshots/16-watchlist.jpg) |

### Mobile

<p>
  <img src="docs/screenshots/13-mobile-home.jpg" alt="Mobile home" width="260">
  &nbsp;&nbsp;
  <img src="docs/screenshots/13b-mobile-market.jpg" alt="Mobile market overview" width="260">
</p>

## 🚀 Features

- **Stock Viewer** — Search any Vietnamese stock symbol with autocomplete, view historical price charts (candlestick & line) and data tables.
- **📊 Technical Indicators** — Overlay MA20/50/200, Bollinger Bands on the main chart; RSI(14) and MACD(12,26,9) display as dedicated sub-charts below. All indicators update when filtering by time period (1M/3M/6M/1Y/All).
- **🏦 Company Financials** — On-demand financial statement viewer: Income Statement, Balance Sheet, Cash Flow, and Financial Ratios — quarterly or annual — powered by the KBS data source via vnstock.
- **🔎 Stock Screener** — Filter and rank the whole market by financial ratios (P/E, ROE, ...) sourced from the synced financial data.
- **🏢 Company Profile** — `/company/{symbol}`: overview & valuation snapshot, ownership structure and major shareholders (donut charts), board / executives / supervisory board with their holdings, subsidiaries & affiliates, and corporate events (upcoming dividends & meetings, insider trades). Cached in the DB with stale-while-revalidate; first visit loads in ~4s, later ones are instant.
- **💼 Open-ended Funds** — `/funds`: 68 Fmarket funds filterable by type / management company / name and sortable by any return window; per-fund page with NAV chart (green/red vs. start of period), max drawdown, volatility, asset allocation and top holdings (linked to company profiles); compare up to 4 funds side by side.
- **Compare Stocks** — Side-by-side chart comparison for multiple symbols.
- **Hot Industries** — Discover top-performing stocks in Banking, Real Estate, and IT sectors.
- **Portfolio Management** — Create portfolios, track holdings, monitor profit/loss in real time from actually-synced prices, set price targets and stop-loss levels (with automatic one-shot email alerts when crossed), get AI rebalancing suggestions.
- **Market Overview & Watchlist** — the home page now opens with the market: VN-Index / VN30 / HNX / UPCoM, a 30-session VN-Index chart with volume, breadth (advancers / decliners / ceiling / floor), liquidity per exchange, top gainers / losers / most-traded stocks, and a real ticker tape (refreshes every minute while the market is open). Signed-in users follow stocks with ★ (home, stock page, company page) and manage them at `/watchlist` with live prices.
- **Exchange Rates** — View Vietcombank (VCB) foreign exchange rates by date, updated daily.
- **Gold Prices** — `/gold`: SJC bars and Bảo Tín Minh Châu gold/silver (from vnstock), world gold converted to VND/lượng with the domestic premium, day change, lượng/chỉ toggle, value/P&L calculator and a price-history chart that fills in as the system records a quote every 15 minutes. Lives with Exchange Rates under one **Thị trường** menu.
- **Market News** — Aggregated news from 5 RSS sources (VnExpress ×2, CafeF ×2, Dân Trí ×1) stored in DB with categories (Kinh doanh, Chứng khoán, Thị trường, Doanh nghiệp). Dedicated `/news` page with category filtering, search, and pagination. Synced every 30 minutes via scheduler.
- **AI Chat & Prediction** — Ask financial questions and get market predictions, powered by the Groq API (free tier, ~0.4s responses).
- **User Accounts** — Register, log in, email verification, forgot/reset password, profile management (avatar, birthday, gender, address, bio).
- **Role-Based Access Control (RBAC)** — Separate admin login; roles and fine-grained permissions are managed entirely from the backend UI (**Admin > Vai trò / Quyền hạn**) — new roles and overlapping permissions don't require any code change. See [docs/RBAC.md](docs/RBAC.md).
- **Admin Panel** — Manage users, roles & permissions, stocks, news (incl. categories), portfolios, sync status, an activity timeline, self-service password change, and a live queue monitoring dashboard (real-time "currently processing" / "recently finished" job feed).

## 🛠️ Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12 (PHP 8.2+) |
| Data Fetching | Python 3 + vnstock 4.x library |
| AI | Groq API — `llama-3.3-70b-versatile` (free, 14,400 req/day, ~0.4s response) |
| Database | MySQL 8 (RANGE-partitioned by year for scale) |
| Charts | TradingView Lightweight Charts 5 (bundled locally by Vite: candles, area, volume, multi-pane indicators) + in-repo SVG donut/bars |
| Frontend | Blade Templates + Bootstrap 4.5 (main site, CDN) + Tailwind CSS 4 (Vite, page-specific styles) |
| Admin Panel | Blade Templates + Tabler / Bootstrap 5 (CDN) |
| Authorization | Custom RBAC (`roles`) + DB-driven permissions (`permissions`/`permission_role`) — see [docs/RBAC.md](docs/RBAC.md) |
| Architecture | SOLID — Controller / Service / Repository / Interface, strict Frontend/Backend namespace separation |
| Queue | Redis Queue — 6 workers (Docker supervisor), custom monitoring dashboard at `/admin/queue` |

## 🔐 Security & environment variables

- **Never commit `.env`, `.env.docker`, or `.env.xampp`** — they hold real secrets and are already gitignored. Only `.env.example` is tracked in git, and it contains **placeholders only** (`your_groq_api_key`, empty passwords) — never replace those placeholders with real values before committing.
- `docker-compose.yml` requires `DB_PASSWORD` and `DB_ROOT_PASSWORD` to be set with **no insecure default** — the stack refuses to start rather than silently using a blank/weak MySQL password. Generate strong random values for both, e.g. `openssl rand -base64 24`.
- `GROQ_API_KEY` (AI chat) and `VNSTOCK_API_KEY` (optional, sponsored vnstock tier) are personal API keys — get your own free key at [console.groq.com](https://console.groq.com) and keep it out of any file that gets committed or shared.
- If a real secret is ever accidentally committed, rotating it (changing the password/regenerating the API key) is mandatory — removing it from a later commit does **not** remove it from git history.
- MySQL's port is bound to `127.0.0.1` only in `docker-compose.yml` (not exposed to the LAN/internet); keep that binding if you change the compose file.

## ⚡ Quick Start (Docker — recommended)

**Requirements:** Docker Desktop

```bash
# 1. Clone
git clone https://github.com/nhatnguyen94/sunstock-ai-vn.git
cd stock-app
```

```bash
# 2. Configure .env (copy from .env.docker as base — DB_HOST=mysql, REDIS_HOST=redis are already set for the Docker network)
cp .env.docker .env
# Then edit .env and set your own real values for:
#   DB_PASSWORD, DB_ROOT_PASSWORD   (required — no insecure default, see Security section above)
#   GROQ_API_KEY                    (for the AI chat feature)
```

```bash
# 3. Add hosts entry (Windows: C:\Windows\System32\drivers\etc\hosts)
# 127.0.0.1 sunstock-local.dev
```

```bash
# 4. Start containers
docker compose up -d
docker exec stock-app-php-1 composer install
docker exec stock-app-php-1 php artisan migrate --seed
docker exec stock-app-php-1 php artisan sync:stock-data
```

```bash
# 5. Visit https://sunstock-local.dev
```

> See [docs/DOCKER.md](docs/DOCKER.md) for full Docker setup, SSL certificate, MySQL Workbench connection.

## ⚡ Quick Start (XAMPP / manual)

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
# 2. Configure .env — use your own real values, never commit them
DB_DATABASE=stock_app
DB_USERNAME=root
DB_PASSWORD=your_own_mysql_password
GROQ_API_KEY=your_own_groq_api_key
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
php artisan sync:stock-data         # Sync stock symbols
php artisan sync:stock-prices       # Sync historical prices
```

```bash
# 6. Run the app
php artisan serve
# Visit: http://127.0.0.1:8000
```

## 🤖 AI Chat Setup

1. Get a free API key at [console.groq.com](https://console.groq.com) (no credit card required)
2. Add to `.env`: `GROQ_API_KEY=gsk_...` — keep this out of any committed file
3. The chat widget appears on the bottom-right of every page
4. To change the AI models, set `GROQ_MODELS=model-a,model-b` in `.env` (tried in order; empty = built-in defaults in `AiService::DEFAULT_MODELS`). Groq retires models — list the current ones with `GET https://api.groq.com/openai/v1/models`
5. Available free models: `llama-3.3-70b-versatile`, `llama3-70b-8192`, `gemma2-9b-it`

## 📁 Project Structure

```
app/
  Frontend/   Controllers, Services, Repositories, Interfaces (user-facing)
  Backend/    Controllers, Services, Repositories, Interfaces (admin panel: users, roles, permissions, stocks, news, portfolios)
  Models/     Shared Eloquent models
py/           Python scripts (stock data, exchange rates, AI)
resources/views/  Blade templates
docs/         Developer documentation
```

> See [docs/STRUCTURE.md](docs/STRUCTURE.md) for the full architecture reference, [docs/RBAC.md](docs/RBAC.md) for the permission system, and [docs/TESTING.md](docs/TESTING.md) for the testing conventions.

## 🆕 Changelog

| Date | Update |
|---|---|
| 2026-09-20 | **Nine changes in one day** — **Gold price page** (`/gold`) under a new *Thị trường* menu · **Portfolio rework** (money-unit bug fixed, performance chart, allocation, dividends, CSV) · **Market overview, real ticker, watchlist and buy/sell ledger** on the home page · **Stock page first click ~10 s → ~1–3 s** · **Admin redesign** (Tabler 1.5, Ctrl+K palette, dark mode) · **Weekly database backup** outside Docker (`db:backup`) · **AI chat + AI prediction fixed** (retired Groq models, dead popup buttons, answers grounded in real market data) · **vnai stopped editing `AGENTS.md`** · **New screenshots + [website tour](docs/WEBSITE_TOUR.md)** · **changelog/history condensed** |
| 2026-09-19 | **Company Profile** (`/company/{symbol}`) and **open-end Fund catalog** (`/funds`, detail, compare); charts moved from ApexCharts (CDN) to bundled **TradingView Lightweight Charts** (MA/Bollinger overlays, RSI/MACD panes) · **Search autocomplete redesign** (single highlight, ranked results, repaired 182 garbled company names) · queue monitor "delete all failed jobs" · admin sidebar fixes |
| 2026-09-17 | **News category management** and **self-service admin password change** (`/admin/news-categories`, `/admin/account`) |
| 2026-09-16 | **Real-time queue activity** (which job runs now, elapsed timer, recent jobs, `queue_job_logs`) · custom queue monitor replaces Horizon · every Python call wrapped by `PythonRunner` (hard timeout) · sync speed-up (6 Redis workers) |
| 2026-09-15 | **Queue monitoring dashboard** · **scalable DB-driven permissions** (Admin > Vai trò / Quyền hạn) · forgot/reset password · extended profile fields (avatar, birthday, gender, address, bio) · auth/authorization + profile/portfolio audits, docs consistency audit |
| 2026-09-14 | **Stock Screener** redesign · Exchange Rate page fixes · portfolio real-price sync + target/stop-loss email alerts · auth/RBAC audit · mandatory test-per-feature workflow adopted |
| 2026-06-27 | Backend admin upgrade: activity timeline, sync status dashboard, company financials DB cache, backend user layer; news scheduler fix |
| 2026-05-31 | **Multi-source News** — 5 RSS feeds (VnExpress ×2, CafeF ×2, Dân Trí ×1) → DB with `news_categories`; `/news` page with category nav, search, pagination; synced every 30 min |
| 2026-05-30 | **Docker migration** (Nginx + PHP-FPM + MySQL + Redis + HTTPS) · **AI switched to Groq** (14,400 req/day free, ~0.4 s) · **Security**: XSS fix, prompt-injection hardening, input sanitisation · **Idempotent sync** (skip already-synced data, `--force` to override) |
| 2026-05-29 | **Technical indicators** (MA/BB/RSI/MACD) + **Company financials** on the stock page · 6-worker parallel historical backfill + MySQL RANGE partitioning · vnstock 4.x API breakage and Laravel 12 scheduler fixed |
| 2026-05-28 | Background auto-sync scheduler; full documentation audit and expansion |
| 2026-05-16 | Improved search, ETF support, backend pipeline fixes |
| 2026-05-02 | RBAC system, separate admin login, role management |
| 2025-08-25 | Email verification, AI market prediction |
| 2025-08-23 | AI Chat feature, portfolio management |

> One row per day. Full detail: [docs/HISTORY.md](docs/HISTORY.md) (latest days) and [docs/history/](docs/history/) (older work, verbatim).

## 👤 Author

**Sun Nguyen**  
Email: [nhat.nguyenminh94@gmail.com](mailto:nhat.nguyenminh94@gmail.com)  
GitHub: [nhatnguyen94/sunstock-ai-vn](https://github.com/nhatnguyen94/sunstock-ai-vn)  
LinkedIn: [Sun Nguyen](https://www.linkedin.com/in/sunnguyen3011/)

MIT License © 2025–2026

---

<a name="tiếng-việt"></a>
# 🇻🇳 Tiếng Việt

> 📸 Ảnh chụp màn hình: xem mục [Screenshots](#-screenshots) ở phần tiếng Anh phía trên; mô tả chi tiết từng trang ở [docs/WEBSITE_TOUR.md](docs/WEBSITE_TOUR.md).

**Sun Stock AI** là nền tảng web tra cứu và quản lý cổ phiếu Việt Nam, xây dựng trên Laravel 12 và Python. Tích hợp AI phân tích thị trường, quản lý danh mục đầu tư kèm cảnh báo giá, tỷ giá ngoại tệ, tin tức, bộ lọc cổ phiếu, và hệ thống phân quyền admin tự quản lý được qua DB.

## 🚀 Tính năng

- **Xem cổ phiếu** — Tìm kiếm mã cổ phiếu với autocomplete, xem biểu đồ giá lịch sử (nến Nhật & đường) và bảng dữ liệu.
- **📊 Chỉ báo kỹ thuật** — Thêm MA20/50/200, Bollinger Bands lên biểu đồ chính; RSI(14) và MACD(12,26,9) hiển thị dưới dạng biểu đồ phụ riêng biệt. Tất cả chỉ báo cập nhật theo bộ lọc thời gian (1T/3T/6T/1N/Tất cả).
- **🏦 Tài chính doanh nghiệp** — Xem báo cáo tài chính theo yêu cầu: Kết quả kinh doanh, Bảng cân đối kế toán, Lưu chuyển tiền tệ, Chỉ số tài chính — theo quý hoặc năm — từ nguồn dữ liệu KBS qua vnstock.
- **🔎 Bộ lọc cổ phiếu (Screener)** — Lọc và xếp hạng toàn thị trường theo các chỉ số tài chính (P/E, ROE, ...) từ dữ liệu đã đồng bộ.
- **🏢 Hồ sơ công ty** — `/company/{symbol}`: tổng quan & định giá, cơ cấu sở hữu và cổ đông lớn (biểu đồ tròn), HĐQT / ban điều hành / BKS kèm tỷ lệ nắm giữ, công ty con & liên kết, sự kiện doanh nghiệp (cổ tức, ĐHCĐ sắp tới, giao dịch nội bộ). Cache DB kiểu stale-while-revalidate; lần đầu ~4 giây, các lần sau tức thì.
- **💼 Quỹ mở** — `/funds`: 68 quỹ Fmarket, lọc theo loại / công ty quản lý / tên, sắp xếp theo mọi kỳ lợi suất; trang chi tiết có biểu đồ NAV (xanh/đỏ so với đầu kỳ), sụt giảm tối đa, biến động, phân bổ tài sản, top khoản đầu tư (link sang hồ sơ công ty); so sánh tối đa 4 quỹ.
- **So sánh cổ phiếu** — So sánh biểu đồ nhiều mã cổ phiếu cùng lúc.
- **Ngành hot** — Khám phá cổ phiếu nổi bật trong các ngành Ngân hàng, Bất động sản, CNTT.
- **Quản lý danh mục** — Tạo danh mục đầu tư, theo dõi lợi nhuận/lỗ theo thời gian thực từ giá đã đồng bộ thật, đặt mức giá mục tiêu và cắt lỗ (tự động gửi email cảnh báo một lần khi chạm mốc), gợi ý cân bằng danh mục bằng AI.
- **Tổng quan thị trường & Danh sách theo dõi** — trang chủ mở đầu bằng thị trường: VN-Index / VN30 / HNX / UPCoM, biểu đồ VN-Index 30 phiên kèm khối lượng, độ rộng thị trường (tăng / giảm / trần / sàn), thanh khoản từng sàn, top tăng / giảm / thanh khoản cao và thanh ticker dữ liệu thật (tự làm mới mỗi phút khi đang giao dịch). Người dùng đăng nhập bấm ★ để theo dõi (trang chủ, trang cổ phiếu, hồ sơ công ty) và quản lý tại `/watchlist` với giá trực tiếp.
- **Tỷ giá ngoại tệ** — Xem tỷ giá Vietcombank theo ngày, cập nhật hàng ngày.
- **Giá vàng** — `/gold`: giá vàng miếng SJC và vàng/bạc Bảo Tín Minh Châu (từ vnstock), giá vàng thế giới quy đổi ra VND/lượng cùng mức chênh lệch trong nước, biến động trong ngày, chuyển đổi lượng/chỉ, máy tính giá trị/lãi lỗ và biểu đồ lịch sử giá (hệ thống ghi lại giá mỗi 15 phút nên biểu đồ dày dần). Nằm chung menu **Thị trường** với Tỷ giá.
- **Tin tức thị trường** — Tổng hợp tin từ 5 nguồn RSS (VnExpress ×2, CafeF ×2, Dân Trí ×1), lưu vào DB với 4 danh mục (Kinh doanh, Chứng khoán, Thị trường, Doanh nghiệp). Trang `/news` riêng với lọc danh mục, tìm kiếm và phân trang. Đồng bộ tự động mỗi 30 phút.
- **AI Chat & Dự đoán** — Hỏi đáp tài chính và dự đoán thị trường qua Groq API (miễn phí, phản hồi ~0.4s).
- **Tài khoản người dùng** — Đăng ký, đăng nhập, xác thực email, quên/đặt lại mật khẩu, quản lý hồ sơ (avatar, ngày sinh, giới tính, địa chỉ, tiểu sử).
- **Phân quyền (RBAC)** — Đăng nhập admin riêng biệt; vai trò và quyền hạn chi tiết được quản lý hoàn toàn từ giao diện backend (**Admin > Vai trò / Quyền hạn**) — thêm role mới, gán quyền chồng chéo không cần sửa code. Xem [docs/RBAC.md](docs/RBAC.md).
- **Trang quản trị** — Quản lý người dùng, vai trò & quyền hạn, cổ phiếu, tin tức (kèm danh mục), danh mục portfolio, trạng thái đồng bộ, nhật ký hoạt động, tự đổi mật khẩu, và dashboard giám sát queue real-time (job nào đang chạy, vừa xong lúc nào, mất bao lâu).

## 🛠️ Công nghệ sử dụng

| Lớp | Công nghệ |
|---|---|
| Backend | Laravel 12 (PHP 8.2+) |
| Lấy dữ liệu | Python 3 + thư viện vnstock 4.x |
| AI | Groq API — miễn phí, 14.400 req/ngày, phản hồi ~0.4s |
| Database | MySQL 8 (phân vùng RANGE theo năm để scale) |
| Giao diện | Blade Templates + Bootstrap 4.5 (trang chính, CDN) + Tailwind CSS 4 (Vite, style riêng từng trang) |
| Trang quản trị | Blade Templates + Tabler / Bootstrap 5 (CDN) |
| Phân quyền | RBAC tự viết (`roles`) + hệ thống permission lưu DB (`permissions`/`permission_role`) — xem [docs/RBAC.md](docs/RBAC.md) |
| Kiến trúc | SOLID — Controller / Service / Repository / Interface, tách namespace Frontend/Backend nghiêm ngặt |
| Queue | Redis Queue — 6 worker (Docker supervisor), dashboard giám sát riêng tại `/admin/queue` |

## 🔐 Bảo mật & biến môi trường

- **Không bao giờ commit `.env`, `.env.docker`, `.env.xampp`** — các file này chứa secret thật và đã được đưa vào `.gitignore`. Chỉ `.env.example` được track trên git, và file này **chỉ chứa placeholder** (`your_groq_api_key`, mật khẩu để trống) — tuyệt đối không thay các placeholder đó bằng giá trị thật rồi commit.
- `docker-compose.yml` bắt buộc phải set `DB_PASSWORD` và `DB_ROOT_PASSWORD`, **không có giá trị mặc định** — nếu thiếu, stack sẽ không khởi động được thay vì âm thầm dùng mật khẩu MySQL rỗng/yếu. Tạo giá trị ngẫu nhiên đủ mạnh cho cả hai, ví dụ `openssl rand -base64 24`.
- `GROQ_API_KEY` (AI chat) và `VNSTOCK_API_KEY` (tuỳ chọn, tier vnstock tài trợ) là API key cá nhân — lấy key miễn phí tại [console.groq.com](https://console.groq.com) và không để lọt vào bất kỳ file nào được commit hoặc chia sẻ.
- Nếu lỡ commit nhầm một secret thật, bắt buộc phải xoay vòng (đổi mật khẩu/tạo lại API key) — xoá nó ở một commit sau **không** xoá được khỏi lịch sử git.
- Port MySQL trong `docker-compose.yml` chỉ bind vào `127.0.0.1` (không public ra LAN/internet); giữ nguyên khi chỉnh sửa file compose.

## ⚡ Cài đặt nhanh (Docker — khuyến nghị)

**Yêu cầu:** Docker Desktop

```bash
git clone https://github.com/nhatnguyen94/sunstock-ai-vn.git
cd stock-app
# Copy .env.docker làm nền (đã có sẵn DB_HOST=mysql, REDIS_HOST=redis đúng cho mạng Docker)
cp .env.docker .env
# Sau đó chỉnh .env: đặt DB_PASSWORD, DB_ROOT_PASSWORD (bắt buộc), GROQ_API_KEY=...
docker compose up -d
docker exec stock-app-php-1 composer install
docker exec stock-app-php-1 php artisan migrate --seed
# Thêm 127.0.0.1 sunstock-local.dev vào hosts file
# Truy cập https://sunstock-local.dev
```

> Xem hướng dẫn chi tiết tại [docs/DOCKER.md](docs/DOCKER.md)

## ⚡ Cài đặt nhanh (thủ công / XAMPP)

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
# 2. Cấu hình .env — dùng giá trị thật của bạn, không commit
DB_DATABASE=stock_app
DB_USERNAME=root
DB_PASSWORD=mat_khau_mysql_cua_ban
GROQ_API_KEY=api_key_groq_cua_ban
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
php artisan sync:stock-data     # Đồng bộ danh sách mã
php artisan sync:stock-prices   # Đồng bộ giá lịch sử
```

```bash
# 6. Khởi động server
php artisan serve
# Truy cập: http://127.0.0.1:8000
```

## 🤖 Cài đặt AI Chat

1. Lấy API key miễn phí tại [console.groq.com](https://console.groq.com), không cần thẻ tín dụng
2. Thêm vào `.env`: `GROQ_API_KEY=gsk_...` — không để lọt vào file nào bị commit
3. Widget chat xuất hiện ở góc phải dưới màn hình
4. Để đổi model AI, đặt `GROQ_MODELS=model-a,model-b` trong `.env` (thử lần lượt; để trống = mặc định trong `AiService::DEFAULT_MODELS`). Groq hay gỡ model cũ — xem danh sách hiện hành bằng `GET https://api.groq.com/openai/v1/models`
5. Xem danh sách model tại [console.groq.com/docs/models](https://console.groq.com/docs/models)

## 🆕 Nhật ký cập nhật

| Ngày | Nội dung |
|---|---|
| 2026-09-20 | **Chín thay đổi trong một ngày** — **Trang giá vàng** (`/gold`) trong menu *Thị trường* mới · **Làm lại Portfolio** (sửa lỗi đơn vị tiền, biểu đồ hiệu suất, tỷ trọng, cổ tức, CSV) · **Tổng quan thị trường, ticker thật, watchlist và sổ giao dịch mua/bán** ở trang chủ · **Trang cổ phiếu lần click đầu ~10 giây → ~1–3 giây** · **Thiết kế lại admin** (Tabler 1.5, palette Ctrl+K, dark mode) · **Backup database hàng tuần** lưu ngoài Docker (`db:backup`) · **Sửa AI chat + AI dự đoán** (model Groq bị gỡ, nút popup chết, câu trả lời dựa trên số liệu thị trường thật) · **vnai không còn sửa `AGENTS.md`** · **Ảnh chụp mới + [tour website](docs/WEBSITE_TOUR.md)** · **gọn lại changelog/history** |
| 2026-09-19 | **Trang hồ sơ công ty** (`/company/{symbol}`) và **danh mục quỹ mở** (`/funds`, chi tiết, so sánh); đổi biểu đồ từ ApexCharts (CDN) sang **TradingView Lightweight Charts** bundle cục bộ (MA/Bollinger, pane RSI/MACD) · **Làm lại ô tìm kiếm mã** (1 dòng highlight, kết quả xếp hạng, sửa 182 tên công ty lỗi font) · nút "Xoá tất cả" job thất bại · sửa sidebar admin |
| 2026-09-17 | **Quản lý danh mục tin tức** và **tự đổi mật khẩu admin** (`/admin/news-categories`, `/admin/account`) |
| 2026-09-16 | **Real-time queue activity** (job đang chạy, đồng hồ, job vừa xong, `queue_job_logs`) · monitor queue tự viết thay Horizon · mọi lệnh Python chạy qua `PythonRunner` (timeout cứng) · tăng tốc sync (6 worker Redis) |
| 2026-09-15 | **Dashboard giám sát Queue** · **phân quyền lưu DB, mở rộng được** (Admin > Vai trò / Quyền hạn) · quên/đặt lại mật khẩu · mở rộng hồ sơ (avatar, ngày sinh, giới tính, địa chỉ, tiểu sử) · audit auth/phân quyền, profile/portfolio, audit tài liệu |
| 2026-09-14 | Redesign **Stock Screener** · sửa trang Tỷ giá · đồng bộ giá thật cho Portfolio + cảnh báo email target/cắt lỗ · audit auth/RBAC · áp dụng quy trình bắt buộc viết test cho từng tính năng |
| 2026-06-27 | Nâng cấp trang quản trị: nhật ký hoạt động, dashboard trạng thái sync, cache DB tài chính doanh nghiệp, lớp user cho backend; sửa scheduler tin tức |
| 2026-05-31 | **Tin tức đa nguồn** — 5 RSS feed (VnExpress ×2, CafeF ×2, Dân Trí ×1) lưu DB với `news_categories`; trang `/news` có danh mục, tìm kiếm, phân trang; sync mỗi 30 phút |
| 2026-05-30 | **Docker migration** (Nginx + PHP-FPM + MySQL + Redis + HTTPS) · **Chuyển AI sang Groq** (14.400 req/ngày miễn phí, ~0.4s) · **Bảo mật**: sửa XSS, chống prompt injection, sanitize input · **Sync idempotent** (bỏ qua dữ liệu đã có, `--force` để buộc) |
| 2026-05-29 | **Chỉ báo kỹ thuật** (MA/BB/RSI/MACD) + **Tài chính doanh nghiệp** trên trang cổ phiếu · backfill lịch sử song song 6 worker + phân vùng MySQL RANGE theo năm · sửa lỗi vnstock 4.x và scheduler Laravel 12 |
| 2026-05-28 | Scheduler tự động đồng bộ nền; rà soát và mở rộng toàn bộ tài liệu |
| 2026-05-16 | Cải tiến tìm kiếm, bổ sung ETF, sửa lỗi pipeline backend |
| 2026-05-02 | Hệ thống RBAC, đăng nhập admin riêng, quản lý vai trò |
| 2025-08-25 | Xác thực email, AI dự đoán thị trường |
| 2025-08-23 | Tính năng AI Chat, quản lý danh mục |

> Mỗi ngày một dòng. Chi tiết: [docs/HISTORY.md](docs/HISTORY.md) (những ngày gần nhất) và [docs/history/](docs/history/) (các mốc cũ, nguyên văn).

## 👤 Tác giả

**Sun Nguyen**  
Email: [nhat.nguyenminh94@gmail.com](mailto:nhat.nguyenminh94@gmail.com)  
GitHub: [nhatnguyen94/sunstock-ai-vn](https://github.com/nhatnguyen94/sunstock-ai-vn)  
LinkedIn: [Sun Nguyen](https://www.linkedin.com/in/sunnguyen3011/)

MIT License © 2025–2026


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
| AI | Groq API — model chain from `GROQ_MODELS` (default `openai/gpt-oss-120b` first; free tier, sub-second to a few seconds) |
| Database | MySQL 8 (RANGE-partitioned by year for scale) |
| Charts | TradingView Lightweight Charts 5 (bundled locally by Vite: candles, area, volume, multi-pane indicators) + in-repo SVG donut/bars |
| Frontend | Blade Templates + Bootstrap 4.5 (main site, CDN) + Tailwind CSS 4 (Vite, page-specific styles) |
| Admin Panel | Blade Templates + Tabler / Bootstrap 5 (CDN) |
| Authorization | Custom RBAC (`roles`) + DB-driven permissions (`permissions`/`permission_role`) — see [docs/RBAC.md](docs/RBAC.md) |
| Architecture | SOLID — Controller / Service / Repository / Interface, strict Frontend/Backend namespace separation |
| Queue | Redis Queue — 6 workers (Docker supervisor), custom monitoring dashboard at `/admin/queue` |

## 🔐 Security & environment variables

- **Never commit `.env`** (or any copy of it) — it holds real secrets and is already gitignored. Only `.env.example` is tracked in git, and it contains **placeholders only** (`your_groq_api_key`, empty passwords) — never replace those placeholders with real values before committing.
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
# 2. Configure .env (copy .env.example, then use the Docker values from docs/DOCKER.md section 2:
#    APP_URL=https://sunstock-local.dev, DB_CONNECTION=mysql, DB_HOST=mysql, REDIS_HOST=redis, SESSION_DRIVER/QUEUE_CONNECTION/CACHE_STORE=redis, PYTHON_PATH=/opt/venv/bin/python3)
cp .env.example .env
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
5. Available models change over time (the three `llama`/`gemma2` ones formerly listed here were retired): list what your key can use with `GET https://api.groq.com/openai/v1/models`

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
| 2026-10-10 | **Bulk demo data** — `DemoBulkDataSeeder` fills the site with a few hundred realistic rows per feature (320 members, ~260 portfolios whose buys and sells use the real stored prices, watchlists, activity, sign-ins with password-guessers, AI calls, sync runs, queue jobs) so every page and chart looks alive; `DemoBulkDataCleanupSeeder` removes exactly that. **World strip** also shows world gold, world silver and the USD/VND market rate (MSN codes taken from vnstock's own maps; oil and Bitcoin left out because no code could be verified). **Gold page fix**: BTMC had silently stopped on 20 Sept (vnstock calls `http://api.btmc.vn`, port 80 times out here) so BTMC rows, the world gold price and the chart were stale for weeks; now requested over https with a 20 s timeout on every vnstock call, and the page shows a warning banner when a source failed on the last sync, BTMC has published nothing for 3 days or no sync has run for 24 h. **Home page motion** (branch `feature/update-fe-homepage`): hero with a pointer-chasing mesh gradient, rolling water, drifting candles and a wavy title; spotlight / glowing-border cards, a sliding ink under every tab, springy chat box (opens and closes), rolling index figures, scroll-driven depth; softer slate palette; everything off for reduced motion, no new dependency (`css/home/home-fx.css`, `js/home/home-fx.js`) |
| 2026-10-09 | **One class for every response** — `TransformerResponse` owns the status codes, the stock messages and the JSON / view / redirect / abort helpers; all scattered `response()->json()`, `abort()`, flash and status numbers in admin and public code now go through it, with a test that fails if a raw one comes back |
| 2026-10-09 | **Admin round 2: health, security, users, content** — a system-health page (scheduler heartbeat, schedule, disk, database backups) and an alert bell in the top bar; a security page with sign-in history, password-guessing detection and IP blocking; richer user detail, bulk actions and CSV export; choose the featured stocks, pin / hide news, a data-quality report; a demo seeder to try it all |
| 2026-10-09 | **Admin: sync history, AI control, site control, charts** — Sync Status records every run of the data jobs and flags a source as "late" against its own schedule (world markets and signals added); a new "Quản lý AI" page logs every AI call, shows which model answered, and can switch the AI off, cap the daily use per account or block one account; "Giao diện & Cache" hides home-page blocks, shows a site-wide notice bar and clears named caches; the dashboard gained charts (sign-ups, activity by hour, most followed / held symbols) |
| 2026-10-08 | **Home page: numbered sections, jump bar and visual effects** — a tabbed version was tried and rejected (content hidden, flat), so the page is one scroll in four numbered sections with a sticky jump bar, the AI prediction as an animated banner, cards that tilt in 3D towards the mouse and rise into view while scrolling (all off for reduced motion) |
| 2026-10-08 | **Home page: market pulse** — a new "Nhịp thị trường" row with foreign buying/selling (estimated from the price board), our own fear/greed gauge, and gold · USD · best funds; a world indices strip (S&P 500, Nasdaq, Nikkei…); and two more tabs: stock signals (52-week breakouts, volume spikes, RSI extremes) and an events calendar (dividends, meetings; the profile sync now seeds the most traded stocks nightly). **Home layout pass 2** — the page is less than half as long (~8.250 → ~3.450 px): compact index strip, the brief folds to its headline, heat map and VN-Index share one card, the market lists became tabs (Tăng / Giảm / GTGD / Theo dõi / Độ rộng), featured stocks + hot industries + exchange rates share one tabbed card, and a signed-in visitor sees "Của tôi" (portfolio value, profit/loss, today, watchlist) first. **Session brief** — under the index cards the home page now writes a few sentences about the session from the real numbers (VN-Index move, breadth, liquidity, strongest/weakest industry, other indices, top movers, how concentrated the money was), with an "ask the AI why" button that sends the question to the chat; no AI call is needed for the text itself. |
| 2026-10-03 | **Market heat map and Ctrl+K palette** — the home page opens with a heat map of the 150 busiest stocks grouped by industry (size = traded value, colour = % change, hover cards, zoom into an industry, exchange filter, live blink on every refresh); Ctrl+K (or the search button) opens a command palette to jump to a stock or page or hand a question to the AI. **Phone bottom navigation** — a bar offers Home, Stocks, Watchlist, Portfolio and Account/Login (current page marked); it slides away while scrolling down and returns on scroll up. |
| 2026-10-02 | **Stock page redesign** — the header showed "62 VNĐ" for a 62.100 ₫ share (the feed is in thousands); prices are now converted once (whole VND for stocks, points for indices) and the chart, table and header agree; new header with change chip, day and 52-week range bars and key-figure tiles, a modern history table (change in ₫ and %, volume bars, no currency column), skeleton loading and a price count-up/flash; +51 tests · **Public endpoint abuse protection** — Python started by a visitor's request is now limited (3 at once, per-visitor budget: guest 4/min, user 10/min, tunable with `PYTHON_WEB_*` in `.env`), made-up company symbols are a 404 in 0.3 s instead of spawning a 60 s process, forced refresh and gold refresh need a login, the exchange-rate date search validates its input and no longer caches empty/failed answers; +40 tests Home page is now "data first": a compact hero with the search box, the market overview right under it, and the product pitch / sign-up banner last and for guests only; the hero's blue fades into the page, with soft glows, a dot grid and subtle motion (cards rise in, charts reveal, numbers count up and flash; off for reduced motion). |
| 2026-10-01 | **Imports rule** — every class is imported with `use` (`@use` in Blade) and used by its short name; 165 inline names in 57 PHP files and 50 in 21 views cleaned up, rule added to `AGENTS.md`, enforced by `pint.json` and the `codeStyle` tests · **Authorization hardening + account status + AI limits** — only the `admin` role may change users/roles/permissions (delegated permissions are read-only), admin can no longer lock itself out (last admin, system role names, core permissions), user status 0/1/2/4 (inactive/active/pending/blocked) that also ends open sessions, full admin audit trail, dashboard data follows permissions, AI chat/prediction need login with per-account limits set in `.env` (`AI_PREDICT_INTERVAL_MINUTES`, `AI_CHAT_WINDOW_MINUTES`, `AI_CHAT_MAX_QUESTIONS`), `DemoStaffSeeder`; +140 tests · **Login/register security audit** — fixed password-reset link poisoning via the Host header, account enumeration on reset/admin login, weak brute-force limits (now per IP, per account and distributed; POSTs only), stored-XSS-capable usernames, weak passwords, sessions surviving a password change, missing security headers, and a verification link that required being logged in; +120 attack-simulation tests · **Failed company-profile jobs fixed** — the VCI data source stopped answering and vnstock's retries (~95s) outlived the 60s Python ceiling, so every profile job failed even though KBS had replied; the script now stops waiting after 40s, returns the sections it has and never wipes stored events |
| 2026-09-30 | **Portfolio audit + insights** — the value line is now replayed from the ledger (sales were ignored), new cards for sector split, comparison with VN-Index / the VN30 ETF using the same cash flows, and risk (volatility, drawdown, beta); ETF names, ETF-aware suggestions, blocked impossible sales and Vietnamese form errors; `DemoUsersSeeder` with five demo accounts (local only) · **ETF page** (`/etf`, `/etf/{symbol}`): the 24 ETFs and listed funds with liquidity, 1M–3Y returns, drawdown, volatility, tracked index and a comparison of funds that follow the same index; built only from what the feed really has (no NAV/iNAV, so no premium/discount) · **`.claude` project settings and docs re-read** — shared permissions (safe commands allowed, secrets and destructive Docker/git commands denied), `CLAUDE.md`, and stale AI-model / worker-count statements corrected in the docs |
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

- **Không bao giờ commit `.env`** (hay bất kỳ bản sao nào của nó) — file này chứa secret thật và đã được đưa vào `.gitignore`. Chỉ `.env.example` được track trên git, và file này **chỉ chứa placeholder** (`your_groq_api_key`, mật khẩu để trống) — tuyệt đối không thay các placeholder đó bằng giá trị thật rồi commit.
- `docker-compose.yml` bắt buộc phải set `DB_PASSWORD` và `DB_ROOT_PASSWORD`, **không có giá trị mặc định** — nếu thiếu, stack sẽ không khởi động được thay vì âm thầm dùng mật khẩu MySQL rỗng/yếu. Tạo giá trị ngẫu nhiên đủ mạnh cho cả hai, ví dụ `openssl rand -base64 24`.
- `GROQ_API_KEY` (AI chat) và `VNSTOCK_API_KEY` (tuỳ chọn, tier vnstock tài trợ) là API key cá nhân — lấy key miễn phí tại [console.groq.com](https://console.groq.com) và không để lọt vào bất kỳ file nào được commit hoặc chia sẻ.
- Nếu lỡ commit nhầm một secret thật, bắt buộc phải xoay vòng (đổi mật khẩu/tạo lại API key) — xoá nó ở một commit sau **không** xoá được khỏi lịch sử git.
- Port MySQL trong `docker-compose.yml` chỉ bind vào `127.0.0.1` (không public ra LAN/internet); giữ nguyên khi chỉnh sửa file compose.

## ⚡ Cài đặt nhanh (Docker — khuyến nghị)

**Yêu cầu:** Docker Desktop

```bash
git clone https://github.com/nhatnguyen94/sunstock-ai-vn.git
cd stock-app
# Copy .env.example rồi đặt giá trị Docker theo docs/DOCKER.md mục 2:
#   APP_URL=https://sunstock-local.dev, DB_CONNECTION=mysql, DB_HOST=mysql, REDIS_HOST=redis, SESSION_DRIVER/QUEUE_CONNECTION/CACHE_STORE=redis, PYTHON_PATH=/opt/venv/bin/python3
cp .env.example .env
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
| 2026-10-10 | **Dữ liệu mẫu lớn** — `DemoBulkDataSeeder` nạp vài trăm bản ghi giống thật cho từng tính năng (320 thành viên, ~260 danh mục mua bán theo giá thật trong DB, theo dõi, hoạt động, đăng nhập có kẻ dò mật khẩu, lượt gọi AI, lịch sử đồng bộ, job hàng đợi) để mọi trang và biểu đồ sinh động; `DemoBulkDataCleanupSeeder` dọn đúng phần đó. **Dải thị trường thế giới** có thêm vàng thế giới, bạc thế giới và tỷ giá USD/VND thị trường (mã MSN lấy từ bảng của chính vnstock; dầu thô và Bitcoin chưa đưa vào vì không kiểm chứng được mã). **Sửa trang giá vàng**: BTMC đã âm thầm ngừng từ 20/9 (vnstock gọi `http://api.btmc.vn`, cổng 80 bị treo từ mạng này) nên giá BTMC, giá vàng thế giới và biểu đồ đứng yên mấy tuần; giờ gọi qua https, mọi lời gọi vnstock có timeout 20 giây, và trang hiện banner cảnh báo khi lần đồng bộ gần nhất lỗi một nguồn, BTMC 3 ngày không có giá mới hoặc 24 giờ chưa đồng bộ. **Chuyển động trang chủ** (nhánh `feature/update-fe-homepage`): hero có mesh gradient đuổi theo chuột, sóng nước, nến trôi và tiêu đề uốn sóng; card có đèn rọi và viền sáng theo chuột, thanh trượt dưới mọi tab, khung chat nảy lò xo (mở và đóng), số chỉ số lăn khi đổi, chiều sâu theo cuộn trang; bảng màu slate dịu hơn; tắt hết khi bật giảm chuyển động, không thêm thư viện (`css/home/home-fx.css`, `js/home/home-fx.js`) |
| 2026-10-09 | **Một class cho mọi response** — `TransformerResponse` giữ status code, message chung và các hàm JSON / view / redirect / abort; mọi `response()->json()`, `abort()`, flash và số status rải rác ở admin lẫn frontend giờ đều đi qua nó, có test canh để không ai viết số trần quay lại |
| 2026-10-09 | **Admin đợt 2: sức khỏe, bảo mật, người dùng, nội dung** — trang sức khỏe hệ thống (nhịp tim scheduler, lịch chạy, ổ đĩa, sao lưu DB) và quả chuông cảnh báo; trang bảo mật có lịch sử đăng nhập, phát hiện dò mật khẩu, chặn IP; chi tiết user, thao tác hàng loạt, xuất CSV; chọn cổ phiếu nổi bật, ghim/ẩn tin, báo cáo chất lượng dữ liệu; seeder demo để kiểm tra tất cả |
| 2026-10-09 | **Admin: lịch sử đồng bộ, quản lý AI, điều khiển trang, biểu đồ** — Sync Status ghi lại mọi lần chạy job dữ liệu và báo "Trễ" theo lịch của từng nguồn (thêm thế giới và tín hiệu); trang "Quản lý AI" mới ghi mọi lượt gọi AI, cho biết model nào trả lời, có công tắc tắt AI, hạn mức mỗi ngày và khóa từng tài khoản; "Giao diện & Cache" ẩn khối trang chủ, hiện thanh thông báo toàn trang và xóa cache theo nhóm; dashboard có biểu đồ (đăng ký, hoạt động theo giờ, mã được theo dõi / nắm giữ nhiều nhất) |
| 2026-10-08 | **Trang chủ: section đánh số, thanh điều hướng dính và hiệu ứng** — bản chia tab bị loại (giấu nội dung, nhìn phẳng), nay là một trang cuộn gồm 4 section đánh số với thanh nhảy dính phía trên, AI dự đoán thành banner động, các card nghiêng 3D theo chuột và hiện dần khi cuộn (tắt khi người dùng chọn giảm chuyển động) |
| 2026-10-08 | **Trang chủ: nhịp thị trường** — hàng "Nhịp thị trường" mới gồm khối ngoại mua/bán (ước tính từ bảng giá), đồng hồ tham lam/sợ hãi tự tính, vàng · USD · quỹ tốt nhất; dải chỉ số thế giới (S&P 500, Nasdaq, Nikkei…); thêm hai tab: tín hiệu cổ phiếu (vượt đỉnh 52 tuần, khối lượng đột biến, RSI) và lịch sự kiện (cổ tức, đại hội; hồ sơ công ty nạp dần mỗi đêm cho mã giao dịch mạnh). **Bố cục trang chủ lần 2** — trang ngắn hơn một nửa (~8.250 → ~3.450 px): dải chỉ số gọn, bản tin chỉ hiện câu mở đầu (bấm "Chi tiết" để xem thêm), bản đồ nhiệt và biểu đồ VN-Index chung một card, các danh sách thị trường thành tab (Tăng / Giảm / GTGD / Theo dõi / Độ rộng), cổ phiếu nổi bật + ngành hot + tỷ giá gom vào một card có tab, và người đã đăng nhập thấy "Của tôi" (giá trị danh mục, lãi/lỗ, hôm nay, danh sách theo dõi) ngay đầu. **Bản tin phiên** — dưới các thẻ chỉ số, trang chủ viết vài câu tóm tắt phiên từ số liệu thật (VN-Index tăng/giảm bao nhiêu, độ rộng, thanh khoản, ngành mạnh/yếu nhất, các chỉ số khác, mã nổi bật, độ tập trung dòng tiền), kèm nút "Hỏi AI vì sao" gửi câu hỏi sang chat; phần chữ không cần gọi AI. |
| 2026-10-03 | **Bản đồ nhiệt thị trường và thanh lệnh Ctrl+K** — trang chủ mở đầu bằng bản đồ nhiệt 150 mã giao dịch mạnh nhất theo ngành (kích thước = giá trị giao dịch, màu = % thay đổi, thẻ khi rê chuột, phóng to một ngành, lọc theo sàn, nháy khi dữ liệu đổi); Ctrl+K (hoặc nút tìm kiếm) mở thanh lệnh để nhảy tới mã/trang hoặc chuyển câu hỏi cho AI. **Thanh điều hướng dưới cho điện thoại** — gồm Trang chủ, Cổ phiếu, Theo dõi, Danh mục và Tài khoản/Đăng nhập (đánh dấu trang đang xem); trượt đi khi cuộn xuống, hiện lại khi cuộn lên. |
| 2026-10-02 | **Làm mới trang cổ phiếu** — header từng ghi "62 VNĐ" cho cổ phiếu giá 62.100 ₫ (nguồn tính theo nghìn đồng); giá nay được quy đổi một lần (đồng cho cổ phiếu, điểm cho chỉ số) nên header, biểu đồ và bảng khớp nhau; header mới có thẻ thay đổi, thanh biên độ phiên/52 tuần và các ô số liệu chính, bảng lịch sử hiện đại (thay đổi theo ₫ và %, thanh khối lượng, bỏ cột tiền tệ), skeleton loading và hiệu ứng giá nhảy; +51 test · **Chống lạm dụng endpoint công khai** — Python do request của khách khởi chạy nay bị giới hạn (tối đa 3 tiến trình cùng lúc, ngân sách theo người: khách 4/phút, user 10/phút, chỉnh bằng `PYTHON_WEB_*` trong `.env`), mã công ty bịa trả 404 sau 0,3 s thay vì chạy tiến trình 60 s, làm mới ép buộc và làm mới giá vàng bắt đăng nhập, tìm tỷ giá theo ngày kiểm tra đầu vào và không còn cache kết quả rỗng/lỗi; +40 test Trang chủ nay "dữ liệu lên trước": hero gọn kèm ô tìm kiếm, tổng quan thị trường ngay bên dưới, phần giới thiệu và banner đăng ký xuống cuối và chỉ hiện cho khách; màu xanh của hero mờ dần xuống nền trang, có quầng sáng, lưới chấm và chuyển động nhẹ (thẻ trượt vào, biểu đồ hiện dần, số đếm lên và nháy; tắt khi bật giảm chuyển động). |
| 2026-10-01 | **Quy tắc import class** — mọi class phải `use` ở đầu file (`@use` trong Blade) rồi dùng tên ngắn; đã dọn 165 chỗ viết tên đầy đủ trong 57 file PHP và 50 chỗ trong 21 view, đưa quy tắc vào `AGENTS.md`, bắt buộc bằng `pint.json` và test `codeStyle` · **Siết authorization + trạng thái tài khoản + giới hạn AI** — chỉ role `admin` mới được sửa user/role/quyền (quyền ủy quyền chỉ để xem), admin không còn tự khoá mình được (admin cuối cùng, tên role hệ thống, quyền lõi), trạng thái user 0/1/2/4 (ngưng/hoạt động/chờ xác thực/bị chặn) và đăng xuất ngay session đang mở, audit trail đầy đủ cho thao tác admin, dashboard theo đúng quyền, AI chat/dự đoán bắt buộc đăng nhập với giới hạn theo tài khoản chỉnh trong `.env` (`AI_PREDICT_INTERVAL_MINUTES`, `AI_CHAT_WINDOW_MINUTES`, `AI_CHAT_MAX_QUESTIONS`), `DemoStaffSeeder`; +140 test · **Rà soát bảo mật login/register** — vá lỗi đầu độc link reset mật khẩu qua Host header, lộ tài khoản tồn tại ở trang reset/admin login, giới hạn brute-force yếu (nay theo IP, theo tài khoản và phân tán; chỉ áp cho POST), username chứa được mã XSS, mật khẩu yếu, session sống sau khi đổi mật khẩu, thiếu security header, link xác thực email bắt buộc phải đăng nhập; +120 test mô phỏng tấn công · **Sửa job hồ sơ công ty bị failed** — nguồn VCI ngừng phản hồi, vnstock retry (~95s) vượt trần 60s của Python nên job nào cũng fail dù KBS đã trả dữ liệu; script giờ chỉ chờ tối đa 40s, trả về các phần đã có và không xoá sự kiện đã lưu |
| 2026-09-30 | **Rà soát + nâng cấp Portfolio** — đường giá trị giờ dựng từ sổ giao dịch (trước đây bỏ qua lệnh bán), thêm thẻ chia theo ngành, so với VN-Index / ETF VN30 cùng dòng tiền, và rủi ro (biến động, sụt giảm, beta); tên ETF, gợi ý biết về ETF, khoá nút bán quá số lượng, lỗi form tiếng Việt; `DemoUsersSeeder` tạo 5 tài khoản demo (chỉ local) · **Trang ETF** (`/etf`, `/etf/{symbol}`): 24 quỹ ETF/quỹ đóng với thanh khoản, lợi suất 1T–3N, sụt giảm, biến động, chỉ số theo dõi và bảng so sánh các quỹ cùng bám một chỉ số; chỉ dùng dữ liệu nguồn thực sự có (không có NAV/iNAV nên không có chiết/phụ giá) · **Cấu hình `.claude` cho project và rà lại docs** — quyền dùng chung (cho phép lệnh an toàn, chặn đọc secret và các lệnh Docker/git nguy hiểm), `CLAUDE.md`, sửa các chỗ docs ghi sai model AI và số worker |
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

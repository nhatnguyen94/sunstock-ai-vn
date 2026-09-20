# Quick Start Guide

## Requirements
- PHP 8.2+
- MySQL / MariaDB
- Composer
- Node.js + npm
- Python 3.10+ with `vnstock` library installed

## 1. Environment Setup
```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` — use your own real values, **never commit them** (see the Security section in [README.md](../README.md)):
```env
DB_DATABASE=stock_app
DB_USERNAME=root
DB_PASSWORD=your_own_mysql_password

MAIL_MAILER=smtp        # Required for email verification
MAIL_FROM_ADDRESS=noreply@yourdomain.com

QUEUE_CONNECTION=database  # For async stock sync jobs

GROQ_API_KEY=your_own_groq_api_key   # Required for the AI chat feature — free key at console.groq.com
```

## 2. Install Dependencies
```bash
composer install
npm install
npm run build
```

## 3. Database Setup
```bash
php artisan migrate --seed   # Runs RoleSeeder, PermissionSeeder, AdminUserSeeder in order — see database/seeders/DatabaseSeeder.php
```

Or run the seeders individually (order matters — `PermissionSeeder` needs the roles from `RoleSeeder` to already exist, and without it the admin account has no permissions and every `can:`-gated page denies access):
```bash
php artisan db:seed --class=RoleSeeder         # Creates roles: admin, webadmin, adminsupport, user
php artisan db:seed --class=PermissionSeeder   # Creates the 6 default permissions and attaches them to roles — see docs/RBAC.md
php artisan db:seed --class=AdminUserSeeder    # Creates default admin account
```

> Alternatively import `stock_app.sql` if you have a full dump.

## 4. Python Setup
```bash
pip install vnstock
```

Test a Python script manually:
```bash
python py/get_stock.py VCB
python py/get_exchange_rate.py 2026-05-28
```

## 5. Stock Data Sync
```bash
# Sync stock symbols and company info (fast, ~few seconds)
php artisan sync:stock-data

# Sync historical prices (slow, can take minutes — dispatches queue jobs; run `php artisan queue:work` too)
php artisan sync:stock-prices

# Register vnstock API key (if using sponsored tier)
php artisan vnstock:register-key
```

## 6. Start the Application
```bash
# Start web server via XAMPP, or:
php artisan serve

# Process queued jobs (for async stock price sync)
php artisan queue:work
```

## Common Artisan Commands
| Command | Description |
|---|---|
| `php artisan route:list` | List all registered routes |
| `php artisan sync:stock-data` | Sync stock symbols from Python/vnstock |
| `php artisan sync:stock-prices` | Dispatch jobs to sync historical price data (`--symbols=AAA,BBB` for specific symbols) |
| `php artisan sync:exchange-rates` | Fetch VCB exchange rates via Python |
| `php artisan sync:gold-prices` | Fetch SJC + BTMC gold/silver and the world gold price, store new quotes (scheduled every 15 min, 07:00–19:00 VN) |
| `php artisan sync:market-overview` | Fetch indices, breadth, liquidity, top movers and a quote per symbol (KBS, ~4 s); scheduled every 5 min Mon–Fri 09:00–15:10 VN + 18:00 |
| `php artisan sync:hot-industries` | Sync hot industry stock list |
| `php artisan sync:news` | Crawl the 5 RSS sources and persist new articles |
| `php artisan sync:company-financials` | Sync company financials to the DB cache (runs monthly via scheduler) |
| `php artisan sync:portfolio-prices` | Refresh every active portfolio's current price and send target/stop-loss alerts |
| `php artisan vnstock:register-key` | Register vnstock API key (sponsored tier) |
| `php artisan schedule:list` | Show the active scheduled commands (defined in `bootstrap/app.php`, not `Kernel.php` — see [docs/GUIDELINES.md](GUIDELINES.md)) |
| `php artisan migrate:fresh --seed` | Reset DB and seed (roles, permissions, admin user) |
| `php artisan test --group=<name>` | Run one feature's test group — see [docs/TESTING.md](TESTING.md) |
| `php artisan cache:clear` | Clear application cache |
| `php artisan config:clear` | Clear config cache |
| `composer dump-autoload` | Rebuild class autoloader |

## Default Admin Credentials
> Check `database/seeders/AdminUserSeeder.php` for credentials.

Admin login URL: `/admin/login`

## Key URLs
| URL | Description |
|---|---|
| `/` | Homepage with featured stocks |
| `/stock` | Stock chart viewer |
| `/stock/compare` | Compare multiple stocks |
| `/stock/screener` | Stock screener (filter/rank by financial ratios) |
| `/company/{symbol}` | Company profile: shareholders, officers, subsidiaries, events |
| `/funds` | Open-ended fund catalog (`/funds/{code}`, `/funds/compare?codes=A,B`) |
| `/exchange-rate` | Exchange rate viewer |
| `/news` | Market news |
| `/portfolio` | User portfolio (auth + verified) |
| `/profile` | User profile (auth + verified) |
| `/admin` | Admin dashboard |
| `/admin/login` | Admin login (separate from user login) |
| `/admin/users` | Manage users (gate: `manage-users`) |
| `/admin/roles` | Manage roles + assign permissions (gate: `manage-roles`) |
| `/admin/permissions` | Manage permissions (gate: `manage-permissions`) — see [docs/RBAC.md](RBAC.md) |
| `/admin/queue` | Queue monitoring dashboard (gate: `manage-queue`) — see "Giám sát Queue" in [docs/DOCKER.md](DOCKER.md) |

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
AI_PREDICT_INTERVAL_MINUTES=15       # one market prediction per account every N minutes
AI_CHAT_WINDOW_MINUTES=5             # AI chat: AI_CHAT_MAX_QUESTIONS questions per N minutes per account
AI_CHAT_MAX_QUESTIONS=5
PYTHON_WEB_MAX_CONCURRENT=3          # Python processes a visitor's request may have running at once (all visitors together)
PYTHON_WEB_GUEST_PER_MINUTE=4        # per guest IP; PYTHON_WEB_GUEST_PER_HOUR=20
PYTHON_WEB_USER_PER_MINUTE=10        # per signed-in user; PYTHON_WEB_USER_PER_HOUR=60
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
| `php artisan db:backup` | Weekly database backup to `../database_backup/<year>/<month>/<day>/stock_app_db.zip` (skipped if one from the last 7 days exists; `--force`, `--list`) — runs automatically when Docker starts |
| `php artisan sync:etfs` | Refresh the ETF / listed-fund roster from KBS (weekly, Sunday 03:30 VN); prices come from `sync:stock-prices` |
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

## Demo accounts (local only)
```bash
php artisan db:seed --class=DemoStaffSeeder   # webadmin@ / support@ (can open /admin, are NOT admins), pending@ / inactive@ / blocked@sunstock.test
```
Same password as below. Statuses you change while testing are not reset by re-running it.

```bash
php artisan db:seed --class=DemoUsersSeeder   # demo1..demo5@sunstock.test, each with a different sample portfolio
```
The shared password is `DemoUsersSeeder::PASSWORD`. It is public on purpose, so the seeder refuses to run outside the local/testing environments. Re-running it changes nothing.

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
| `/etf` | ETFs and listed funds: liquidity, returns, tracked index (`/etf/{symbol}` detail) |
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

## Trying the admin features by hand

```bash
php artisan db:seed --class=DemoAdminFeaturesSeeder          # local only; admin@sunstock.test / support@sunstock.test / webadmin@sunstock.test, shared demo password
php artisan db:seed --class=DemoAdminFeaturesCleanupSeeder   # removes exactly what the seeder created

# a few hundred realistic rows per feature (320 members, ~260 portfolios from real prices, watchlists, activity, sign-ins, AI calls, sync runs…)
php artisan db:seed --class=DemoBulkDataSeeder               # ~4 minutes; local only; prints five accounts to log in with (shared demo password)
php artisan db:seed --class=DemoBulkDataCleanupSeeder        # removes exactly what it created
```

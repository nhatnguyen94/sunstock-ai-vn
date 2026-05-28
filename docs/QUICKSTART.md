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

Edit `.env`:
```env
DB_DATABASE=stock_app
DB_USERNAME=root
DB_PASSWORD=

MAIL_MAILER=smtp        # Required for email verification
MAIL_FROM_ADDRESS=noreply@yourdomain.com

QUEUE_CONNECTION=database  # For async stock sync jobs
```

## 2. Install Dependencies
```bash
composer install
npm install
npm run build
```

## 3. Database Setup
```bash
php artisan migrate
php artisan db:seed --class=RoleSeeder         # Creates roles: admin, webadmin, adminsupport, user
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
php artisan stock:sync

# Sync historical prices (slow, can take minutes — runs as queue job)
php artisan stock:sync-prices

# Register vnstock API key (if using sponsored tier)
php artisan vnstock:register-api-key
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
| `php artisan stock:sync` | Sync stock symbols from Python/vnstock |
| `php artisan stock:sync-prices` | Sync historical price data |
| `php artisan vnstock:register-api-key` | Register vnstock API key |
| `php artisan migrate:fresh --seed` | Reset DB and seed |
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
| `/exchange-rate` | Exchange rate viewer |
| `/portfolio` | User portfolio (auth + verified) |
| `/profile` | User profile (auth + verified) |
| `/admin` | Admin dashboard |
| `/admin/login` | Admin login (separate from user login) |

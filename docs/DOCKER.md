# Docker Setup Guide — Stock App

> **Tóm tắt**: Project đã được migrate từ XAMPP + `php artisan serve` sang Docker với Nginx + PHP-FPM + MySQL + Redis + Python, hỗ trợ HTTPS tại `https://sunstock-local.dev`.

---

## 1. Tại sao chuyển sang Docker?

| XAMPP (cũ) | Docker (mới) |
|---|---|
| Chạy Apache/PHP trực tiếp trên Windows | Mỗi service chạy trong container riêng |
| Config khó reproduce | `docker-compose.yml` = mô tả toàn bộ môi trường |
| Deploy lên server phải setup lại từ đầu | Build image 1 lần, chạy được ở mọi nơi |
| `start-workers.bat` chạy thủ công | Queue workers + scheduler tự start trong container |
| HTTP only | HTTPS với cert được browser tin tưởng |

---

## 2. Những gì đã được tạo ra

### Cấu trúc thư mục Docker

```
stock-app/
├── docker/
│   ├── nginx/
│   │   ├── default.conf          ← Config Nginx: HTTPS, redirect HTTP→HTTPS, proxy PHP-FPM
│   │   └── ssl/
│   │       ├── sunstock-local.dev.pem      ← SSL certificate (mkcert, trusted bởi browser)
│   │       └── sunstock-local.dev-key.pem  ← Private key
│   └── php/
│       ├── Dockerfile            ← Build PHP 8.2-FPM + Python 3.11 + tất cả extensions
│       ├── supervisord.conf      ← Quản lý 6 queue workers song song
│       └── php.ini               ← Custom PHP settings (memory, upload, opcache)
├── docker-compose.yml            ← Định nghĩa 6 containers
├── .dockerignore                 ← Loại trừ file không cần thiết khi build
├── .env.docker                   ← .env đã được config cho Docker (DB_HOST=mysql, Redis, Python path)
└── .env.xampp                    ← Backup .env cũ của XAMPP (để khôi phục nếu cần)
```

### 6 Containers

| Container | Image | Vai trò | Port |
|---|---|---|---|
| `nginx` | nginx:1.27-alpine | Reverse proxy, HTTPS, serve static files | 80, 443 |
| `php` | custom (Dockerfile) | PHP-FPM 8.2 + Python 3.11 + vnstock | internal:9000 |
| `mysql` | mysql:8.0 | Database | 3307 (host) → 3306 (internal) |
| `redis` | redis:7-alpine | Queue + Cache + Session | internal:6379 |
| `queue` | custom (Dockerfile) | 6 queue workers via Supervisor | — |
| `scheduler` | custom (Dockerfile) | `php artisan schedule:work` | — |

> **Tại sao MySQL dùng port 3307?** Để tránh conflict với XAMPP MySQL đang chạy trên port 3306. Sau khi chuyển hẳn sang Docker thì có thể đổi lại 3306.

### .env thay đổi gì?

| Setting | XAMPP (.env.xampp) | Docker (.env.docker → .env) |
|---|---|---|
| `APP_URL` | `http://localhost` | `https://sunstock-local.dev` |
| `DB_HOST` | `127.0.0.1` | `mysql` (tên container) |
| `SESSION_DRIVER` | `file` | `redis` |
| `QUEUE_CONNECTION` | `database` | `redis` |
| `CACHE_STORE` | `database` | `redis` |
| `REDIS_HOST` | `127.0.0.1` | `redis` (tên container) |
| `PYTHON_PATH` | `C:/Users/<username>/AppData/.../python.exe` | `/opt/venv/bin/python3` |

---

## 3. Lần đầu tiên setup (First-time Setup)

### Bước 1: Build images (chỉ cần làm 1 lần)

```powershell
cd C:\xampp\htdocs\stock-app
docker compose build
```

> ⏱ **Thời gian**: 8–15 phút lần đầu. Lần sau chỉ vài giây (Docker cache).
> Quá trình này: download PHP/Nginx/MySQL images, compile PHP extensions (intl, redis), install Python + vnstock.

### Bước 2: Start tất cả containers

```powershell
docker compose up -d
```

### Bước 3: Install PHP dependencies

```powershell
docker compose exec php composer install
```

### Bước 4: Import database từ XAMPP

```powershell
# File dump đã được export sẵn tại docker/init.sql (459MB)
docker compose exec -T mysql mysql -u root -p"$(grep DB_PASSWORD .env | cut -d= -f2)" stock_app < docker/init.sql
# Hoặc nhập password thủ công:
docker compose exec -T mysql mysql -u root -p stock_app < docker/init.sql
```

> ⏱ Import 459MB mất khoảng 3–10 phút.

### Bước 5: Chạy migrations (kiểm tra)

```powershell
docker compose exec php php artisan migrate --force
```

### Bước 6: Fix permissions

```powershell
docker compose exec php chown -R www-data:www-data storage bootstrap/cache
docker compose exec php chmod -R 775 storage bootstrap/cache
```

### Bước 7: Clear cache

```powershell
docker compose exec php php artisan config:clear
docker compose exec php php artisan cache:clear
docker compose exec php php artisan view:clear
```

### Bước 8: Mở browser

```
https://sunstock-local.dev
```

---

## 4. Sử dụng hàng ngày

### Start / Stop

```powershell
# Start (sau khi tắt máy)
cd C:\xampp\htdocs\stock-app
docker compose up -d

# Stop (khi không dùng)
docker compose down

# Restart 1 container cụ thể
docker compose restart php
docker compose restart queue
```

> ⚠️ **Không dùng `docker compose down -v`** — `-v` sẽ xóa volumes, mất toàn bộ database!

### Xem logs

```powershell
# Tất cả containers
docker compose logs -f

# Riêng từng container
docker compose logs -f nginx
docker compose logs -f php
docker compose logs -f queue
docker compose logs -f scheduler

# Laravel app logs
docker compose exec php tail -f storage/logs/laravel.log
```

### Chạy Artisan commands

```powershell
# Thay vì: php artisan [command]
docker compose exec php php artisan [command]

# Ví dụ:
docker compose exec php php artisan migrate
docker compose exec php php artisan sync:stock-prices
docker compose exec php php artisan backfill:stock-prices --dispatch
docker compose exec php php artisan queue:monitor
docker compose exec php php artisan tinker
```

### Vào shell container

```powershell
# Vào PHP container (để debug, chạy lệnh)
docker compose exec php bash

# Vào MySQL container (nhập password khi được hỏi)
docker compose exec mysql mysql -u root -p stock_app
```

---

## 5. Khi code thay đổi

### Thay đổi PHP/Blade (tức thì)

Code được **mount trực tiếp** vào container qua volume (`- .:/var/www/html`).  
→ Sửa file PHP/Blade xong là có tác dụng ngay, **không cần rebuild**.

### Thay đổi JS/CSS (cần build Vite)

```powershell
# Trên máy Windows (không cần vào container)
npm run build
```

### Thêm PHP package mới

```powershell
# Trên máy Windows
composer require package/name

# Sau đó sync vào container
docker compose exec php composer install
```

### Thêm migration mới

```powershell
docker compose exec php php artisan make:migration create_table_name
# Sửa file migration
docker compose exec php php artisan migrate
```

### Restart queue workers (sau khi sửa Job files)

```powershell
docker compose restart queue
```

---

## 6. Khi cần rebuild image

Rebuild chỉ cần khi thay đổi `Dockerfile` (thêm PHP extension, thêm Python package, v.v.):

```powershell
# Rebuild PHP image
docker compose build php

# Rebuild và restart
docker compose up -d --build php queue scheduler
```

---

## 7. Khôi phục về XAMPP (nếu cần)

```powershell
cd C:\xampp\htdocs\stock-app

# Restore .env cũ
Copy-Item .env.xampp .env

# Stop Docker
docker compose down

# Start lại XAMPP Apache + MySQL như cũ
# php artisan serve hoặc dùng XAMPP Control Panel
```

---

## 8. Kết nối database từ tool ngoài (TablePlus, DBeaver...)

MySQL trong Docker expose ra port **3307** trên localhost:

```
Host: 127.0.0.1
Port: 3307
User: root
Password: <giá trị DB_PASSWORD trong .env>
Database: stock_app
```

---

## 9. Khi deploy lên server thật

1. Copy toàn bộ project (không cần `vendor/`, `node_modules/`, `docker/init.sql`)
2. Tạo `.env` cho production (copy từ `.env.docker`, đổi `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://yourdomain.com`)
3. Thay SSL cert: dùng Let's Encrypt thay vì mkcert
4. Trên server:
   ```bash
   docker compose build
   docker compose up -d
   docker compose exec php composer install --no-dev --optimize-autoloader
   docker compose exec php php artisan migrate --force
   docker compose exec php php artisan config:cache
   docker compose exec php php artisan route:cache
   docker compose exec php php artisan view:cache
   ```
5. MySQL port: đổi lại `3306:3306` (không cần tránh conflict XAMPP)

---

## 10. Troubleshooting

### "No configuration file provided: not found"
```
# Nguyên nhân: chạy docker compose không đúng thư mục
# Fix: luôn cd vào project trước
cd C:\xampp\htdocs\stock-app
docker compose [command]
```

### Port 80/443 bị chiếm
```powershell
# Tắt XAMPP Apache trước
# Hoặc kiểm tra process
netstat -ano | findstr ":80 "
netstat -ano | findstr ":443 "
```

### Container không start / crash loop
```powershell
# Xem lý do crash
docker compose logs php
docker compose logs nginx
```

### Permission denied trên storage/
```powershell
docker compose exec php chown -R www-data:www-data storage bootstrap/cache
docker compose exec php chmod -R 775 storage bootstrap/cache
```

### Queue không xử lý job
```powershell
# Kiểm tra queue workers có chạy không
docker compose ps queue
docker compose logs queue

# Restart workers
docker compose restart queue
```

### Python không chạy được trong container
```powershell
# Kiểm tra python path trong container
docker compose exec php /opt/venv/bin/python3 --version
docker compose exec php /opt/venv/bin/python3 py/get_stock.py VCB
```

---

## 11. Files quan trọng và vai trò

| File | Vai trò |
|---|---|
| `docker-compose.yml` | Định nghĩa toàn bộ stack (6 containers, volumes, networks) |
| `docker/php/Dockerfile` | Build PHP image: PHP 8.2 + Python + extensions + vnstock |
| `docker/nginx/default.conf` | Nginx config: HTTPS, HTTP redirect, PHP-FPM proxy |
| `docker/php/supervisord.conf` | Quản lý 6 queue workers song song |
| `docker/php/php.ini` | Custom PHP settings |
| `docker/nginx/ssl/*.pem` | SSL cert (mkcert, trusted, expires 2028-08-30) |
| `.env.docker` | .env cho Docker (backup, không dùng trực tiếp) |
| `.env.xampp` | .env cũ của XAMPP (để rollback) |
| `docker/init.sql` | Database dump 459MB (gitignored) |

---

*Last updated: May 30, 2026*

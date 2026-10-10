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
└── .env.example                  ← Mẫu cấu hình (copy thành .env, đặt giá trị Docker theo bảng bên dưới); .env thật không commit
```

### 6 Containers

| Container | Image | Vai trò | Port |
|---|---|---|---|
| `nginx` | nginx:1.27-alpine | Reverse proxy, HTTPS, serve static files | 80, 443 |
| `php` | custom (Dockerfile) | PHP-FPM 8.2 + Python 3.11 + vnstock | internal:9000 |
| `mysql` | mysql:8.0 | Database | 3307 (host) → 3306 (internal) |
| `redis` | redis:7-alpine | Queue + Cache + Session | internal:6379 |
| `queue` | custom (Dockerfile) | 6 Redis queue workers (`queue:work redis --queue=high,default`) via Supervisor | — |
| `scheduler` | custom (Dockerfile) | `php artisan schedule:work` | — |

> **Tại sao MySQL dùng port 3307?** Để tránh conflict với XAMPP MySQL đang chạy trên port 3306. Sau khi chuyển hẳn sang Docker thì có thể đổi lại 3306.

### .env thay đổi gì?

`.env.example` mang giá trị mặc định kiểu chạy thẳng trên máy (XAMPP / `php artisan serve`). Khi chạy bằng Docker, đặt các giá trị sau trong `.env`:

| Setting | Mặc định trong `.env.example` (không Docker) | Docker (đặt trong `.env`) |
|---|---|---|
| `APP_URL` | `http://localhost` | `https://sunstock-local.dev` |
| `DB_CONNECTION` | `sqlite` | `mysql` |
| `DB_HOST` / `DB_PORT` | (đang comment) `127.0.0.1` / `3306` | `mysql` (tên container) / `3306` |
| `DB_DATABASE` / `DB_USERNAME` | (đang comment) | `stock_app` / `root` |
| `SESSION_DRIVER` | `database` | `redis` |
| `QUEUE_CONNECTION` | `database` | `redis` |
| `CACHE_STORE` | `database` | `redis` |
| `REDIS_HOST` | `127.0.0.1` | `redis` (tên container) |
| `PYTHON_PATH` | `""` (rỗng) | `/opt/venv/bin/python3` |

> Các file `.env.docker`, `.env.docker.bak`, `.env.xampp` (bản sao cũ từ lúc chuyển sang Docker, 30/5/2026) đã bị xóa ngày 10/10/2026: compose không đọc chúng, chúng còn chứa key cũ, và không file nào trong code tham chiếu tới chúng. `.env.example` là mẫu duy nhất.

---

## 3. Lần đầu tiên setup (First-time Setup)

### Bước 0: Cấu hình `.env`

```powershell
cp .env.example .env
```

Sau đó chỉnh `.env`: đặt các giá trị Docker ở bảng "`.env` thay đổi gì?" phía trên (`APP_URL`, `DB_*`, `SESSION_DRIVER`, `QUEUE_CONNECTION`, `CACHE_STORE`, `REDIS_HOST`, `PYTHON_PATH`) và set giá trị thật (không commit) cho:
- `DB_PASSWORD`, `DB_ROOT_PASSWORD` — **bắt buộc**, `docker-compose.yml` không có giá trị mặc định, thiếu là container `mysql` không start được
- `GROQ_API_KEY` — cho tính năng AI chat (free tại [console.groq.com](https://console.groq.com))

> Xem mục "🔐 Security & environment variables" trong [README.md](../README.md) để biết đầy đủ quy tắc về secret.

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

### Bước 4: Khởi tạo database

**Clone mới (không có sẵn `docker/init.sql`)** — dùng cách này:

```powershell
docker compose exec php php artisan migrate --seed
docker compose exec php php artisan sync:stock-data
```

**Máy đã từng chạy XAMPP và có sẵn file dump `docker/init.sql`** (file này bị gitignore — chỉ tồn tại cục bộ trên máy đã export nó trước đó, KHÔNG có sẵn sau khi `git clone`) — có thể import thẳng thay vì migrate/seed:

```powershell
docker compose exec -T mysql mysql -u root -p"$(grep DB_PASSWORD .env | cut -d= -f2)" stock_app < docker/init.sql
# Hoặc nhập password thủ công:
docker compose exec -T mysql mysql -u root -p stock_app < docker/init.sql
```

> ⏱ Import full dump (nhiều trăm MB) mất khoảng 3–10 phút.

### Bước 5: Chạy migrations mới nhất (nếu import từ `docker/init.sql`)

Chỉ cần nếu bạn đi theo nhánh "có sẵn `docker/init.sql`" ở Bước 4 — dump có thể cũ hơn migration mới nhất trong code. Nếu bạn vừa chạy `migrate --seed` ở Bước 4 thì bỏ qua bước này.

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
docker compose exec php php artisan queue:retry <uuid>   # requeue a failed job
docker compose exec php php artisan tinker
```

### Vào shell container

```powershell
# Vào PHP container (để debug, chạy lệnh)
docker compose exec php bash

# Vào MySQL container (nhập password khi được hỏi)
docker compose exec mysql mysql -u root -p stock_app
```

### Giám sát Queue

Job Redis (`sync:stock-prices` dispatch nhiều `ProcessStockPriceSync`, `sync:company-financials` dispatch nhiều `SyncCompanyFinancialJob`, ...) chạy qua 6 worker `queue:work redis --queue=high,default` (`docker/php/supervisord.conf`) — tăng từ 3 lên 6 để tận dụng việc các job này chủ yếu chờ mạng (I/O-bound), không tốn CPU. Kết hợp với `py/get_stock.py` tự chạy song song 4 mã cùng lúc trong 1 lần gọi (thay vì tuần tự từng mã), tổng số request đồng thời tới VCI tối đa là 6×4=24 (từ 3 trước đây) — xem "Tối ưu tốc độ sync" và `docs/PYTHON_INTEGRATION.md`. Laravel Horizon từng được thử để có dashboard giám sát nhưng bị bỏ — SPA riêng, khó theo dõi hơn cần thiết cho quy mô app này — thay bằng trang tự viết theo đúng pattern Controller/Service/Repository sẵn có (xem `docs/HISTORY.md`, `QUEUE_MONITOR_CUSTOM_PAGE`).

### Tối ưu tốc độ sync

Nếu sync vẫn chậm sau khi đã tăng worker + song song hoá, cân nhắc theo thứ tự:
1. **Chỉnh 2 con số cùng lúc**: `numprocs` trong `docker/php/supervisord.conf` (số worker) và `MAX_CONCURRENT` trong `py/get_stock.py`/`py/get_exchange_rate.py` (số luồng song song mỗi worker). Tích của 2 số = số request đồng thời tối đa tới VCI — tăng quá cao dễ bị VCI rate-limit/chặn mạnh hơn, phản tác dụng. Theo dõi qua **Admin > Giám sát Queue** khi chỉnh để biết throughput thực tế và có bị fail nhiều hơn không.
2. **Bậc cuối, tốn công nhất**: dựng 1 Python service sống lâu (FastAPI/Flask) để PHP gọi qua HTTP thay vì `exec()` — loại bỏ hẳn chi phí khởi động lại `vnstock`/`pandas` mỗi lần gọi (hiện tại mỗi `exec()` là 1 process Python mới). Chưa làm, chỉ ghi nhận là hướng tối ưu tiếp theo nếu 2 bước trên vẫn chưa đủ.

- **Dashboard**: **Admin > Hệ thống > Giám sát Queue** (`https://sunstock-local.dev/admin/queue`, permission `manage-queue` — mặc định chỉ role `admin` có, xem [docs/RBAC.md](RBAC.md)). Gồm: số job đang chờ/đang chạy/hoãn theo từng queue; bảng **"Đang xử lý (real-time)"** — job nào đang chạy ngay lúc này, nội dung (vd mã cổ phiếu), bắt đầu lúc mấy giờ, đã chạy bao lâu; bảng **"Vừa xử lý xong"** — job hoàn thành/fail gần nhất kèm thời lượng chạy, cộng số job đã xử lý trong ngày; tất cả tự refresh mỗi 5s không cần F5; danh sách job fail hẳn kèm nút Retry/Xoá/Retry tất cả. Dữ liệu "đang xử lý"/"vừa xong" lấy từ bảng `queue_job_logs` — ghi bởi `App\Support\QueueJobLogger` (lắng nghe `Queue::before/after/failing` trong `AppServiceProvider`), dọn định kỳ bởi lệnh `queue-logs:prune` (chạy hàng giờ).
- **CLI nhanh không cần mở dashboard**:
  ```powershell
  docker compose exec redis redis-cli LLEN "laravel-database-queues:default"          # còn bao nhiêu job chờ
  docker compose exec redis redis-cli ZCARD "laravel-database-queues:default:reserved" # bao nhiêu job đang chạy
  docker compose exec php php artisan queue:failed                                      # danh sách job fail hẳn
  docker compose exec php php artisan queue:retry <uuid>                                 # retry 1 job
  docker compose logs -f queue                                                           # log real-time
  ```
- **`retry_after` phải luôn lớn hơn `timeout`** (`config/queue.php` → `connections.redis.retry_after`, hiện set 660s > timeout 600s trong `--timeout=600` ở supervisord.conf) — nếu để thấp hơn, Redis sẽ tưởng nhầm worker chết khi job chạy lâu (rất hay xảy ra khi API vnstock/VCI chậm) và giao job đó cho worker khác chạy trùng, dẫn tới `MaxAttemptsExceededException` dù job chưa từng thật sự lỗi. Đây là nguyên nhân đã xác nhận của 1 job fail thật trong lịch sử — xem `docs/HISTORY.md`.

---

## 4b. Tối ưu tốc độ và dung lượng (10/10/2026)

Đo trên máy dev (project nằm ở ổ Windows, Docker Desktop WSL2): `stat` 361 file qua bind mount mất **1,1 giây** so với **5 ms** trên đĩa riêng của container, nên mọi thứ PHP đọc qua mount đều chậm. Đã áp dụng:

| Thay đổi | Ở đâu | Kết quả đo được / lý do |
|---|---|---|
| `opcache.revalidate_freq = 2` (trước là 0), `interned_strings_buffer = 16`, `realpath_cache_ttl = 600` | `docker/php/php.ini` | Trang `/login` 170–240 ms → 106–180 ms (nhanh hơn 35–45%). Sửa code vẫn thấy sau tối đa 2 giây; cần thấy ngay thì đặt lại `0`. |
| View đã compile + cache khởi động của Laravel (`packages.php`, `services.php`…) nằm ở `/var/cache/laravel` (đĩa của container) thay vì `storage/framework/views` và `bootstrap/cache` trên ổ Windows | `docker-compose.yml` (`x-php-env`: `VIEW_COMPILED_PATH`, `APP_*_CACHE`) | Không đọc 100+ file view qua mount. Thư mục được tạo lúc container khởi động. Chỉ là cache: mất khi tạo lại container thì tự dựng lại; `php artisan view:clear` vẫn đúng chỗ. |
| `auto_prepend_file = dev-umask.php` + `PHP_DEV_UMASK=1` | `docker/php/dev-umask.php`, compose | File PHP tạo ra (view compile, log) ai cũng ghi được, nên `docker compose exec` (root) và php-fpm (www-data) không khóa nhau. **Dev only — bỏ biến `PHP_DEV_UMASK` khi deploy thật.** |
| MySQL: `performance_schema=OFF`, `skip-log-bin`, `innodb_flush_log_at_trx_commit=2`, `innodb_flush_method=O_DIRECT`, `innodb_buffer_pool_size=384M`, `max_connections=100` | tham số `command:` của service `mysql` (không dùng `my.cnf` vì mount Windows báo file world-writable nên MySQL bỏ qua) | RAM MySQL 882 MB → ~540 MB; hết binlog (283 MB) và bớt fsync; ghi hàng loạt nhanh hơn. **Đánh đổi:** VM sập đột ngột có thể mất tối đa 1 giây commit cuối (đã có `db:backup` hằng tuần). Production: dùng lại `flush_log_at_trx_commit=1` và bật binlog nếu cần replica. |
| nginx: `gzip on` (text/JSON/CSS/JS/SVG), `open_file_cache` | `docker/nginx/default.conf` | Nhỏ hơn khi truyền, bớt hỏi mount cho asset tĩnh. |
| Log container xoay vòng 10 MB × 3 file / service | `x-logging` trong compose | Trước đó log json-file không bao giờ xoay vòng. |
| Một image dùng chung `stock-app-php:latest` cho `php`, `queue`, `scheduler` | compose (`image:` + `pull_policy: never`) | Trước đây cùng một Dockerfile bị build 3 lần dưới 3 tên. |

### 4c. Python / vnstock: image build lại được (10/10/2026)

**Chuyện gì đã xảy ra:** vnstock đã rời PyPI (`pypi.org/pypi/vnstock` trả 404) và giờ phát hành ở index riêng `https://vnstocks.com/api/simple` (`pip install -U --extra-index-url https://vnstocks.com/api/simple vnstock vnai`, theo README của `thinh-vu/vnstock`). Index chỉ giữ 2 bản mới nhất mỗi gói, nên `pip install vnstock` cũ trong Dockerfile hỏng và image không build lại được; thêm nữa tag `php:8.2-fpm` tự nhảy sang Debian 13 / Python 3.13.

**Cách đang dùng (đã build và kiểm tra):**
- Base image ghim theo **digest** trong `docker/php/Dockerfile` (PHP 8.2.34, Debian 13, Python 3.13). Nâng cấp có chủ đích: pull tag mới → chép digest mới → build → chạy test.
- `docker/python/requirements.txt` ghim đúng phiên bản mọi gói Python (cài từ PyPI).
- **vnstock 4.0.9 và vnai 2.6.3 cài từ wheel local** `docker/python/wheelhouse/*.whl` với `--no-index` (không phụ thuộc index ngoài lúc build, không dùng `--extra-index-url` nên không có rủi ro dependency confusion). Wheel **không commit vào git** (gitignore): vnstock có giấy phép "Custom (Personal Use)", vnai là "proprietary". Trên máy mới: `sh docker/python/fetch-wheels.sh` trước khi `docker compose build`. Thiếu wheel, build dừng với thông báo rõ ràng.
- Đã chuyển stack sang image này (10/10/2026); image cũ giữ lại với tag `stock-app-php:before-vnstock4` để quay lui (`docker tag stock-app-php:before-vnstock4 stock-app-php:latest && docker compose up -d`). Sau mỗi lần `docker compose up -d` tạo lại container `php`, nếu site trả 502 thì `docker compose restart nginx` (nginx giữ địa chỉ IP cũ của php).
- Khi tự chạy `docker run` với image này để test, phải gắn `docker/php/php.ini` và `dev-umask.php` như trong compose, không thì PHP dùng `memory_limit` 128M và bộ test chết giữa chừng.
- Nâng phiên bản vnstock sau này: sửa `VNSTOCK` / `VNAI` trong `fetch-wheels.sh`, chạy lại script, build, chạy các script `py/*.py` và test.
- `VNSTOCK_DISABLE_AGENT_SETUP=1` vẫn để trong compose (bản < 4.0.9 ghi file vào cấu hình công cụ AI mỗi lần import; 4.0.9 đã sửa).
- Tài liệu `docs/vnstock-agent/` (bản sao hướng dẫn của bên thứ ba, dừng ở vnstock_data 3.0.0) đã bị xóa vì lỗi thời; API key vẫn đọc từ `.env` (`VNSTOCK_API_KEY`) như cũ.
Không áp dụng (đã cân nhắc, chưa cần): giới hạn RAM/CPU của WSL bằng `.wslconfig`, đưa cả project vào ổ WSL, `docker compose watch`, hạ số worker queue. Chỉ cần nếu sau các thay đổi trên máy vẫn chậm.

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

## 6b. Backup database tự động (hàng tuần)

Database nằm trong volume `mysql_data` của Docker — nếu Docker Desktop bị reset/xóa dữ liệu (đã từng xảy ra: mất hết container + image) thì volume cũng có thể mất theo. Vì vậy có một job backup ghi ra **ngoài Docker**, vào thư mục nằm ngang hàng với source code:

```
C:\xampp\htdocs\stock-app\          ← source code
C:\xampp\htdocs\database_backup\    ← backup (tự tạo, "ngang cấp" với thư mục project)
    2026\9\20\stock_app_db.zip      ← năm \ tháng \ ngày (không đệm số 0)
                └── stock_app_db.sql
```

Đường dẫn **không hard-code**: là `<thư mục cha của project>\database_backup`, nên project nằm ở đâu thì thư mục backup nằm cạnh đó (`docker-compose.yml` mount `../database_backup` vào container tại `/backups`).

**Quy tắc "mỗi tuần một lần":** `php artisan db:backup` tìm zip hợp lệ (có file `.sql` bên trong, không rỗng) mới nhất; nếu nó **dưới 7 ngày tuổi thì bỏ qua**, ngược lại mới dump. Không backup theo ngày để đỡ tốn dung lượng.

**Khi nào chạy:**
- Mỗi lần mở Docker: `scheduler` chạy `db:backup` đầu tiên trong `scheduler-entrypoint.sh` (nếu đã có backup trong tuần thì bỏ qua ngay).
- Máy bật suốt: scheduler kiểm tra mỗi giờ (`hourly`) — chỉ là kiểm tra rẻ, chỉ dump khi đã quá 7 ngày.

```powershell
docker compose exec php php artisan db:backup            # backup nếu chưa có bản nào trong 7 ngày
docker compose exec php php artisan db:backup --force    # backup ngay bây giờ
docker compose exec php php artisan db:backup --days=3   # đổi chu kỳ (mặc định 7)
docker compose exec php php artisan db:backup --list     # liệt kê các bản backup đang có
```

**An toàn:** dump bằng `mysqldump --single-transaction` (không khóa bảng, dữ liệu nhất quán) vào thư mục tạm, kiểm tra dòng `-- Dump completed` (phát hiện file cụt), nén, kiểm tra zip đọc được rồi mới đổi tên thành file cuối — lỗi giữa chừng không để lại file "trông như backup". Mật khẩu truyền qua biến môi trường, không nằm trên command line. Job **không bao giờ xóa** bản cũ.

**Lưu ý:**
- Cần build lại image một lần để có `mysqldump`: `docker compose up -d --build`.
- Mỗi bản ≈ 40 MB (SQL ≈ 200 MB nén lại). **Chưa có tự động dọn bản cũ** — thỉnh thoảng xóa thư mục năm/tháng cũ bằng tay.
- Chỉ backup nội dung MySQL (không gồm Redis, `vendor`, `.env`).
- Đổi vị trí: đặt `DB_BACKUP_PATH` trong compose (đường dẫn trong container) và sửa phần mount tương ứng.

**Khôi phục:**

```powershell
# 1. Giải nén ra file .sql (ví dụ bản 2026-09-20)
Expand-Archive C:\xampp\htdocs\database_backup\2026\9\20\stock_app_db.zip -DestinationPath C:\temp\restore

# 2. Nạp vào MySQL container (thay <DB_PASSWORD> bằng mật khẩu trong .env)
docker compose exec -T -e MYSQL_PWD=<DB_PASSWORD> mysql mysql -u root stock_app < C:\temp\restore\stock_app_db.sql
```

---

## 7. Khôi phục về XAMPP (nếu cần)

```powershell
cd C:\xampp\htdocs\stock-app

# Đưa .env về giá trị không-Docker: cột "Mặc định trong .env.example" ở bảng mục 2
# (APP_URL, DB_*, SESSION_DRIVER, QUEUE_CONNECTION, CACHE_STORE, REDIS_HOST, PYTHON_PATH). File .env.xampp cũ đã bị xóa.

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
2. Tạo `.env` cho production (copy từ `.env.example`, đặt các giá trị Docker như mục 2, đổi `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://yourdomain.com`)
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

### 502 Bad Gateway sau khi `docker compose up -d` tạo lại container `php`

Nginx giữ địa chỉ IP cũ của `php`. Chạy `docker compose restart nginx` (hoặc `docker compose up -d --force-recreate nginx`).

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
| `docker/php/Dockerfile` | Build PHP image: PHP 8.2 (base ghim theo digest) + Python 3.13 + extensions + vnstock từ wheel local (mục 4c) |
| `docker/python/requirements.txt`, `fetch-wheels.sh`, `wheelhouse/` | Gói Python ghim phiên bản; script tải wheel vnstock/vnai (wheel không commit) |
| `docker/nginx/default.conf` | Nginx config: HTTPS, HTTP redirect, PHP-FPM proxy |
| `docker/php/supervisord.conf` | Chạy 6 process `queue:work redis --queue=high,default` (mục "Giám sát Queue" ở trên) |
| `docker/php/php.ini` / `docker/php/dev-umask.php` | Custom PHP settings (opcache revalidate 2 s, realpath cache) và umask dev-only — xem mục 4b |
| `docker/nginx/ssl/*.pem` | SSL cert (mkcert, trusted, expires 2028-08-30) |
| `.env.example` | Mẫu duy nhất để tạo `.env` (`cp .env.example .env`, rồi đặt giá trị Docker — xem mục 2 và Bước 0) |
| `docker/init.sql` | Database dump 459MB (gitignored) |

---

*Last updated: September 15, 2026*

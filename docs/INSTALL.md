# SRP (Smart Redirect Platform) — Panduan Instalasi

## Persyaratan Sistem

| Komponen | Minimum | Rekomendasi |
|----------|---------|-------------|
| PHP | 8.3+ | 8.3.x |
| MySQL / MariaDB | 5.7+ / 10.3+ | 8.0+ / 10.6+ |
| Web Server | Apache (mod_rewrite) | Apache 2.4+ |
| Ekstensi PHP | curl, json, mbstring, mysqli, openssl, pdo, pdo_mysql | + apcu, redis |

### Ekstensi PHP Opsional

- **APCu** — In-memory caching untuk performa lebih baik
- **Redis** — Distributed cache (direkomendasikan untuk multi-server)
- **Memcached** — Alternatif distributed cache

---

## Metode Instalasi

### Metode 1: Web Installer (Rekomendasi)

1. Upload semua file ke hosting (cPanel / VPS)
2. Set document root ke folder `public_html/`
3. Buka browser, akses `https://domain-anda.com/install.php`
4. Ikuti langkah-langkah wizard:
   - **Step 1**: Konfigurasi database (host, port, nama DB, user, password)
   - **Step 2**: Buat akun admin (username + password min 8 karakter)
   - **Step 3**: Generate API key (otomatis atau manual)
   - **Step 4**: Test koneksi DB dan import schema
5. Setelah selesai, file `.installed` dibuat otomatis sebagai lock
6. Login ke dashboard di `/login.php`

> **Catatan**: Installer otomatis membuat file `.env` dan import `schema.sql`. Installer hanya bisa diakses sekali — setelah selesai, akses akan di-redirect ke login.

### Metode 2: CLI Installer (cPanel / SSH)

```bash
# Upload file ke server, lalu SSH
chmod +x install.sh
./install.sh
```

Installer CLI akan:
1. Deteksi PHP 8.3 dan cek ekstensi yang dibutuhkan
2. Prompt konfigurasi DB, admin, dan API key secara interaktif
3. Jalankan `composer install --no-dev` (jika Composer tersedia)
4. Setup `.htaccess` dan hapus kredensial hardcoded
5. Import `schema.sql` ke database
6. Set permission file (PHP: 644, cron: 750, .env: 600)
7. Install cron jobs (purge, backup, health-check)
8. Jalankan health-check untuk verifikasi

---

## Instalasi Manual

Jika tidak bisa menggunakan installer, ikuti langkah berikut:

### 1. Upload File

Upload semua file ke server. Set document root ke `public_html/`.

### 2. Buat File `.env`

```bash
cp .env.example .env
chmod 600 .env
```

Edit `.env` dan isi minimal:

```ini
# Database
SRP_DB_HOST=127.0.0.1
SRP_DB_PORT=3306
SRP_DB_NAME=nama_database
SRP_DB_USER=user_database
SRP_DB_PASS=password_database

# Admin
SRP_ADMIN_USER=admin
SRP_ADMIN_PASSWORD_HASH=

# API Key (generate: openssl rand -hex 32)
SRP_API_KEY=your-api-key-here

# URL
APP_URL=https://domain-anda.com
APP_ENV=production
```

Generate password hash:
```bash
php -r "echo password_hash('password_anda', PASSWORD_DEFAULT), PHP_EOL;"
```

### 3. Import Schema Database

```bash
mysql -h127.0.0.1 -uUSER -p NAMA_DB < schema.sql
```

Schema membuat 3 tabel:
- **`settings`** — Konfigurasi single-row (redirect URL, country filter, postback)
- **`logs`** — Traffic log append-only (IP, UA, click_id, country, decision A/B)
- **`conversions`** — Data konversi/lead (click_id, payout, currency, status)

### 4. Install Dependencies

```bash
composer install --no-dev --classmap-authoritative
```

> Jika Composer tidak tersedia di shared hosting dan tidak ada package runtime di `composer.json`, aplikasi tetap berjalan dengan fallback autoloader di `src/Config/Bootstrap.php`.

### 5. Set Permissions

```bash
# Direktori
mkdir -p logs backups storage/tmp
chmod 755 logs backups storage/tmp

# File PHP
find src/ public_html/ -name "*.php" -exec chmod 644 {} \;
find cron/ -name "*.php" -exec chmod 750 {} \;

# File sensitif
chmod 600 .env
chmod 640 schema.sql

# Web-deny untuk direktori non-public
for dir in cron src logs backups storage; do
    echo -e "Order deny,allow\nDeny from all" > "$dir/.htaccess"
done
```

### 6. Setup Cron Jobs

```bash
crontab -e
```

Tambahkan:
```cron
# Cleanup harian (logs, session, tmp, backup lama)
0 3 * * * php /path/to/cron/purge.php --log-days=7 --backup-days=30 >> /path/to/logs/purge.log 2>&1

# Backup DB harian
0 1 * * * php /path/to/cron/backup.php 30 >> /path/to/logs/backup.log 2>&1

# Health check setiap 15 menit
*/15 * * * * php /path/to/cron/health-check.php >> /path/to/logs/health.log 2>&1
```

---

## Konfigurasi Cache

Cache driver dipilih otomatis: Redis → Memcached → APCu → none.

Untuk memilih manual, set di `.env`:

```ini
# Pilihan: redis | memcached | apcu | none
CACHE_DRIVER=redis

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DB=0

# Memcached
MEMCACHED_HOST=127.0.0.1
MEMCACHED_PORT=11211
```

---

## Konfigurasi API Client

Tuning untuk koneksi ke remote decision server:

```ini
SRP_API_TIMEOUT=8              # Timeout total (detik)
SRP_API_CONNECT_TIMEOUT=3      # Timeout koneksi (detik)
SRP_API_FAILURE_COOLDOWN=30    # Cooldown setelah error (detik)
SRP_API_MAX_RETRIES=0          # Jumlah retry (0 = tanpa retry)
SRP_API_BACKOFF_BASE_MS=250    # Base delay exponential backoff (ms)
SRP_API_BACKOFF_MAX_MS=1500    # Max delay backoff (ms)
SRP_API_RESPONSE_CACHE_SECONDS=3  # Cache response identik (detik)
```

---

## Rate Limiting

Rate limiting API publik per IP per endpoint:

```ini
SRP_PUBLIC_API_RATE_WINDOW=60      # Window dalam detik
SRP_PUBLIC_API_RATE_MAX=1000       # Max request per window
SRP_PUBLIC_API_RATE_HEAVY_MAX=30   # Max untuk endpoint berat (logs, analytics)
```

---

## Viewer Account (Opsional)

Untuk akun read-only dashboard:

```ini
SRP_USER_USER=viewer
SRP_USER_PASSWORD_HASH=    # Generate sama seperti admin
SRP_USER_PASSWORD=         # Atau plain text (dev only)
```

---

## Verifikasi Instalasi

```bash
# Test health check
php cron/health-check.php

# Test API
curl -s -H "X-API-Key: YOUR_KEY" https://domain-anda.com/api/v1/status

# Test login
# Buka https://domain-anda.com/login.php di browser
```

---

## Troubleshooting

| Masalah | Solusi |
|---------|--------|
| 500 Internal Server Error | Cek `logs/` dan PHP error log. Pastikan `.env` ada dan permission benar (600) |
| Login gagal | Pastikan `SRP_ADMIN_USER` dan `SRP_ADMIN_PASSWORD_HASH` di `.env` benar |
| Database connection failed | Verifikasi kredensial DB di `.env`. Test: `mysql -h HOST -u USER -p DB_NAME` |
| API 401 Unauthorized | Cek `SRP_API_KEY` di `.env` dan kirim via header `X-API-Key` |
| Cache tidak aktif | Cek `CACHE_DRIVER` di `.env` dan pastikan ekstensi PHP terpasang |
| Installer tidak muncul | File `.installed` sudah ada. Hapus untuk mengakses ulang (hati-hati di production) |
| Permission denied | Jalankan ulang perintah chmod di bagian "Set Permissions" |

---

## Struktur Deployment

```
project-root/
├── public_html/        ← Document root (Apache/Nginx)
│   ├── index.php       ← Dashboard
│   ├── login.php       ← Login page
│   ├── decision.php    ← Core routing
│   ├── api.php         ← Public REST API
│   ├── data.php        ← Internal API
│   ├── install.php     ← Web installer
│   └── .htaccess       ← URL rewriting
├── src/                ← Application source
├── cron/               ← Scheduled tasks
├── vendor/             ← Composer dependencies
├── logs/               ← Application logs
├── backups/            ← DB backups
├── storage/tmp/        ← Temporary files
├── schema.sql          ← Database schema
├── .env                ← Configuration (JANGAN commit!)
├── .env.example        ← Template konfigurasi
└── .installed          ← Lock file installer
```

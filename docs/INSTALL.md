# SRP v2.1 (Smart Redirect Platform) — Panduan Instalasi Lengkap

## Daftar Isi

1. [Persyaratan Sistem](#persyaratan-sistem)
2. [Arsitektur Multi-Domain](#arsitektur-multi-domain)
3. [Deploy dari Git](#deploy-dari-git)
4. [Instalasi Brand Domain (Dashboard)](#instalasi-brand-domain-dashboard)
5. [Instalasi Tracking Domain (entry.php)](#instalasi-tracking-domain-entryphp)
6. [Konfigurasi .env Lengkap](#konfigurasi-env-lengkap)
7. [Deploy Script](#deploy-script)
8. [Verifikasi & Testing](#verifikasi--testing)
9. [Update & Maintenance](#update--maintenance)
10. [Troubleshooting](#troubleshooting)

---

## Persyaratan Sistem

| Komponen | Minimum | Rekomendasi |
|----------|---------|-------------|
| PHP | 8.3+ | 8.3.x |
| MySQL / MariaDB | 5.7+ / 10.3+ | 8.0+ / 10.6+ |
| Web Server | Apache (mod_rewrite) | LiteSpeed / Apache 2.4+ |
| Hosting | Shared hosting (cPanel) | cPanel + SSH access |

### Ekstensi PHP Wajib

- `curl` — HTTP requests ke API
- `json` — Encode/decode JSON
- `mbstring` — UTF-8 string handling
- `openssl` — SSL/TLS & crypto
- `pdo` + `pdo_mysql` — Database

### Ekstensi PHP Opsional

- **APCu** — In-memory caching (performa ++, gratis)
- **Redis** — Distributed cache (multi-server)
- **Memcached** — Alternatif distributed cache

---

## Arsitektur Multi-Domain

SRP menggunakan 2 domain terpisah:

```
┌─────────────────────────────────┐     ┌───────────────────────────────┐
│  BRAND DOMAIN                   │     │  TRACKING DOMAIN              │
│  https://hantuin.com            │     │  https://tracking.com         │
│                                 │     │                               │
│  ├── Dashboard (login, stats)   │     │  ├── entry.php                │
│  ├── REST API (/api/v1/...)     │◄────│  │   POST /api/v1/decision    │
│  ├── Decision engine            │     │  ├── .env                     │
│  └── Postback tracking          │     │  └── .htaccess (opsional)     │
│                                 │     │                               │
│  File: semua source code        │     │  File: hanya 2-3 file         │
└─────────────────────────────────┘     └───────────────────────────────┘
```

- **Brand domain** — Server utama: dashboard admin, API, database, semua logic
- **Tracking domain** — Server client: terima traffic visitor, minta keputusan ke brand domain via API, redirect visitor

---

## Deploy dari Git

### Opsi A: Git Clone via SSH

```bash
# SSH ke server
ssh user@server.com

# Clone repo (pertama kali)
cd ~
git clone https://github.com/xctd-glitch/Hantuin-v2.git srp
cd srp

# Install
chmod +x install.sh
./install.sh
```

### Opsi B: cPanel Git Version Control

1. Login **cPanel** → cari **Git™ Version Control**
2. Klik **Create**
3. Isi:
   - **Clone URL:** `https://github.com/xctd-glitch/Hantuin-v2.git`
   - **Repository Path:** `/home/username/srp`
   - **Repository Name:** `Hantuin-v2`
4. Klik **Create**
5. Buka **Terminal** cPanel:

```bash
cd ~/srp
chmod +x install.sh
./install.sh
```

### Opsi C: Upload Zip Manual

```bash
# Di komputer lokal: build zip
php build.php
# Output: dist/srp-v2.1-XXXXXXXX-XXXX.zip
```

1. Login **cPanel** → **File Manager**
2. Upload zip ke `/home/username/`
3. Klik kanan → **Extract**
4. Folder `srp/` muncul — buka **Terminal**:

```bash
cd ~/srp
chmod +x install.sh
./install.sh
```

---

## Instalasi Brand Domain (Dashboard)

### Metode 1: Auto Installer (CLI — Rekomendasi)

```bash
cd ~/srp
chmod +x install.sh
./install.sh
```

Installer interaktif akan:

1. ✓ Deteksi PHP 8.3 dan cek semua ekstensi
2. ✓ Prompt konfigurasi database (host, port, nama, user, password)
3. ✓ Buat akun admin (username + password, auto bcrypt hash)
4. ✓ Generate API key (`openssl rand -hex 32`)
5. ✓ Tulis `.env` dengan permission `600`
6. ✓ Jalankan `composer install --no-dev`
7. ✓ Copy `.htaccess.example` → `public_html/.htaccess`
8. ✓ Import `schema.sql` ke database
9. ✓ Set permission semua file & direktori
10. ✓ Install 3 cron jobs (purge, backup, health-check)
11. ✓ Jalankan health-check untuk verifikasi

### Metode 2: Web Installer

1. Upload semua file ke server
2. Pastikan document root mengarah ke `public_html/`
3. Buka `https://domain-anda.com/install.php`
4. Ikuti wizard:
   - Step 1: Konfigurasi database
   - Step 2: Buat akun admin
   - Step 3: Generate API key
   - Step 4: Test koneksi & import schema
5. File `.installed` dibuat sebagai lock
6. **Hapus `install.php` setelah selesai!**

### Metode 3: Manual

#### 3a. Buat `.env`

```bash
cp .env.example .env
chmod 600 .env
nano .env
```

Isi minimal:

```ini
SRP_ENV=production
APP_URL=https://hantuin.com
APP_DEBUG=false

SRP_DB_HOST=localhost
SRP_DB_PORT=3306
SRP_DB_NAME=nama_database
SRP_DB_USER=user_database
SRP_DB_PASS=password_database

SRP_ADMIN_USER=admin
SRP_ADMIN_PASSWORD_HASH=$2y$12$xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx

SRP_API_KEY=your-64-char-hex-api-key

SRP_FORCE_SECURE_COOKIES=true
```

Generate password hash:

```bash
php -r "echo password_hash('password_kamu', PASSWORD_BCRYPT), PHP_EOL;"
```

Generate API key:

```bash
openssl rand -hex 32
```

#### 3b. Install Dependencies

```bash
composer install --no-dev --classmap-authoritative
```

> Jika Composer tidak tersedia, aplikasi tetap jalan dengan fallback autoloader di `src/Config/Bootstrap.php`.

#### 3c. Import Database

```bash
mysql -h localhost -u USER -p NAMA_DB < schema.sql
```

Schema membuat 3 tabel:
- **`settings`** — Konfigurasi single-row (redirect URL, country filter, dll)
- **`traffic_logs`** — Traffic log (IP, UA, click_id, country, decision A/B)
- **`conversions`** — Data konversi (click_id, payout, status)

#### 3d. Setup .htaccess

```bash
cp .htaccess.example public_html/.htaccess
```

#### 3e. Set Permissions

```bash
# Buat direktori runtime
mkdir -p logs backups storage/tmp

# File PHP
find src/ public_html/ -name "*.php" -exec chmod 644 {} \;
find cron/ -name "*.php" -exec chmod 750 {} \;

# File sensitif
chmod 600 .env

# Proteksi direktori non-public
for dir in src cron logs backups storage; do
    echo 'Require all denied' > "$dir/.htaccess"
done
```

#### 3f. Setup Cron Jobs

```bash
crontab -e
```

Tambahkan:

```cron
# Cleanup harian (logs, session, backup lama)
0 3 * * * /usr/bin/php /home/user/srp/cron/purge.php >> /home/user/srp/logs/purge.log 2>&1

# Backup DB harian
0 1 * * * /usr/bin/php /home/user/srp/cron/backup.php 30 >> /home/user/srp/logs/backup.log 2>&1

# Health check setiap 15 menit
*/15 * * * * /usr/bin/php /home/user/srp/cron/health-check.php >> /home/user/srp/logs/health.log 2>&1
```

#### 3g. Point Domain

Di cPanel → **Domains** → set document root ke `/home/username/srp/public_html`

---

## Instalasi Tracking Domain (entry.php)

Tracking domain hanya butuh **2 file**: `entry.php` dan `.env`.

### Step 1: Upload File

```bash
# Dari repo lokal
cp entry.php /home/username/tracking-domain/public_html/
cp .env.entry.example /home/username/tracking-domain/public_html/.env
```

Atau via cPanel File Manager: upload `entry.php` dan `.env` ke document root tracking domain.

### Step 2: Konfigurasi `.env`

```bash
nano /home/username/tracking-domain/public_html/.env
```

```ini
# URL decision API di brand domain (WAJIB)
HANTUIN_API_URL=https://hantuin.com/api/v1/decision

# API Key dari dashboard → Settings (WAJIB)
HANTUIN_API_KEY=661577fc9b3cbc07e3bf6c3bc0f5d31331bcf5f1a91fd9aebd04c810cd01aa59

# Fallback path jika decision gagal
FALLBACK_PATH=/_meetups/

# Timeout (detik)
API_TIMEOUT=5
API_CONNECT_TIMEOUT=3

# Cache decision (detik, 0 = nonaktif)
DECISION_CACHE_TTL=3
```

### Step 3: Setup .htaccess (Opsional)

Buat `.htaccess` di root tracking domain untuk URL rewriting:

```apache
RewriteEngine On
RewriteBase /

# /_meetups/ → entry.php (fallback landing)
RewriteRule ^_meetups/?$ entry.php [L,QSA]

# /l/campaign → entry.php?user_lp=campaign
RewriteRule ^l/([a-zA-Z0-9_-]+)$ entry.php?user_lp=$1 [L,QSA]

# Proteksi .env
<FilesMatch "^\.env">
    Require all denied
</FilesMatch>
```

### Step 4: Test

```bash
# Harus redirect (302) ke fallback
curl -I "https://tracking.com/entry.php"

# Harus redirect (302) ke target URL
curl -I "https://tracking.com/entry.php?click_id=test123"
```

### Struktur Tracking Domain

```
tracking-domain/public_html/
├── entry.php       ← Standalone, tanpa dependencies
├── .env            ← Config (API URL, key, fallback)
└── .htaccess       ← URL rewriting (opsional)
```

---

## Konfigurasi .env Lengkap

### Brand Domain (.env)

```ini
# ── Application ──────────────────────────────────────
APP_URL=https://hantuin.com
APP_ENV=production
APP_DEBUG=false
SRP_ENV=production

# ── Database ─────────────────────────────────────────
SRP_DB_HOST=localhost
SRP_DB_PORT=3306
SRP_DB_NAME=srp
SRP_DB_USER=root
SRP_DB_PASS=password_here
SRP_DB_SOCKET=                          # Kosongkan jika pakai host+port

# ── Admin Credentials ────────────────────────────────
SRP_ADMIN_USER=admin
SRP_ADMIN_PASSWORD_HASH=$2y$12$...      # bcrypt hash (WAJIB di production)
SRP_ADMIN_PASSWORD=                     # Kosongkan jika hash sudah diisi

# ── Viewer Account (Opsional) ────────────────────────
SRP_USER_USER=viewer
SRP_USER_PASSWORD_HASH=$2y$12$...
SRP_USER_PASSWORD=

# ── API Key ──────────────────────────────────────────
SRP_API_KEY=64-char-hex-string

# ── Remote Decision (Opsional — proxy ke SRP lain) ───
SRP_REMOTE_DECISION_URL=
SRP_REMOTE_API_KEY=

# ── API Client Tuning ───────────────────────────────
SRP_API_TIMEOUT=8
SRP_API_CONNECT_TIMEOUT=3
SRP_API_FAILURE_COOLDOWN=30
SRP_API_MAX_RETRIES=0
SRP_API_BACKOFF_BASE_MS=250
SRP_API_BACKOFF_MAX_MS=1500
SRP_API_RESPONSE_CACHE_SECONDS=3
SRP_API_INFLIGHT_WAIT_MS=300

# ── VPN Check ────────────────────────────────────────
SRP_VPN_CHECK_ENABLED=1

# ── Rate Limiting ────────────────────────────────────
SRP_PUBLIC_API_RATE_WINDOW=60
SRP_PUBLIC_API_RATE_MAX=1000
SRP_PUBLIC_API_RATE_HEAVY_MAX=30
RATE_LIMIT_ATTEMPTS=5
RATE_LIMIT_WINDOW=900

# ── Security ─────────────────────────────────────────
SRP_FORCE_SECURE_COOKIES=true
SRP_TRUSTED_PROXIES=                    # Cloudflare IP ranges (auto-detect)

# ── Cache ────────────────────────────────────────────
CACHE_DRIVER=                           # redis | memcached | apcu | none (auto)
CACHE_PREFIX=srp_
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DB=0
MEMCACHED_HOST=127.0.0.1
MEMCACHED_PORT=11211

# ── Session ──────────────────────────────────────────
SESSION_LIFETIME=3600
```

### Tracking Domain (.env)

```ini
HANTUIN_API_URL=https://hantuin.com/api/v1/decision
HANTUIN_API_KEY=64-char-hex-string
FALLBACK_PATH=/_meetups/
API_TIMEOUT=5
API_CONNECT_TIMEOUT=3
DECISION_CACHE_TTL=3
```

---

## Deploy Script

### Build Production Zip

```bash
php build.php
# Output: dist/srp-v2.1-XXXXXXXX-XXXX.zip
```

### Deploy via SSH

```bash
# Full deploy ke brand domain + verifikasi
./deploy.sh --host user@server.com --path /home/user/srp --run-check

# Deploy hanya entry.php ke tracking domain
./deploy.sh --host user@server.com --path /home/user/tracking/public_html --entry-only

# Dry-run (preview tanpa eksekusi)
./deploy.sh --host user@server.com --path /home/user/srp --dry-run
```

### Deploy Verification

Upload `deploy-check.php` ke server dan jalankan:

```bash
php deploy-check.php
```

Cek 80+ item: files, source code, views, entry.php, .env config, PHP environment, database connection, permissions, security.

**⚠ Hapus `deploy-check.php` setelah selesai!**

---

## Verifikasi & Testing

### 1. Health Check

```bash
php cron/health-check.php
```

### 2. Test API

```bash
# Status
curl -s -H "X-API-Key: YOUR_KEY" https://hantuin.com/api/v1/status | jq .

# Stats
curl -s -H "X-API-Key: YOUR_KEY" https://hantuin.com/api/v1/stats | jq .
```

### 3. Test Dashboard

Buka `https://hantuin.com/login` di browser.

### 4. Test Tracking

```bash
# Tanpa click_id → langsung fallback (tidak panggil API)
curl -I "https://tracking.com/entry.php"
# Expected: 302 → /_meetups/

# Dengan click_id → panggil API → redirect
curl -I "https://tracking.com/entry.php?click_id=test123"
# Expected: 302 → target URL atau fallback
```

### 5. Test Postback

```bash
curl -s "https://hantuin.com/postback.php?click_id=test123&payout=1.50&status=approved"
```

---

## Update & Maintenance

### Update dari Git

```bash
cd ~/srp
git pull origin main
composer install --no-dev --classmap-authoritative
```

> `.env` tidak akan tertimpa (ada di `.gitignore`).

### Update Tracking Domain

```bash
# Dari repo lokal
cd ~/srp
cp entry.php /home/username/tracking-domain/public_html/
```

Atau deploy via script:

```bash
./deploy.sh --host user@server.com --path /home/user/tracking/public_html --entry-only
```

### Backup Manual

```bash
php cron/backup.php 30
```

### Cleanup Manual

```bash
php cron/purge.php --log-days=7 --backup-days=30
```

---

## Troubleshooting

| Masalah | Penyebab | Solusi |
|---------|----------|--------|
| 500 Internal Server Error | PHP error / .env missing | Cek `logs/` dan PHP error log. Pastikan `.env` ada (chmod 600) |
| Login gagal | Password hash salah | Generate ulang: `php -r "echo password_hash('pass', PASSWORD_BCRYPT);"` |
| Database connection failed | Kredensial salah | Test: `mysql -h HOST -u USER -p DB_NAME`. Cek `.env` |
| API 401 Unauthorized | API key salah | Cek `SRP_API_KEY` di `.env`. Kirim via header `X-API-Key` |
| API 404 Not Found | mod_rewrite tidak aktif | Gunakan path langsung: `/api.php/v1/stats` bukan `/api/v1/stats` |
| HTTP 415 Unsupported Media | Server proxy reject | Cek PHP handler di cPanel → MultiPHP Manager. Tambah `AddType application/x-httpd-php .php` di `.htaccess` |
| entry.php 400 "click_id required" | Visitor tanpa click_id | Update `entry.php` terbaru — sudah skip API call jika click_id kosong |
| Cache tidak aktif | Ekstensi belum terpasang | Cek `CACHE_DRIVER` di `.env`. Install APCu/Redis jika tersedia |
| Installer tidak muncul | `.installed` lock file | Hapus `.installed` untuk akses ulang (hati-hati di production) |
| Permission denied | File permission salah | Jalankan ulang: `chmod 644 *.php && chmod 600 .env` |
| Chart.js canvas error | Data belum loaded | Update `stats.view.php` terbaru — sudah ada null safety |
| Dashboard "Gagal memuat data" | `data.php` 415/404 | Update `.htaccess` dan `dashboard.view.php` terbaru |
| Cookies tidak set (localhost) | Secure cookies aktif | Set `SRP_FORCE_SECURE_COOKIES=false` untuk development |
| Log spam 400 errors | Health check tanpa click_id | Normal — health check tools kirim request kosong |

---

## Struktur Deployment Lengkap

### Brand Domain

```
/home/username/srp/
├── public_html/            ← Document root
│   ├── index.php           ← Dashboard
│   ├── login.php           ← Login page
│   ├── decision.php        ← Core routing engine
│   ├── api.php             ← Public REST API
│   ├── data.php            ← Internal dashboard API
│   ├── postback.php        ← Conversion tracking
│   ├── stats.php           ← Statistics page
│   ├── landing.php         ← Landing page
│   ├── .htaccess           ← URL rewriting & security
│   ├── assets/             ← CSS, JS, icons
│   └── pwa/                ← Progressive Web App
├── src/                    ← Application source
│   ├── Config/             ← Bootstrap, DB, Env, Cache
│   ├── Controllers/        ← Request handlers
│   ├── Models/             ← Data access & business logic
│   ├── Middleware/          ← Session, Security headers
│   └── Views/              ← PHP templates & components
├── cron/                   ← Scheduled tasks
├── vendor/                 ← Composer autoloader
├── logs/                   ← Application logs
├── backups/                ← Database backups
├── storage/tmp/            ← Temporary files
├── schema.sql              ← Database schema
├── .env                    ← Configuration (JANGAN commit!)
└── .env.example            ← Template konfigurasi
```

### Tracking Domain

```
/home/username/tracking/public_html/
├── entry.php               ← Standalone redirect script
├── .env                    ← API URL, key, fallback config
└── .htaccess               ← URL rewriting (opsional)
```

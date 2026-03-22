#!/usr/bin/env bash
# =============================================================================
# Hantuin-v2 Decision Logic — Auto-Installer
# cPanel Shared Hosting
# =============================================================================
# Usage:
#   chmod +x install.sh
#   ./install.sh
#
# Re-run anytime — idempotent and safe.
# =============================================================================

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP=""
COMPOSER_BIN=""
MYSQL_OK=0

# ── Colors ─────────────────────────────────────────────────────────────────────
R='\033[0;31m'; Y='\033[1;33m'; G='\033[0;32m'
C='\033[0;36m'; W='\033[1m';    N='\033[0m'

ok()    { echo -e "  ${G}✓${N}  $*"; }
warn()  { echo -e "  ${Y}⚠${N}  $*"; }
err()   { echo -e "  ${R}✗${N}  $*" >&2; }
info()  { echo -e "  ${C}→${N}  $*"; }
die()   { err "$*"; echo; exit 1; }
hr()    { echo -e "\n${W}━━━  $*  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${N}"; }

ask() {
    # ask LABEL [DEFAULT] [secret=y]
    local label="$1" def="${2:-}" secret="${3:-n}" ans
    if [[ "$secret" == "y" ]]; then
        read -rsp "  ${label}${def:+ [$def]}: " ans; echo
    else
        read -rp  "  ${label}${def:+ [$def]}: " ans
    fi
    printf '%s' "${ans:-$def}"
}

confirm() {
    local label="$1" ans
    read -rp "  $label [y/N]: " ans
    [[ "${ans}" =~ ^[Yy]$ ]]
}

# ── PHP detection ──────────────────────────────────────────────────────────────
find_php() {
    local candidates=(
        php8.3 php83 php
        /usr/local/bin/php
        /usr/bin/php
        /opt/cpanel/ea-php83/root/usr/bin/php
        /usr/local/php83/bin/php
    )
    local bin ver major minor
    for candidate in "${candidates[@]}"; do
        bin="$(command -v "$candidate" 2>/dev/null)" || continue
        major="$("$bin" -r 'echo PHP_MAJOR_VERSION;' 2>/dev/null)" || continue
        minor="$("$bin" -r 'echo PHP_MINOR_VERSION;' 2>/dev/null)" || continue
        if [[ "$major" -eq 8 ]] && [[ "$minor" -eq 3 ]]; then
            PHP="$bin"; return 0
        fi
    done
    return 1
}

find_composer() {
    if command -v composer >/dev/null 2>&1; then
        COMPOSER_BIN="$(command -v composer)"
        return 0
    fi

    return 1
}

count_runtime_packages() {
    if [[ ! -f "$ROOT/composer.json" ]]; then
        printf '0'
        return 0
    fi

    "$PHP" -r '
        $path = $argv[1];
        $json = file_get_contents($path);
        if ($json === false) {
            fwrite(STDERR, "Failed to read composer.json" . PHP_EOL);
            exit(1);
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $count = 0;

        foreach (($data["require"] ?? []) as $name => $constraint) {
            if ($name === "php" || str_starts_with($name, "ext-")) {
                continue;
            }
            $count++;
        }

        echo $count;
    ' "$ROOT/composer.json"
}

# ── env reader (no source, handles no-export .env) ────────────────────────────
read_env() { grep -m1 "^${1}=" "$ROOT/.env" 2>/dev/null | cut -d= -f2- || true; }

# =============================================================================
echo -e "\n${W}  Hantuin-v2 Installer — cPanel Shared Hosting${N}"
echo -e   "  ${C}$ROOT${N}\n"
# =============================================================================

# ─────────────────────────────────────────────────────────────────────────────
hr "1 / 7 — Pre-flight checks"
# ─────────────────────────────────────────────────────────────────────────────

# Verify project structure
for f in schema.sql src/bootstrap.php public_html/index.php .env.example \
          src/Controllers/DecisionController.php \
          src/Controllers/ApiController.php \
          src/Models/TrafficLog.php \
          src/Views/components/analytics-tab.php; do
    [[ -f "$ROOT/$f" ]] || die "Not a valid Hantuin-v2 project root — missing: $f"
done
ok "Project root: $ROOT"

# PHP 8.3
find_php || die "PHP 8.3 not found. Try: ls /opt/cpanel/  or  which php8.3"
PHP_VER="$($PHP -r 'echo PHP_VERSION;')"
ok "PHP: $PHP  ($PHP_VER)"

# Required extensions
for ext in curl json mbstring mysqli openssl pdo; do
    $PHP -r "exit(extension_loaded('$ext') ? 0 : 1);" 2>/dev/null \
        || die "PHP extension missing: ${ext} — enable it in cPanel › PHP Extensions"
done
ok "PHP extensions: curl  json  mbstring  mysqli  openssl  pdo"

# Optional APCu
if $PHP -r "exit(extension_loaded('apcu') ? 0 : 1);" 2>/dev/null; then
    ok "APCu: available  (in-memory caching enabled)"
else
    warn "APCu not loaded — enable in cPanel › PHP Extensions for better performance"
fi

# MySQL CLI
if command -v mysql &>/dev/null; then
    MYSQL_OK=1
    ok "MySQL CLI: $(command -v mysql)"
else
    warn "mysql CLI not found — you'll import schema.sql manually after install"
fi

# ─────────────────────────────────────────────────────────────────────────────
hr "2 / 8 — Configure .env"
# ─────────────────────────────────────────────────────────────────────────────

WRITE_ENV=1
if [[ -f "$ROOT/.env" ]]; then
    if confirm ".env already exists — reconfigure it?"; then
        cp "$ROOT/.env" "$ROOT/.env.bak.$(date +%H%M%S)"
        info "Backed up to .env.bak.*"
    else
        WRITE_ENV=0
        info "Keeping existing .env"
    fi
fi

if [[ "$WRITE_ENV" -eq 1 ]]; then
    echo
    info "Database connection"
    DB_HOST=$(ask "  DB host"     "127.0.0.1")
    DB_PORT=$(ask "  DB port"     "3306")
    DB_NAME=$(ask "  DB name"     "srp")
    DB_USER=$(ask "  DB username")
    DB_PASS=$(ask "  DB password" "" "y")

    echo
    info "Admin dashboard"
    ADMIN_USER=$(ask "  Username" "admin")
    while true; do
        ADMIN_PASS=$(ask "  Password (min 8 chars)" "" "y")
        [[ ${#ADMIN_PASS} -ge 8 ]] && break
        err "Password must be at least 8 characters — try again"
    done
    # Hash via env var so special chars in password are never shell-interpolated
    ADMIN_HASH=$(E_PASS="$ADMIN_PASS" $PHP -r "echo password_hash(getenv('E_PASS'), PASSWORD_DEFAULT);")

    echo
    info "API key"
    API_KEY=$(ask "  API key  (leave blank to auto-generate)")
    if [[ -z "$API_KEY" ]]; then
        API_KEY=$(openssl rand -hex 32 2>/dev/null \
            || $PHP -r "echo bin2hex(random_bytes(32));")
        ok "Generated: $API_KEY"
    fi

    echo
    info "Application URL"
    APP_URL=$(ask "  App URL (e.g. https://trackng.us)")

    # Write .env using PHP so passwords with special chars are stored literally
    E_AU="$ADMIN_USER" E_AH="$ADMIN_HASH" E_AK="$API_KEY" \
    E_DH="$DB_HOST"    E_DP="$DB_PORT"    E_DN="$DB_NAME" \
    E_DU="$DB_USER"    E_DS="$DB_PASS"    E_UL="$APP_URL" \
    E_FILE="$ROOT/.env" \
    $PHP -r '
        $lines = [
            "# ===================================================================",
            "# Hantuin-v2 Environment Configuration",
            "# Last updated: " . date("Y-m-d H:i:s"),
            "# ===================================================================",
            "",
            "# ── Application ────────────────────────────────────────────────────",
            "APP_URL="                 . getenv("E_UL"),
            "APP_ENV=production",
            "APP_DEBUG=false",
            "SRP_ENV=production",
            "SRP_ENV_FILE=",
            "",
            "# ── Database ───────────────────────────────────────────────────────",
            "SRP_DB_HOST="             . getenv("E_DH"),
            "SRP_DB_PORT="             . getenv("E_DP"),
            "SRP_DB_NAME="             . getenv("E_DN"),
            "SRP_DB_USER="             . getenv("E_DU"),
            "SRP_DB_PASS="             . getenv("E_DS"),
            "SRP_DB_SOCKET=",
            "",
            "# ── API Keys ───────────────────────────────────────────────────────",
            "SRP_API_KEY="             . getenv("E_AK"),
            "",
            "# Remote Decision Server (S2S)",
            "SRP_REMOTE_DECISION_URL=",
            "SRP_REMOTE_API_KEY=",
            "",
            "# ── API Client Tuning ──────────────────────────────────────────────",
            "SRP_API_TIMEOUT=8",
            "SRP_API_CONNECT_TIMEOUT=3",
            "SRP_API_FAILURE_COOLDOWN=30",
            "SRP_API_MAX_RETRIES=0",
            "SRP_API_BACKOFF_BASE_MS=250",
            "SRP_API_BACKOFF_MAX_MS=1500",
            "SRP_API_RESPONSE_CACHE_SECONDS=3",
            "SRP_API_INFLIGHT_WAIT_MS=300",
            "",
            "# ── VPN Check ─────────────────────────────────────────────────────",
            "SRP_VPN_CHECK_ENABLED=1",
            "",
            "# ── Rate Limiting ──────────────────────────────────────────────────",
            "SRP_PUBLIC_API_RATE_WINDOW=60",
            "SRP_PUBLIC_API_RATE_MAX=1000",
            "SRP_PUBLIC_API_RATE_HEAVY_MAX=30",
            "RATE_LIMIT_ATTEMPTS=5",
            "RATE_LIMIT_WINDOW=900",
            "",
            "# ── Admin Credentials ──────────────────────────────────────────────",
            "SRP_ADMIN_USER="          . getenv("E_AU"),
            "SRP_ADMIN_PASSWORD_HASH=" . getenv("E_AH"),
            "SRP_ADMIN_PASSWORD=",
            "",
            "SRP_USER_USER=",
            "SRP_USER_PASSWORD_HASH=",
            "SRP_USER_PASSWORD=",
            "",
            "# ── Security ───────────────────────────────────────────────────────",
            "SRP_TRUSTED_PROXIES=",
            "SRP_FORCE_SECURE_COOKIES=true",
            "",
            "# ── Cache ──────────────────────────────────────────────────────────",
            "CACHE_DRIVER=",
            "CACHE_PREFIX=srp_",
            "REDIS_HOST=127.0.0.1",
            "REDIS_PORT=6379",
            "REDIS_PASSWORD=",
            "REDIS_DB=0",
            "MEMCACHED_HOST=127.0.0.1",
            "MEMCACHED_PORT=11211",
            "",
            "# ── Session ────────────────────────────────────────────────────────",
            "SESSION_LIFETIME=3600",
        ];
        file_put_contents(getenv("E_FILE"), implode(PHP_EOL, $lines) . PHP_EOL);
    '

    chmod 600 "$ROOT/.env"
    ok ".env written  (chmod 600)"
fi

# Expose key and DB creds from .env for later steps
API_KEY="$(read_env SRP_API_KEY)"
APP_URL="$(read_env APP_URL)"
DB_HOST="$(read_env SRP_DB_HOST)"; DB_PORT="$(read_env SRP_DB_PORT)"
DB_NAME="$(read_env SRP_DB_NAME)"; DB_USER="$(read_env SRP_DB_USER)"
DB_PASS="$(read_env SRP_DB_PASS)"

# ─────────────────────────────────────────────────────────────────────────────
hr "3 / 8 — Composer"
# ─────────────────────────────────────────────────────────────────────────────

if [[ -f "$ROOT/composer.json" ]]; then
    RUNTIME_PACKAGE_COUNT="$(count_runtime_packages)"
    if find_composer; then
        info "Composer: ${COMPOSER_BIN}"
        info "Running production install (no-dev, classmap-authoritative)"
        if PATH="$(dirname "$PHP"):$PATH" "$COMPOSER_BIN" install \
            --working-dir "$ROOT" \
            --no-dev \
            --prefer-dist \
            --classmap-authoritative \
            --no-interaction; then
            ok "Composer install completed"
        else
            die "Composer install failed — resolve dependency/platform issues before continuing"
        fi
    else
        if [[ "$RUNTIME_PACKAGE_COUNT" -gt 0 ]]; then
            die "composer.json declares runtime packages, but Composer is not available on this server"
        fi

        warn "Composer not found — skipping install. Runtime still works because the app ships a fallback autoloader and no third-party runtime packages."
    fi
else
    warn "composer.json not found — skipping Composer step"
fi

# ─────────────────────────────────────────────────────────────────────────────
hr "4 / 8 — public_html/.htaccess"
# ─────────────────────────────────────────────────────────────────────────────

HTACCESS="$ROOT/public_html/.htaccess"
if [[ ! -f "$HTACCESS" ]]; then
    cp "$ROOT/.htaccess.example" "$HTACCESS"
    info "Copied from .htaccess.example"
fi

# Strip any hardcoded SetEnv credentials block (security risk)
E_HTA="$HTACCESS" $PHP -r '
    $f = getenv("E_HTA");
    $c = file_get_contents($f);
    // Remove <IfModule mod_env.c>...</ IfModule> blocks containing SetEnv
    $c = preg_replace(
        "/<IfModule mod_env\.c>[\s\S]*?SetEnv[\s\S]*?<\/IfModule>\s*/",
        "",
        $c
    );
    file_put_contents($f, $c);
'
ok "Removed hardcoded SetEnv credentials from .htaccess"

# ─────────────────────────────────────────────────────────────────────────────
hr "5 / 8 — Database"
# ─────────────────────────────────────────────────────────────────────────────

if [[ "$MYSQL_OK" -eq 1 && -n "$DB_USER" ]]; then
    info "Testing connection to ${DB_HOST}:${DB_PORT:-3306} / ${DB_NAME} ..."
    # Create the database if it doesn't exist, then test access
    MYSQL_PWD="$DB_PASS" mysql \
        -h"${DB_HOST}" -P"${DB_PORT:-3306}" \
        -u"${DB_USER}" \
        -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` DEFAULT CHARACTER SET utf8mb4;" &>/dev/null || true

    if MYSQL_PWD="$DB_PASS" mysql \
            -h"${DB_HOST}" -P"${DB_PORT:-3306}" \
            -u"${DB_USER}" "${DB_NAME}" \
            -e "SELECT 1;" &>/dev/null; then
        ok "DB connection successful  →  ${DB_NAME}"
        if confirm "  Import schema.sql?  (safe — CREATE TABLE IF NOT EXISTS)"; then
            MYSQL_PWD="$DB_PASS" mysql \
                -h"${DB_HOST}" -P"${DB_PORT:-3306}" \
                -u"${DB_USER}" "${DB_NAME}" \
                < "$ROOT/schema.sql" 2>/dev/null
            ok "Schema imported  →  ${DB_NAME}"
        fi
    else
        err "DB connection failed — verify credentials in .env"
        warn "Manual import:"
        info "  mysql -h${DB_HOST} -u${DB_USER} -p ${DB_NAME} < schema.sql"
    fi
else
    warn "Skipped — manual import required:"
    info "  mysql -h${DB_HOST:-HOST} -u${DB_USER:-USER} -p ${DB_NAME:-srp} < $ROOT/schema.sql"
fi

# ─────────────────────────────────────────────────────────────────────────────
hr "6 / 8 — Directories and permissions"
# ─────────────────────────────────────────────────────────────────────────────

# Runtime dirs
for dir in logs backups storage/tmp; do
    mkdir -p "$ROOT/$dir"
    chmod 755 "$ROOT/$dir"
    ok "Directory: ${dir}/"
done

# Web-deny .htaccess for non-public dirs
DENY_BLOCK="$(printf 'Order deny,allow\nDeny from all\n')"
for dir in cron src logs backups storage; do
    [[ -d "$ROOT/$dir" ]] || continue
    printf '%s\n' "$DENY_BLOCK" > "$ROOT/$dir/.htaccess"
done
ok "Web-deny: cron/  src/  logs/  backups/  storage/"

# PHP files
find "$ROOT/src"         -name "*.php" -exec chmod 644 {} \; 2>/dev/null || true
find "$ROOT/public_html" -name "*.php" -exec chmod 644 {} \; 2>/dev/null || true
[[ -f "$ROOT/entry.php" ]] && chmod 644 "$ROOT/entry.php"

# Cron executables
find "$ROOT/cron" -name "*.php" -exec chmod 750 {} \; 2>/dev/null || true
[[ -f "$ROOT/cron/setup-cron.sh" ]] && chmod 750 "$ROOT/cron/setup-cron.sh"
[[ -f "$ROOT/install.sh"         ]] && chmod 750 "$ROOT/install.sh"

# Lock down sensitive files
chmod 600 "$ROOT/.env"
chmod 640 "$ROOT/schema.sql"
[[ -f "$ROOT/.env.example" ]] && chmod 640 "$ROOT/.env.example"

ok "Permissions set  (PHP: 644  |  cron: 750  |  .env: 600)"

# ─────────────────────────────────────────────────────────────────────────────
hr "7 / 8 — Crontab"
# ─────────────────────────────────────────────────────────────────────────────

if confirm "  Install cron jobs?"; then
    CRON_SCRIPTS=("purge.php" "backup.php" "health-check.php")
    CRON_ENTRIES=(
        "0 3 * * * ${PHP} ${ROOT}/cron/purge.php --log-days=7 --backup-days=30 >> ${ROOT}/logs/purge.log 2>&1"
        "0 1 * * * ${PHP} ${ROOT}/cron/backup.php 30 >> ${ROOT}/logs/backup.log 2>&1"
        "*/15 * * * * ${PHP} ${ROOT}/cron/health-check.php >> ${ROOT}/logs/health.log 2>&1"
    )

    CURRENT_CRON="$(crontab -l 2>/dev/null || true)"
    NEW_CRON="$CURRENT_CRON"
    ADDED=0

    for i in "${!CRON_SCRIPTS[@]}"; do
        script="${CRON_SCRIPTS[$i]}"
        entry="${CRON_ENTRIES[$i]}"
        if echo "$CURRENT_CRON" | grep -qF "cron/${script}"; then
            warn "Already in crontab: ${script}  (skipped)"
        else
            NEW_CRON="${NEW_CRON}"$'\n'"${entry}"
            ADDED=$((ADDED + 1))
            ok "Added: ${script}"
        fi
    done

    if [[ "$ADDED" -gt 0 ]]; then
        printf '%s\n' "$NEW_CRON" | crontab -
        ok "$ADDED job(s) installed"
        info "  purge.php     → 03:00 daily  (logs, sessions, tmp, backups)"
        info "  backup.php    → 01:00 daily  (DB backup)"
        info "  health-check  → every 15 min"
    fi
fi

# ─────────────────────────────────────────────────────────────────────────────
hr "8 / 8 — Verify"
# ─────────────────────────────────────────────────────────────────────────────

echo
$PHP "$ROOT/cron/health-check.php" 2>&1 \
    | grep -E '^\[|^---' \
    | head -20 \
    || true

# ─────────────────────────────────────────────────────────────────────────────
hr "Installation complete"
# ─────────────────────────────────────────────────────────────────────────────
echo -e "
  ${G}Hantuin-v2 is ready.${N}

  ${W}Next steps${N}
  ┌────────────────────────────────────────────────────────────┐
  │  1. Set document root  →  ${ROOT}/public_html              │
  │  2. Login dan konfigurasi redirect URL di dashboard        │
  │  3. Test:  curl -sI ${APP_URL:-https://trackng.us}/        │
  └────────────────────────────────────────────────────────────┘

  ${W}Dashboard ${N} ${C}${APP_URL:-https://trackng.us}/login.php${N}
  ${W}API key   ${N} ${C}${API_KEY}${N}

  ${W}Logs      ${N} ${ROOT}/logs/
  ${W}Backups   ${N} ${ROOT}/backups/
  ${W}Crontab   ${N} crontab -l
"

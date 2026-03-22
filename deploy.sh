#!/usr/bin/env bash
# =============================================================================
# SRP v2.1 — Deploy Script
# =============================================================================
# Upload & deploy zip ke cPanel shared hosting via SSH atau SCP.
#
# Usage:
#   # 1. Build dulu
#   php build.php
#
#   # 2. Deploy via SSH (auto-extract + verify)
#   ./deploy.sh --host user@server.com --path /home/user/srp
#
#   # 3. Deploy hanya ke tracking domain
#   ./deploy.sh --host user@server.com --path /home/user/tracking --entry-only
#
#   # 4. Deploy dengan zip tertentu
#   ./deploy.sh --host user@server.com --path /home/user/srp --zip dist/srp-v2.1-xxx.zip
#
#   # 5. Dry-run (tanpa upload)
#   ./deploy.sh --host user@server.com --path /home/user/srp --dry-run
#
# Options:
#   --host HOST         SSH host (user@hostname)
#   --path PATH         Remote project root path
#   --zip FILE          Zip file (default: latest di dist/)
#   --entry-only        Deploy hanya entry.php (tracking domain)
#   --port PORT         SSH port (default: 22)
#   --dry-run           Tampilkan command tanpa eksekusi
#   --skip-backup       Skip backup .env sebelum deploy
#   --run-check         Jalankan deploy-check.php setelah deploy
#   -h, --help          Tampilkan bantuan
# =============================================================================

set -euo pipefail

# ── Colors ───────────────────────────────────────────────────────
R='\033[0;31m'; Y='\033[1;33m'; G='\033[0;32m'
C='\033[0;36m'; W='\033[1m';    N='\033[0m'

ok()   { echo -e "  ${G}✓${N}  $*"; }
warn() { echo -e "  ${Y}⚠${N}  $*"; }
err()  { echo -e "  ${R}✗${N}  $*" >&2; }
info() { echo -e "  ${C}→${N}  $*"; }
die()  { err "$*"; echo; exit 1; }
hr()   { echo -e "\n${W}━━━  $*  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${N}"; }

# ── Defaults ─────────────────────────────────────────────────────
SSH_HOST=""
REMOTE_PATH=""
ZIP_FILE=""
SSH_PORT="22"
ENTRY_ONLY=0
DRY_RUN=0
SKIP_BACKUP=0
RUN_CHECK=0

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# ── Parse args ───────────────────────────────────────────────────
while [[ $# -gt 0 ]]; do
    case "$1" in
        --host)       SSH_HOST="$2"; shift 2 ;;
        --path)       REMOTE_PATH="$2"; shift 2 ;;
        --zip)        ZIP_FILE="$2"; shift 2 ;;
        --port)       SSH_PORT="$2"; shift 2 ;;
        --entry-only) ENTRY_ONLY=1; shift ;;
        --dry-run)    DRY_RUN=1; shift ;;
        --skip-backup) SKIP_BACKUP=1; shift ;;
        --run-check)  RUN_CHECK=1; shift ;;
        -h|--help)
            head -n 30 "$0" | tail -n +2 | sed 's/^# \?//'
            exit 0 ;;
        *) die "Unknown option: $1" ;;
    esac
done

# ── Validate ─────────────────────────────────────────────────────
[[ -z "$SSH_HOST" ]]   && die "Missing --host (e.g., user@server.com)"
[[ -z "$REMOTE_PATH" ]] && die "Missing --path (e.g., /home/user/srp)"

# ── Remote command helper ────────────────────────────────────────
rcmd() {
    if [[ $DRY_RUN -eq 1 ]]; then
        echo -e "  ${C}[DRY]${N} ssh -p $SSH_PORT $SSH_HOST \"$*\""
    else
        ssh -p "$SSH_PORT" "$SSH_HOST" "$@"
    fi
}

rscp() {
    if [[ $DRY_RUN -eq 1 ]]; then
        echo -e "  ${C}[DRY]${N} scp -P $SSH_PORT $1 ${SSH_HOST}:$2"
    else
        scp -P "$SSH_PORT" "$1" "${SSH_HOST}:$2"
    fi
}

# ══════════════════════════════════════════════════════════════════
#  Entry-only mode (tracking domain)
# ══════════════════════════════════════════════════════════════════
if [[ $ENTRY_ONLY -eq 1 ]]; then
    hr "Deploy entry.php → Tracking Domain"

    ENTRY_FILE="${ROOT}/entry.php"
    [[ ! -f "$ENTRY_FILE" ]] && die "entry.php tidak ditemukan di ${ROOT}/"

    echo
    info "Host:   ${SSH_HOST}"
    info "Remote: ${REMOTE_PATH}/entry.php"
    echo

    # Cek apakah masih localhost
    if grep -q 'localhost' "$ENTRY_FILE"; then
        warn "entry.php masih berisi 'localhost'!"
        warn "Pastikan HANTUIN_API_URL sudah diset ke domain production."
        echo
        read -rp "  Lanjutkan? [y/N]: " ans
        [[ ! "${ans}" =~ ^[Yy]$ ]] && die "Dibatalkan."
    fi

    # Backup existing
    info "Backup entry.php lama..."
    rcmd "[ -f ${REMOTE_PATH}/entry.php ] && cp ${REMOTE_PATH}/entry.php ${REMOTE_PATH}/entry.php.bak.$(date +%Y%m%d%H%M) || true"

    # Upload
    info "Upload entry.php..."
    rscp "$ENTRY_FILE" "${REMOTE_PATH}/entry.php"

    # Set permission
    rcmd "chmod 644 ${REMOTE_PATH}/entry.php"

    ok "entry.php deployed!"
    echo
    info "Test: curl -I https://tracking-domain.com/?click_id=test123"
    exit 0
fi

# ══════════════════════════════════════════════════════════════════
#  Full deploy mode
# ══════════════════════════════════════════════════════════════════
hr "SRP v2.1 — Full Deploy"

# ── Find zip ─────────────────────────────────────────────────────
if [[ -z "$ZIP_FILE" ]]; then
    ZIP_FILE=$(ls -t "${ROOT}"/dist/srp-v*.zip 2>/dev/null | head -n1)
    [[ -z "$ZIP_FILE" ]] && die "Tidak ada zip di dist/. Jalankan: php build.php"
fi

[[ ! -f "$ZIP_FILE" ]] && die "Zip tidak ditemukan: ${ZIP_FILE}"

ZIP_NAME=$(basename "$ZIP_FILE")
ZIP_SIZE=$(du -h "$ZIP_FILE" | cut -f1)

echo
info "Zip:    ${ZIP_NAME} (${ZIP_SIZE})"
info "Host:   ${SSH_HOST}:${SSH_PORT}"
info "Remote: ${REMOTE_PATH}"
echo

if [[ $DRY_RUN -eq 1 ]]; then
    warn "DRY RUN — tidak ada perubahan yang dilakukan"
    echo
fi

# ── Step 1: Pre-flight check ────────────────────────────────────
hr "Step 1: Pre-flight Check"

info "Test SSH connection..."
rcmd "echo 'SSH OK'" || die "SSH connection failed!"
ok "SSH connection"

info "Check remote directory..."
rcmd "[ -d ${REMOTE_PATH} ] || mkdir -p ${REMOTE_PATH}"
ok "Remote path exists"

info "Check PHP on remote..."
rcmd "php -v | head -1" || warn "PHP tidak ditemukan di PATH remote"

# ── Step 2: Backup .env ─────────────────────────────────────────
if [[ $SKIP_BACKUP -eq 0 ]]; then
    hr "Step 2: Backup Remote Config"

    info "Backup .env (jika ada)..."
    rcmd "[ -f ${REMOTE_PATH}/.env ] && cp ${REMOTE_PATH}/.env ${REMOTE_PATH}/.env.bak.$(date +%Y%m%d%H%M) && echo 'backed up' || echo 'no .env to backup'"
    ok ".env backup"
else
    hr "Step 2: Backup [SKIPPED]"
fi

# ── Step 3: Upload zip ──────────────────────────────────────────
hr "Step 3: Upload"

REMOTE_TMP="${REMOTE_PATH}/tmp_deploy_${ZIP_NAME}"

info "Upload ${ZIP_NAME}..."
rscp "$ZIP_FILE" "${REMOTE_TMP}"
ok "Upload complete"

# ── Step 4: Extract ──────────────────────────────────────────────
hr "Step 4: Extract & Deploy"

# Extract ke temp dir, lalu merge (preserving .env, .htaccess, vendor/)
rcmd "
    set -e
    cd ${REMOTE_PATH}
    TMPDIR=\$(mktemp -d)

    # Extract
    unzip -qo ${REMOTE_TMP} -d \$TMPDIR

    # srp/ prefix di dalam zip
    if [ -d \$TMPDIR/srp ]; then
        SRCDIR=\$TMPDIR/srp
    else
        SRCDIR=\$TMPDIR
    fi

    # Deploy files (skip .env dan vendor/)
    rsync -a --exclude='.env' --exclude='vendor/' --exclude='.htaccess' \$SRCDIR/ ${REMOTE_PATH}/

    # Bersihkan
    rm -rf \$TMPDIR ${REMOTE_TMP}
    echo 'extract-done'
"
ok "Files extracted & deployed"

# ── Step 5: Restore .htaccess (jika hilang) ──────────────────────
hr "Step 5: Post-deploy Config"

info "Check .htaccess..."
rcmd "
    if [ ! -f ${REMOTE_PATH}/public_html/.htaccess ]; then
        if [ -f ${REMOTE_PATH}/.htaccess.example ]; then
            cp ${REMOTE_PATH}/.htaccess.example ${REMOTE_PATH}/public_html/.htaccess
            echo 'htaccess-restored'
        else
            echo 'htaccess-missing'
        fi
    else
        echo 'htaccess-exists'
    fi
"

# ── Step 6: Composer install ─────────────────────────────────────
info "Composer install..."
rcmd "
    cd ${REMOTE_PATH}
    if command -v composer &>/dev/null; then
        composer install --no-dev --classmap-authoritative --no-interaction 2>&1 | tail -3
    elif [ -f /opt/cpanel/composer/bin/composer ]; then
        /opt/cpanel/composer/bin/composer install --no-dev --classmap-authoritative --no-interaction 2>&1 | tail -3
    else
        echo 'composer-not-found'
    fi
"

# ── Step 7: Permissions ─────────────────────────────────────────
hr "Step 6: Set Permissions"

rcmd "
    cd ${REMOTE_PATH}

    # Runtime directories
    mkdir -p backups logs storage/tmp

    # File permissions
    find . -type f -name '*.php' -exec chmod 644 {} \;
    [ -f .env ] && chmod 600 .env

    # Directory permissions
    chmod 750 backups logs storage cron 2>/dev/null || true

    # Cron executable
    chmod 750 cron/*.php 2>/dev/null || true

    # .htaccess protection
    for dir in src cron logs backups storage; do
        if [ -d \$dir ] && [ ! -f \$dir/.htaccess ]; then
            echo 'Require all denied' > \$dir/.htaccess
        fi
    done

    echo 'permissions-done'
"
ok "Permissions set"

# ── Step 7: Run verification ────────────────────────────────────
if [[ $RUN_CHECK -eq 1 ]]; then
    hr "Step 7: Deploy Verification"

    # Upload deploy-check.php
    CHECK_FILE="${ROOT}/deploy-check.php"
    if [[ -f "$CHECK_FILE" ]]; then
        rscp "$CHECK_FILE" "${REMOTE_PATH}/deploy-check.php"
        info "Running deploy-check.php..."
        echo
        rcmd "cd ${REMOTE_PATH} && php deploy-check.php; rm -f deploy-check.php"
    else
        warn "deploy-check.php tidak ditemukan di local"
    fi
fi

# ── Done ─────────────────────────────────────────────────────────
hr "Deploy Complete!"

echo
ok "SRP v2.1 deployed ke ${SSH_HOST}:${REMOTE_PATH}"
echo
info "Next steps:"
info "  1. Verifikasi .env production sudah benar"
info "  2. Test dashboard: https://domain.com/login"
info "  3. Test API: curl https://domain.com/api/v1/stats"
info "  4. Test tracking: curl -I https://tracking.com/?click_id=test123"
echo
info "Jika perlu rollback:"
info "  ssh ${SSH_HOST} 'cp ${REMOTE_PATH}/.env.bak.* ${REMOTE_PATH}/.env'"
echo

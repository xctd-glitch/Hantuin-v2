<?php
// phpcs:ignoreFile -- Installer entrypoint intentionally mixes bootstrap logic with inline HTML, CSS, and JS.
declare(strict_types=1);

// ── Early error handler — tangkap fatal error sebagai JSON untuk AJAX ──
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (\Throwable $e): void {
    $isAjax = (
        strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST'
        || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
        || strpos(($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false
    );
    if ($isAjax && !headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'ok' => false,
            'error' => 'PHP Error: ' . $e->getMessage(),
            'file' => basename($e->getFile()) . ':' . $e->getLine(),
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
    // Non-AJAX: tampilkan error sederhana
    http_response_code(500);
    echo '<h1>Installer Error</h1><pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
    echo '<p><small>' . htmlspecialchars(basename($e->getFile()), ENT_QUOTES, 'UTF-8') . ':' . $e->getLine() . '</small></p>';
    exit;
});

define('ROOT', dirname(__DIR__));
define('LOCK_FILE', ROOT . '/.installed');
const STATE_KEY = 'srp_installer';
const CSRF_KEY = 'srp_installer_csrf';

startInstallerSession();

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = $method === 'POST' ? normalizeAction($_POST['action'] ?? null) : '';

// POST/AJAX: handle early, sebelum kirim HTML headers (cegah 415 conflict)
if ($action !== '') {
    handleAction($action);
}

// Restore default error handler untuk rendering HTML
restore_error_handler();

$nonce = createNonce();
sendSecurityHeaders($nonce);

$state = installerState();
if (is_file(LOCK_FILE) && !$state['completed']) {
    redirect('/login.php');
}

$boot = [
    'csrf' => ensureCsrfToken(),
    'completed' => is_file(LOCK_FILE) && $state['completed'],
    'apiKey' => $state['api_key'] !== '' ? $state['api_key'] : readEnvValue('SRP_API_KEY'),
    'crons' => $state['crons'] !== [] ? $state['crons'] : cronLines(),
];
$bootJson = json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
if ($bootJson === false) {
    $bootJson = '{"csrf":"","completed":false,"apiKey":"","crons":[]}';
}

$dbHost = $state['db']['host'] !== '' ? $state['db']['host'] : fallbackEnv('SRP_DB_HOST', '127.0.0.1');
$dbPort = $state['db']['port'] !== '' ? $state['db']['port'] : fallbackEnv('SRP_DB_PORT', '3306');
$dbName = $state['db']['name'] !== '' ? $state['db']['name'] : fallbackEnv('SRP_DB_NAME', 'srp');
$dbUser = $state['db']['user'] !== '' ? $state['db']['user'] : fallbackEnv('SRP_DB_USER', '');
$adminUser = $state['admin']['user'] !== '' ? $state['admin']['user'] : fallbackEnv('SRP_ADMIN_USER', 'admin');
$apiKey = $state['api_key'] !== '' ? $state['api_key'] : readEnvValue('SRP_API_KEY');

function handleAction(string $action): void
{
    if (!hash_equals(ensureCsrfToken(), (string)($_POST['tok'] ?? ''))) {
        json(['ok' => false, 'error' => 'CSRF token tidak valid. Muat ulang installer.'], 403);
    }

    $state = installerState();
    $locked = is_file(LOCK_FILE);
    if ($locked && (!$state['completed'] || $action !== 'delete_self')) {
        json(['ok' => false, 'error' => 'Installer sudah dikunci.'], 423);
    }

    if ($action === 'check_reqs') {
        json(checkRequirements());
    }

    if ($action === 'test_db') {
        $db = validateDb($_POST);
        ensureDatabaseReady($db);
        json(['ok' => true, 'message' => sprintf('Koneksi ke `%s` berhasil.', $db['name'])]);
    }

    if ($action === 'save_db') {
        $_SESSION[STATE_KEY]['db'] = validateDb($_POST);
        json(['ok' => true]);
    }

    if ($action === 'save_admin') {
        $admin = validateAdmin($_POST);
        $hash = password_hash($admin['password'], PASSWORD_DEFAULT);
        $_SESSION[STATE_KEY]['admin'] = ['user' => $admin['user'], 'hash' => $hash];
        $_SESSION[STATE_KEY]['api_key'] = $admin['api_key'];
        $_SESSION[STATE_KEY]['app_url'] = $admin['app_url'];
        json(['ok' => true, 'api_key' => $admin['api_key']]);
    }

    if ($action === 'run_install') {
        runInstall($state);
    }

    if ($action === 'delete_self') {
        if (!$state['completed']) {
            json(['ok' => false, 'error' => 'Installer hanya boleh dihapus setelah instalasi selesai.'], 409);
        }
        if (!unlink(__FILE__)) {
            json(['ok' => false, 'error' => 'Gagal menghapus install.php. Hapus manual.'], 500);
        }
        unset($_SESSION[STATE_KEY], $_SESSION[CSRF_KEY]);
        json(['ok' => true]);
    }

    json(['ok' => false, 'error' => 'Aksi installer tidak dikenal.'], 400);
}

/**
 * @param array{
 *     db: array{host:string,port:string,name:string,user:string},
 *     admin: array{user:string,hash:string},
 *     api_key:string,
 *     app_url:string,
 *     completed:bool,
 *     crons:list<string>
 * } $state
 */
function runInstall(array $state): void
{
    if ($state['db']['host'] === '' || $state['db']['user'] === '' || $state['admin']['user'] === '' || $state['admin']['hash'] === '') {
        json(['ok' => false, 'error' => 'Step database atau admin belum lengkap.'], 409);
    }

    $db = [
        'host' => $state['db']['host'],
        'port' => (int) $state['db']['port'],
        'name' => $state['db']['name'],
        'user' => $state['db']['user'],
        'pass' => (string) ($_SESSION[STATE_KEY]['db']['pass'] ?? ''),
    ];
    $admin = [
        'user'    => $state['admin']['user'],
        'hash'    => $state['admin']['hash'],
        'api_key' => $state['api_key'],
        'app_url' => $state['app_url'],
    ];
    $log = [];
    $ok = true;

    try {
        writeEnv($db, $admin);
        $log[] = ['ok' => true, 'msg' => '.env berhasil ditulis.'];
    } catch (Throwable $e) {
        $log[] = ['ok' => false, 'msg' => '.env gagal ditulis.'];
        $ok = false;
    }

    try {
        ensureDatabaseReady($db);
        applySchema(connectPdo($db, true));
        $log[] = ['ok' => true, 'msg' => 'Schema database diterapkan via PDO tanpa multi-statement.'];
    } catch (Throwable $e) {
        $log[] = ['ok' => false, 'msg' => 'Schema database gagal diterapkan.'];
        $ok = false;
    }

    try {
        sanitizeHtaccess();
        ensureRuntimeDirs();
        hardenPermissions();
        $log[] = ['ok' => true, 'msg' => '.htaccess, direktori runtime, dan permission diperketat.'];
    } catch (Throwable $e) {
        $log[] = ['ok' => false, 'msg' => 'Hardening file system gagal.'];
        $ok = false;
    }

    // Auto-install cron jobs (non-fatal jika gagal)
    try {
        $cronScript = ROOT . '/cron/auto-setup.php';
        if (is_file($cronScript)) {
            $cronOutput = [];
            $cronCode = 0;
            exec(PHP_BINARY . ' ' . escapeshellarg($cronScript) . ' 2>&1', $cronOutput, $cronCode);
            if ($cronCode === 0) {
                $log[] = ['ok' => true, 'msg' => 'Cron jobs otomatis terpasang (purge, backup, health-check).'];
            } else {
                $log[] = ['ok' => true, 'msg' => 'Cron auto-setup dilewati (manual: php cron/auto-setup.php).'];
            }
        }
    } catch (Throwable $e) {
        // Non-fatal: cron bisa dipasang manual
        $log[] = ['ok' => true, 'msg' => 'Cron auto-setup dilewati. Pasang manual via crontab.'];
    }

    if ($ok) {
        writeAtomic(LOCK_FILE, gmdate('c'), 0600);
        session_regenerate_id(true);
        $_SESSION[STATE_KEY]['completed'] = true;
        $_SESSION[STATE_KEY]['crons'] = cronLines();
        $_SESSION[STATE_KEY]['api_key'] = $admin['api_key'];
        json(['ok' => true, 'log' => $log, 'api_key' => $admin['api_key'], 'crons' => $_SESSION[STATE_KEY]['crons']]);
    }

    json(['ok' => false, 'log' => $log, 'error' => 'Instalasi gagal.'], 500);
}

/**
 * @return array{ok:bool,items:list<array{label:string,ok:bool,note:string,warn?:bool}>}
 */
function checkRequirements(): array
{
    $items = [];
    $ok = true;
    $phpOk = PHP_VERSION_ID >= 80300;
    $items[] = ['label' => 'PHP ' . PHP_VERSION, 'ok' => $phpOk, 'note' => $phpOk ? '' : 'Wajib PHP 8.3+'];
    if (!$phpOk) {
        $ok = false;
    }
    foreach (['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl'] as $ext) {
        $loaded = extension_loaded($ext);
        $items[] = ['label' => 'ext/' . $ext, 'ok' => $loaded, 'note' => $loaded ? '' : 'Extension wajib aktif'];
        if (!$loaded) {
            $ok = false;
        }
    }
    $writable = is_writable(ROOT);
    $items[] = ['label' => 'Project root writable', 'ok' => $writable, 'note' => $writable ? '' : 'Installer butuh izin tulis'];
    if (!$writable) {
        $ok = false;
    }
    foreach (['.env.example', 'public_html/.htaccess', 'src/bootstrap.php', 'src/Config/Database.php'] as $file) {
        $exists = is_file(ROOT . '/' . $file);
        $items[] = ['label' => $file, 'ok' => $exists, 'note' => $exists ? '' : 'File wajib ada'];
        if (!$exists) {
            $ok = false;
        }
    }
    if (is_file(ROOT . '/.env')) {
        $items[] = ['label' => '.env sudah ada', 'ok' => true, 'warn' => true, 'note' => 'Akan ditimpa saat install'];
    }
    return ['ok' => $ok, 'items' => $items];
}

/**
 * @param array<string, mixed> $source
 * @return array{host:string,port:int,name:string,user:string,pass:string}
 */
function validateDb(array $source): array
{
    $host = trim(readStringField($source, 'db_host'));
    $port = filter_var($source['db_port'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
    $name = trim(readStringField($source, 'db_name'));
    $user = trim(readStringField($source, 'db_user'));
    $pass = readStringField($source, 'db_pass');

    if ($host === '' || strlen($host) > 255 || preg_match('/\A[a-zA-Z0-9.\-:\[\]]{1,255}\z/', $host) !== 1) {
        json(['ok' => false, 'error' => 'DB host tidak valid.'], 422);
    }
    if ($port === false) {
        json(['ok' => false, 'error' => 'Port database harus 1-65535.'], 422);
    }
    if ($name === '' || strlen($name) > 64 || preg_match('/\A[A-Za-z0-9_]{1,64}\z/', $name) !== 1) {
        json(['ok' => false, 'error' => 'Nama database harus alfanumerik/underscore, max 64 karakter.'], 422);
    }
    if ($user === '' || !isPrintable($user) || strlen($user) > 128) {
        json(['ok' => false, 'error' => 'Username database tidak valid.'], 422);
    }
    if (!isPrintable($pass) || strlen($pass) > 255) {
        json(['ok' => false, 'error' => 'Password database tidak valid.'], 422);
    }

    return ['host' => $host, 'port' => (int) $port, 'name' => $name, 'user' => $user, 'pass' => $pass];
}

/**
 * @param array<string, mixed> $source
 * @return array{user:string,password:string,api_key:string,app_url:string}
 */
function validateAdmin(array $source): array
{
    $user = trim(readStringField($source, 'admin_user'));
    $password = readStringField($source, 'admin_pass');
    $apiKey = strtolower(trim(readStringField($source, 'api_key')));
    $appUrl = trim(readStringField($source, 'app_url'));

    if ($user === '' || strlen($user) < 3 || strlen($user) > 64 || preg_match('/\A[A-Za-z0-9._-]{3,64}\z/', $user) !== 1) {
        json(['ok' => false, 'error' => 'Username admin tidak valid.'], 422);
    }
    if (
        strlen($password) < 8
        || strlen($password) > 255
        || !isPrintable($password)
        || preg_match('/[a-z]/', $password) !== 1
        || preg_match('/[0-9]/', $password) !== 1
    ) {
        json(['ok' => false, 'error' => 'Password admin minimal 8 karakter dan wajib mengandung huruf kecil dan angka.'], 422);
    }
    if ($apiKey === '') {
        $apiKey = bin2hex(random_bytes(32));
    }
    if (preg_match('/\A[a-f0-9]{64}\z/', $apiKey) !== 1) {
        json(['ok' => false, 'error' => 'API key harus 64 karakter hex.'], 422);
    }
    if ($appUrl !== '' && filter_var($appUrl, FILTER_VALIDATE_URL) === false) {
        json(['ok' => false, 'error' => 'Application URL tidak valid.'], 422);
    }

    return ['user' => $user, 'password' => $password, 'api_key' => $apiKey, 'app_url' => $appUrl];
}

/**
 * @param array{host:string,port:int,name:string,user:string,pass:string} $db
 */
function ensureDatabaseReady(array $db): void
{
    $pdo = connectPdo($db, false);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS ' . quoteIdentifier($db['name']) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    connectPdo($db, true)->query('SELECT 1');
}

/**
 * @param array{host:string,port:int,name:string,user:string,pass:string} $db
 */
function connectPdo(array $db, bool $withDb): PDO
{
    $dsn = 'mysql:host=' . $db['host'] . ';port=' . (string) $db['port'] . ';charset=utf8mb4';
    if ($withDb) {
        $dsn .= ';dbname=' . $db['name'];
    }
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
        $options[PDO::MYSQL_ATTR_MULTI_STATEMENTS] = false;
    }
    return new PDO($dsn, $db['user'], $db['pass'], $options);
}

function applySchema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (`id` TINYINT UNSIGNED NOT NULL, `redirect_url` VARCHAR(2048) NOT NULL DEFAULT '', `system_on` TINYINT(1) NOT NULL DEFAULT 0, `country_filter_mode` ENUM('all','whitelist','blacklist') NOT NULL DEFAULT 'all', `country_filter_list` TEXT NOT NULL, `postback_url` VARCHAR(2048) NOT NULL DEFAULT '', `postback_token` VARCHAR(64) NOT NULL DEFAULT '', `updated_at` INT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `logs` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `ts` INT UNSIGNED NOT NULL, `ip` VARCHAR(45) NOT NULL, `ua` VARCHAR(500) NOT NULL, `click_id` VARCHAR(100) DEFAULT NULL, `country_code` VARCHAR(10) DEFAULT NULL, `user_lp` VARCHAR(100) DEFAULT NULL, `decision` ENUM('A','B') NOT NULL, PRIMARY KEY (`id`), INDEX `idx_logs_ts_dec` (`ts`, `decision`), INDEX `idx_logs_cc_ts` (`country_code`, `ts`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `conversions` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `ts` INT UNSIGNED NOT NULL, `click_id` VARCHAR(100) NOT NULL DEFAULT '', `payout` DECIMAL(10,4) NOT NULL DEFAULT 0.0000, `currency` VARCHAR(10) NOT NULL DEFAULT 'USD', `status` VARCHAR(50) NOT NULL DEFAULT 'approved', `country` VARCHAR(10) DEFAULT NULL, `ip` VARCHAR(45) NOT NULL DEFAULT '', `raw` TEXT NOT NULL, PRIMARY KEY (`id`), INDEX `idx_conv_ts` (`ts`), INDEX `idx_conv_click_id` (`click_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (!columnExists($pdo, 'settings', 'postback_url')) {
        $pdo->exec("ALTER TABLE `settings` ADD COLUMN `postback_url` VARCHAR(2048) NOT NULL DEFAULT '' AFTER `country_filter_list`");
    }
    if (!columnExists($pdo, 'settings', 'postback_token')) {
        $pdo->exec("ALTER TABLE `settings` ADD COLUMN `postback_token` VARCHAR(64) NOT NULL DEFAULT '' AFTER `postback_url`");
    }
    if (!columnExists($pdo, 'conversions', 'country')) {
        $pdo->exec("ALTER TABLE `conversions` ADD COLUMN `country` VARCHAR(10) DEFAULT NULL AFTER `status`");
    }
    if (!indexExists($pdo, 'logs', 'idx_logs_ts_dec')) {
        $pdo->exec('ALTER TABLE `logs` ADD INDEX `idx_logs_ts_dec` (`ts`, `decision`)');
    }
    if (indexExists($pdo, 'logs', 'idx_logs_ts')) {
        $pdo->exec('ALTER TABLE `logs` DROP INDEX `idx_logs_ts`');
    }
    $stmt = $pdo->prepare("INSERT INTO `settings` (`id`,`redirect_url`,`system_on`,`country_filter_mode`,`country_filter_list`,`postback_url`,`postback_token`,`updated_at`) VALUES (:id,:redirect_url,:system_on,:country_filter_mode,:country_filter_list,:postback_url,:postback_token,UNIX_TIMESTAMP()) ON DUPLICATE KEY UPDATE `id`=`id`");
    $stmt->execute([
        ':id' => 1,
        ':redirect_url' => '',
        ':system_on' => 0,
        ':country_filter_mode' => 'all',
        ':country_filter_list' => '',
        ':postback_url' => '',
        ':postback_token' => '',
    ]);
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name');
    $stmt->execute([':table_name' => $table, ':column_name' => $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function indexExists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name');
    $stmt->execute([':table_name' => $table, ':index_name' => $index]);
    return (int) $stmt->fetchColumn() > 0;
}

/**
 * @param array{host:string,port:int,name:string,user:string,pass:string} $db
 * @param array{user:string,hash:string,api_key:string,app_url:string} $admin
 */
function writeEnv(array $db, array $admin): void
{
    $lines = [
        '# ===================================================================',
        '# Hantuin-v2 Environment Configuration',
        '# Last updated: ' . date('Y-m-d H:i:s'),
        '# ===================================================================',
        '',
        '# ── Application ────────────────────────────────────────────────────',
        '# Kosongkan atau "auto" untuk auto-detect dari request',
        'APP_URL=' . ($admin['app_url'] !== '' ? envValue($admin['app_url']) : 'auto'),
        'APP_ENV=production',
        'APP_DEBUG=false',
        'SRP_ENV=production',
        'SRP_ENV_FILE=',
        '',
        '# ── Database ───────────────────────────────────────────────────────',
        'SRP_DB_HOST=' . envValue($db['host']),
        'SRP_DB_PORT=' . (string) $db['port'],
        'SRP_DB_NAME=' . envValue($db['name']),
        'SRP_DB_USER=' . envValue($db['user']),
        'SRP_DB_PASS=' . envValue($db['pass']),
        'SRP_DB_SOCKET=',
        '',
        '# ── API Keys ───────────────────────────────────────────────────────',
        'SRP_API_KEY=' . envValue($admin['api_key']),
        '',
        '# Remote Decision Server (S2S)',
        'SRP_REMOTE_DECISION_URL=',
        'SRP_REMOTE_API_KEY=',
        '',
        '# ── API Client Tuning ──────────────────────────────────────────────',
        'SRP_API_TIMEOUT=8',
        'SRP_API_CONNECT_TIMEOUT=3',
        'SRP_API_FAILURE_COOLDOWN=30',
        'SRP_API_MAX_RETRIES=0',
        'SRP_API_BACKOFF_BASE_MS=250',
        'SRP_API_BACKOFF_MAX_MS=1500',
        'SRP_API_RESPONSE_CACHE_SECONDS=3',
        'SRP_API_INFLIGHT_WAIT_MS=300',
        '',
        '# ── VPN Check ─────────────────────────────────────────────────────',
        'SRP_VPN_CHECK_ENABLED=1',
        '',
        '# ── Rate Limiting ──────────────────────────────────────────────────',
        'SRP_PUBLIC_API_RATE_WINDOW=60',
        'SRP_PUBLIC_API_RATE_MAX=1000',
        'SRP_PUBLIC_API_RATE_HEAVY_MAX=30',
        'RATE_LIMIT_ATTEMPTS=5',
        'RATE_LIMIT_WINDOW=900',
        '',
        '# ── Admin Credentials ──────────────────────────────────────────────',
        'SRP_ADMIN_USER=' . envValue($admin['user']),
        'SRP_ADMIN_PASSWORD_HASH=' . envValue($admin['hash']),
        'SRP_ADMIN_PASSWORD=',
        '',
        'SRP_USER_USER=',
        'SRP_USER_PASSWORD_HASH=',
        'SRP_USER_PASSWORD=',
        '',
        '# ── Security ───────────────────────────────────────────────────────',
        '# Cloudflare IPv4 + IPv6 ranges (https://www.cloudflare.com/ips/)',
        'SRP_TRUSTED_PROXIES=173.245.48.0/20,103.21.244.0/22,103.22.200.0/22,103.31.4.0/22,141.101.64.0/18,108.162.192.0/18,190.93.240.0/20,188.114.96.0/20,197.234.240.0/22,198.41.128.0/17,162.158.0.0/15,104.16.0.0/13,104.24.0.0/14,172.64.0.0/13,131.0.72.0/22,2400:cb00::/32,2606:4700::/32,2803:f800::/32,2405:b500::/32,2405:8100::/32,2a06:98c0::/29,2c0f:f248::/32',
        'SRP_FORCE_SECURE_COOKIES=true',
        '',
        '# ── Cache ──────────────────────────────────────────────────────────',
        '# auto = Redis > Memcached > APCu > none',
        'CACHE_DRIVER=auto',
        'CACHE_PREFIX=srp_',
        'REDIS_HOST=127.0.0.1',
        'REDIS_PORT=6379',
        'REDIS_PASSWORD=',
        'REDIS_DB=0',
        'MEMCACHED_HOST=127.0.0.1',
        'MEMCACHED_PORT=11211',
        '',
        '# ── Session ────────────────────────────────────────────────────────',
        'SESSION_LIFETIME=3600',
    ];
    writeAtomic(ROOT . '/.env', implode(PHP_EOL, $lines), 0600);
}

function sanitizeHtaccess(): void
{
    $target = ROOT . '/public_html/.htaccess';
    $content = file_get_contents($target);
    if ($content === false) {
        throw new PDOException('Gagal membaca .htaccess publik.');
    }
    $content = preg_replace('/<IfModule\s+mod_env\.c>[\s\S]*?<\/IfModule>\s*/i', '', $content);
    $content = preg_replace('/^\s*Header\s+always\s+set\s+X-XSS-Protection.*$\R?/mi', '', (string) $content);
    if ($content === null) {
        throw new PDOException('Gagal membersihkan .htaccess.');
    }
    writeAtomic($target, trim($content), 0644);
}

function ensureRuntimeDirs(): void
{
    foreach ([ROOT . '/logs', ROOT . '/backups', ROOT . '/storage', ROOT . '/storage/tmp'] as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new PDOException('Gagal membuat direktori runtime.');
        }
    }
    $deny = "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>";
    foreach (['cron', 'src', 'logs', 'backups', 'storage'] as $dir) {
        if (is_dir(ROOT . '/' . $dir)) {
            writeAtomic(ROOT . '/' . $dir . '/.htaccess', $deny, 0644);
        }
    }
}

function hardenPermissions(): void
{
    foreach ([ROOT . '/src', ROOT . '/public_html'] as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file instanceof SplFileInfo) {
                continue;
            }
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                chmod($file->getPathname(), 0644);
            }
        }
    }
    foreach (glob(ROOT . '/cron/*.php') ?: [] as $file) {
        chmod($file, 0750);
    }
    foreach ([ROOT . '/.env', ROOT . '/schema.sql', ROOT . '/entry.php'] as $file) {
        if (is_file($file)) {
            chmod($file, $file === ROOT . '/.env' ? 0600 : 0644);
        }
    }
}

function writeAtomic(string $path, string $content, int $mode): void
{
    $tmp = $path . '.tmp-' . bin2hex(random_bytes(6));
    if (file_put_contents($tmp, $content . PHP_EOL, LOCK_EX) === false) {
        throw new PDOException('Gagal menulis file sementara.');
    }
    if (is_file($path) && !unlink($path)) {
        @unlink($tmp);
        throw new PDOException('Gagal menimpa file target.');
    }
    if (!rename($tmp, $path)) {
        @unlink($tmp);
        throw new PDOException('Gagal memindahkan file sementara.');
    }
    if (!chmod($path, $mode)) {
        throw new PDOException('Gagal mengatur permission file.');
    }
}

function quoteIdentifier(string $value): string
{
    if (preg_match('/\A[A-Za-z0-9_]{1,64}\z/', $value) !== 1) {
        throw new PDOException('Identifier database tidak valid.');
    }
    return '`' . $value . '`';
}

function envValue(string $value): string
{
    if (!isPrintable($value)) {
        throw new PDOException('Nilai env tidak valid.');
    }
    return trim($value);
}

function isPrintable(string $value): bool
{
    return preg_match('/\A[^\x00-\x1F\x7F]*\z/u', $value) === 1;
}

/**
 * @param array<string, mixed> $source
 */
function readStringField(array $source, string $key): string
{
    $value = $source[$key] ?? '';
    if (!is_scalar($value) && $value !== null) {
        json(['ok' => false, 'error' => 'Input installer tidak valid.'], 422);
    }
    return (string) $value;
}

/**
 * @return array{
 *     db: array{host:string,port:string,name:string,user:string},
 *     admin: array{user:string,hash:string},
 *     api_key:string,
 *     app_url:string,
 *     completed:bool,
 *     crons:list<string>
 * }
 */
function installerState(): array
{
    $stored = $_SESSION[STATE_KEY] ?? [];
    return [
        'db' => [
            'host' => (string) ($stored['db']['host'] ?? ''),
            'port' => (string) ($stored['db']['port'] ?? ''),
            'name' => (string) ($stored['db']['name'] ?? ''),
            'user' => (string) ($stored['db']['user'] ?? ''),
        ],
        'admin' => [
            'user' => (string) ($stored['admin']['user'] ?? ''),
            'hash' => (string) ($stored['admin']['hash'] ?? ''),
        ],
        'api_key' => (string) ($stored['api_key'] ?? ''),
        'app_url' => (string) ($stored['app_url'] ?? ''),
        'completed' => (bool) ($stored['completed'] ?? false),
        'crons' => is_array($stored['crons'] ?? null) ? array_values($stored['crons']) : [],
    ];
}

/**
 * @return list<string>
 */
function cronLines(): array
{
    $php = cronQuote(PHP_BINARY);
    $root = cronQuote(ROOT);
    return [
        '0 3 * * * ' . $php . ' ' . $root . '/cron/purge.php --log-days=7 --backup-days=30 >> ' . $root . '/logs/purge.log 2>&1',
        '0 1 * * * ' . $php . ' ' . $root . '/cron/backup.php 30 >> ' . $root . '/logs/backup.log 2>&1',
        '*/15 * * * * ' . $php . ' ' . $root . '/cron/health-check.php >> ' . $root . '/logs/health.log 2>&1',
    ];
}

function cronQuote(string $value): string
{
    return preg_match('/[\s\'"`$\\\\]/', $value) === 1 ? "'" . str_replace("'", "'\"'\"'", $value) . "'" : $value;
}

function readEnvValue(string $key): string
{
    $path = ROOT . '/.env';
    if (!is_readable($path)) {
        return '';
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return '';
    }
    foreach ($lines as $line) {
        if ($line === '' || strpos(trim($line), '#') === 0) {
            continue;
        }
        [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
        if (trim($k) === $key) {
            return trim($v);
        }
    }
    return '';
}

function fallbackEnv(string $key, string $default): string
{
    $value = readEnvValue($key);
    return $value === '' ? $default : $value;
}

function ensureCsrfToken(): string
{
    if (!isset($_SESSION[CSRF_KEY]) || !is_string($_SESSION[CSRF_KEY])) {
        $_SESSION[CSRF_KEY] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_KEY];
}

function startInstallerSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('srp_installer');
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => isSecure(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    } else {
        session_set_cookie_params(0, '/; SameSite=Strict', '', isSecure(), true);
    }
    session_start();
    if (!isset($_SESSION[STATE_KEY]['boot'])) {
        session_regenerate_id(true);
        $_SESSION[STATE_KEY]['boot'] = true;
    }
}

function sendSecurityHeaders(string $nonce): void
{
    if (function_exists('header_remove')) {
        header_remove('X-Powered-By');
    }
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: accelerometer=(), camera=(), geolocation=(), gyroscope=(), microphone=(), payment=(), usb=()');
    header("Content-Security-Policy: default-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'; connect-src 'self'; img-src 'self' data:; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'unsafe-inline'");
    if (isSecure()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function isSecure(): bool
{
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    $proto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    return $https === 'on' || $https === '1' || $proto === 'https' || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

function createNonce(): string
{
    try {
        return rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    } catch (Throwable $e) {
        return 'static-installer-nonce';
    }
}

/** @param mixed $value */
function normalizeAction($value): string
{
    if (!is_scalar($value) && $value !== null) {
        return '';
    }
    $action = trim((string) $value);
    return preg_match('/\A[a-z_]{1,32}\z/', $action) === 1 ? $action : '';
}

function redirect(string $to): void
{
    header('Location: ' . $to, true, 302);
    exit;
}

/**
 * @param array<string, mixed> $payload
 */
function json(array $payload, int $status = 200): void
{
    // Bersihkan buffer output sebelumnya (jika ada HTML headers terkirim)
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    // Hapus header HTML jika sudah terkirim sebelumnya
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-Content-Type-Options: nosniff');
    }
    $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);
    echo $encoded === false ? '{"ok":false,"error":"JSON encoding gagal."}' : $encoded;
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Hantuin-v2 Installer</title>
<style nonce="<?= h($nonce) ?>">
/* ── CSS Variables (match dashboard) ─────────────── */
:root {
    --ring-color:             hsl(240 5.9% 10%);
    --muted-foreground:       hsl(240 4% 44%);
    --border-color:           hsl(240 6% 89%);
    --primary:                hsl(240 5.9% 10%);
    --primary-foreground:     hsl(0 0% 98%);
    --secondary:              hsl(240 5% 96%);
    --secondary-foreground:   hsl(240 5.9% 10%);
    --destructive:            hsl(0 84.2% 60.2%);
    --destructive-foreground: hsl(0 0% 98%);
    --page-bg:                hsl(220 18% 95.5%);
    --card-bg:                hsl(0 0% 100%);
    --card-shadow:            0 1px 3px hsla(240,6%,10%,.07), 0 1px 2px hsla(240,6%,10%,.05);
    --radius:                 .375rem;
    --success-bg:             hsl(143 76% 96%);
    --success-fg:             hsl(143 64% 24%);
    --success-border:         hsl(143 56% 82%);
    --error-bg:               hsl(0 86% 97%);
    --error-fg:               hsl(0 72% 51%);
    --error-border:           hsl(0 60% 88%);
    --warn-bg:                hsl(48 96% 96%);
    --warn-fg:                hsl(32 95% 44%);
    --warn-border:            hsl(48 80% 82%);
    --info-bg:                hsl(214 95% 96%);
    --info-fg:                hsl(221 83% 53%);
    --info-border:            hsl(214 80% 88%);
}

/* ── Reset ───────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }

/* ── Base Typography (match dashboard Inter) ─────── */
html {
    font-size: 16px;
    scroll-behavior: smooth;
    -webkit-text-size-adjust: 100%;
}
body {
    margin: 0;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
    font-feature-settings: "cv02", "cv03", "cv04", "cv11";
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    text-rendering: optimizeLegibility;
    line-height: 1.5;
    letter-spacing: -.012em;
    background-color: var(--page-bg);
    color: hsl(240 10% 3.9%);
    min-height: 100vh;
}

/* ── Scrollbars ──────────────────────────────────── */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: hsl(240 5% 82%); border-radius: 6px; }
::-webkit-scrollbar-thumb:hover { background: hsl(240 4% 62%); }

/* ── Layout ──────────────────────────────────────── */
.installer-wrapper {
    max-width: 720px;
    margin: 0 auto;
    padding: 1.5rem 1.25rem 3rem;
}

/* ── Header bar (match dashboard sticky nav) ─────── */
.installer-topbar {
    position: sticky;
    top: 0;
    z-index: 50;
    width: 100%;
    border-bottom: 1px solid var(--border-color);
    background-color: hsla(0 0% 100% / .95);
    backdrop-filter: blur(8px);
}
.installer-topbar-inner {
    display: flex;
    height: 3rem;
    max-width: 720px;
    margin: 0 auto;
    align-items: center;
    padding: 0 1.25rem;
    gap: .625rem;
}
.installer-topbar .logo-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    border-radius: var(--radius);
    background: var(--primary);
    color: var(--primary-foreground);
    flex-shrink: 0;
}
.installer-topbar .logo-icon svg { width: 1rem; height: 1rem; }
.installer-topbar .title-group { display: flex; flex-direction: column; line-height: 1.2; }
.installer-topbar .title-text { font-size: .8125rem; font-weight: 600; letter-spacing: -.01em; }
.installer-topbar .sub-text { font-size: .6875rem; color: var(--muted-foreground); }

/* ── Card ────────────────────────────────────────── */
.card {
    border-radius: var(--radius);
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
    color: hsl(240 10% 3.9%);
    box-shadow: var(--card-shadow);
}
.card-body { padding: 1.25rem; }
.card-header {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--border-color);
}

/* ── Step pills (stepper) ────────────────────────── */
.pillbar {
    display: flex;
    gap: .375rem;
    margin-bottom: 1.25rem;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.pill {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .375rem;
    padding: .5rem .625rem;
    border-radius: var(--radius);
    border: 1px solid var(--border-color);
    text-align: center;
    color: var(--muted-foreground);
    font-size: .75rem;
    font-weight: 500;
    white-space: nowrap;
    transition: all .2s ease;
    cursor: default;
}
.pill .pill-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.25rem;
    height: 1.25rem;
    border-radius: 50%;
    border: 1px solid var(--border-color);
    font-size: .625rem;
    font-weight: 600;
    flex-shrink: 0;
    transition: all .2s ease;
}
.pill.active {
    background-color: var(--primary);
    color: var(--primary-foreground);
    border-color: var(--primary);
}
.pill.active .pill-num {
    background: hsla(0 0% 100% / .2);
    border-color: hsla(0 0% 100% / .3);
    color: var(--primary-foreground);
}
.pill.done {
    background-color: var(--success-bg);
    color: var(--success-fg);
    border-color: var(--success-border);
}
.pill.done .pill-num {
    background: var(--success-fg);
    border-color: var(--success-fg);
    color: #fff;
}

/* ── Step sections ───────────────────────────────── */
.step { display: none; }
.step.active { display: block; }
.step h2 {
    font-size: .9375rem;
    font-weight: 600;
    letter-spacing: -.01em;
    margin: 0 0 1rem;
}
.step-desc {
    font-size: .8125rem;
    color: var(--muted-foreground);
    margin: -.5rem 0 1rem;
}

/* ── Form fields ─────────────────────────────────── */
.field { margin-bottom: .875rem; }
.field label {
    display: block;
    font-size: .75rem;
    font-weight: 500;
    color: hsl(240 10% 3.9%);
    margin-bottom: .375rem;
    letter-spacing: -.005em;
}
.field input {
    display: flex;
    height: 2.125rem;
    width: 100%;
    border-radius: calc(var(--radius) - 1px);
    border: 1px solid var(--border-color);
    background-color: transparent;
    padding: .25rem .7rem;
    font-size: .8125rem;
    font-family: inherit;
    line-height: 1.5;
    color: hsl(240 10% 3.9%);
    transition: border-color .15s ease, box-shadow .15s ease;
}
.field input::placeholder { color: var(--muted-foreground); opacity: .75; }
.field input:focus,
.field input:focus-visible {
    outline: none;
    border-color: hsl(240 5.9% 36%);
    box-shadow: 0 0 0 3px hsla(240, 5.9%, 10%, .10);
}
.field input:disabled { cursor: not-allowed; opacity: .5; }

.row { display: grid; grid-template-columns: 1fr 1fr; gap: .875rem; }

/* ── Buttons (match dashboard) ───────────────────── */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .375rem;
    white-space: nowrap;
    border-radius: var(--radius);
    border: 1px solid transparent;
    font-size: .8125rem;
    font-weight: 500;
    font-family: inherit;
    line-height: 1;
    letter-spacing: -.005em;
    cursor: pointer;
    user-select: none;
    padding: .35rem 1rem;
    height: 2.125rem;
    text-decoration: none;
    transition: background-color .15s ease, color .15s ease, border-color .15s ease, box-shadow .15s ease, opacity .15s ease;
    outline: 2px solid transparent;
    outline-offset: 2px;
}
.btn:focus-visible { box-shadow: 0 0 0 2px var(--ring-color); }
.btn:disabled { pointer-events: none; opacity: .45; }
.btn svg { width: .875rem; height: .875rem; flex-shrink: 0; }

.primary {
    background-color: var(--primary);
    color: var(--primary-foreground);
}
.primary:hover { background-color: hsl(240 5.9% 16%); }
.primary:active { background-color: hsl(240 5.9% 7%); }

.secondary {
    background-color: var(--secondary);
    color: var(--secondary-foreground);
    border-color: var(--border-color);
}
.secondary:hover { background-color: hsl(240 5% 91%); }

.danger {
    background-color: var(--destructive);
    color: var(--destructive-foreground);
}
.danger:hover { background-color: hsl(0 84.2% 54%); }

.ghost {
    background-color: transparent;
    color: var(--muted-foreground);
}
.ghost:hover { background-color: var(--secondary); color: hsl(240 5.9% 10%); }

.link {
    background: transparent;
    color: var(--muted-foreground);
    padding: 0;
    height: auto;
    border: 0;
    font-size: .75rem;
}
.link:hover { color: hsl(240 5.9% 10%); text-decoration: underline; }

.btn-sm { height: 1.875rem; padding: .25rem .625rem; font-size: .75rem; border-radius: calc(var(--radius) - 1px); }

/* ── Actions bar ─────────────────────────────────── */
.actions {
    display: flex;
    gap: .75rem;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    margin-top: 1.25rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
}
.actions > div { display: flex; gap: .5rem; }

/* ── Status messages ─────────────────────────────── */
.status {
    display: none;
    padding: .625rem .75rem;
    border-radius: calc(var(--radius) - 1px);
    margin-top: .75rem;
    font-weight: 500;
    font-size: .8125rem;
    border: 1px solid transparent;
}
.status.show { display: flex; align-items: center; gap: .5rem; }
.status::before { font-size: .875rem; flex-shrink: 0; }
.ok { background: var(--success-bg); color: var(--success-fg); border-color: var(--success-border); }
.ok::before { content: "\2713"; }
.err { background: var(--error-bg); color: var(--error-fg); border-color: var(--error-border); }
.err::before { content: "\2717"; }
.load { background: var(--info-bg); color: var(--info-fg); border-color: var(--info-border); }
.load::before { content: "\25CB"; animation: pulse-dot 1.2s ease-in-out infinite; }
@keyframes pulse-dot { 0%,100% { opacity: .4; } 50% { opacity: 1; } }

/* ── Requirement items ───────────────────────────── */
.item {
    display: flex;
    align-items: flex-start;
    gap: .625rem;
    padding: .625rem .75rem;
    border-radius: calc(var(--radius) - 1px);
    border: 1px solid var(--border-color);
    margin-bottom: .5rem;
    background: var(--card-bg);
    font-size: .8125rem;
}
.item::before { font-size: .8125rem; flex-shrink: 0; margin-top: 1px; }
.item.ok { background: var(--success-bg); border-color: var(--success-border); }
.item.ok::before { content: "\2713"; color: var(--success-fg); }
.item.err { background: var(--error-bg); border-color: var(--error-border); }
.item.err::before { content: "\2717"; color: var(--error-fg); }
.item.warn { background: var(--warn-bg); border-color: var(--warn-border); }
.item.warn::before { content: "\26A0"; color: var(--warn-fg); }
.item strong { font-weight: 600; }
.item div { font-size: .75rem; color: var(--muted-foreground); margin-top: .125rem; }

/* ── Install log terminal ────────────────────────── */
.log {
    background: hsl(240 10% 3.9%);
    color: hsl(240 5% 80%);
    border-radius: var(--radius);
    border: 1px solid hsl(240 6% 20%);
    padding: 1rem;
    min-height: 200px;
    max-height: 320px;
    overflow-y: auto;
    font: .75rem/1.7 'SF Mono', 'Cascadia Code', 'Fira Code', Consolas, monospace;
    white-space: pre-wrap;
    word-break: break-all;
    scrollbar-width: thin;
}
.log::-webkit-scrollbar { width: 4px; }
.log::-webkit-scrollbar-thumb { background: hsl(240 5% 30%); border-radius: 4px; }

/* ── Final summary items ─────────────────────────── */
.summary-item {
    padding: .875rem 1rem;
    border-radius: var(--radius);
    border: 1px solid var(--border-color);
    margin-bottom: .75rem;
    background: var(--card-bg);
}
.summary-item strong {
    display: block;
    font-size: .75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .03em;
    color: var(--muted-foreground);
    margin-bottom: .5rem;
}
.summary-item pre {
    margin: 0;
    padding: .625rem .75rem;
    background: hsl(240 5% 96%);
    border-radius: calc(var(--radius) - 1px);
    font: .75rem/1.6 'SF Mono', Consolas, monospace;
    white-space: pre-wrap;
    word-break: break-all;
    overflow-x: auto;
    color: hsl(240 10% 3.9%);
}
.summary-item ol {
    margin: 0;
    padding-left: 1.25rem;
    font-size: .8125rem;
    line-height: 1.7;
    color: hsl(240 10% 20%);
}
.summary-item ol code {
    font-size: .75rem;
    padding: .1rem .35rem;
    background: hsl(240 5% 96%);
    border-radius: 3px;
    font-family: 'SF Mono', Consolas, monospace;
}

/* ── Helper note text ────────────────────────────── */
.note-text {
    font-size: .75rem;
    color: var(--muted-foreground);
    margin: .25rem 0 0;
}

/* ── Hidden ──────────────────────────────────────── */
.hidden { display: none !important; }

/* ── Responsive ──────────────────────────────────── */
@media (max-width: 639px) {
    .installer-wrapper { padding: 1rem .75rem 2rem; }
    .row { grid-template-columns: 1fr; }
    .pillbar { gap: .25rem; }
    .pill { padding: .375rem .375rem; font-size: .6875rem; }
    .pill .pill-label { display: none; }
    .pill .pill-num { width: 1.5rem; height: 1.5rem; font-size: .6875rem; }
    .field input { height: 2.5rem; font-size: .875rem; }
    .btn { height: 2.5rem; }
    .btn-sm { height: 2.125rem; }
    .actions { gap: .5rem; }
}
</style>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<!-- ── Top bar ──────────────────────────────────── -->
<nav class="installer-topbar">
    <div class="installer-topbar-inner">
        <div class="logo-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>
            </svg>
        </div>
        <div class="title-group">
            <span class="title-text">Hantuin-v2 Installer</span>
            <span class="sub-text">Secure setup &mdash; CSRF + CSP nonce + PDO safe</span>
        </div>
    </div>
</nav>

<!-- ── Main wrapper ─────────────────────────────── -->
<div class="installer-wrapper">

<!-- ── Step card ─────────────────────────────────── -->
<div class="card">
<div class="card-body">
<div class="pillbar">
    <div class="pill" data-pill="1"><span class="pill-num">1</span><span class="pill-label">Requirements</span></div>
    <div class="pill" data-pill="2"><span class="pill-num">2</span><span class="pill-label">Database</span></div>
    <div class="pill" data-pill="3"><span class="pill-num">3</span><span class="pill-label">Admin</span></div>
    <div class="pill" data-pill="4"><span class="pill-num">4</span><span class="pill-label">Install</span></div>
    <div class="pill" data-pill="5"><span class="pill-num">5</span><span class="pill-label">Final</span></div>
</div>

<!-- ── Step 1: Requirements ──────────────────────── -->
<div class="step" id="step-1">
<h2>System Requirements</h2>
<p class="step-desc">Memastikan server memenuhi syarat minimum.</p>
<div id="req-list"></div>
<div class="actions">
<span id="req-note" class="note-text">Semua requirement wajib harus lolos.</span>
<div>
<button class="btn secondary btn-sm" type="button" id="req-refresh">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg>
    Periksa ulang
</button>
<button class="btn primary btn-sm" type="button" id="req-next" disabled>
    Lanjut
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
</button>
</div>
</div>
</div>

<!-- ── Step 2: Database ──────────────────────────── -->
<div class="step" id="step-2">
<h2>Database Configuration</h2>
<p class="step-desc">Koneksi MySQL/MariaDB untuk menyimpan data routing.</p>
<div class="row">
<div class="field"><label for="db_host">DB Host</label><input id="db_host" type="text" value="<?= h($dbHost) ?>" maxlength="255" placeholder="localhost"></div>
<div class="field"><label for="db_port">Port</label><input id="db_port" type="number" value="<?= h($dbPort) ?>" min="1" max="65535" placeholder="3306"></div>
<div class="field"><label for="db_name">Database Name</label><input id="db_name" type="text" value="<?= h($dbName) ?>" maxlength="64" placeholder="srp"></div>
<div class="field"><label for="db_user">Database User</label><input id="db_user" type="text" value="<?= h($dbUser) ?>" maxlength="128" placeholder="root"></div>
</div>
<div class="field"><label for="db_pass">Database Password</label><input id="db_pass" type="password" maxlength="255" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"></div>
<div class="status" id="db-status"></div>
<div class="actions">
<button class="btn link" type="button" data-step="1">&larr; Kembali</button>
<div>
<button class="btn secondary btn-sm" type="button" id="db-test">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
    Tes koneksi
</button>
<button class="btn primary btn-sm" type="button" id="db-save">
    Simpan &amp; lanjut
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
</button>
</div>
</div>
</div>

<!-- ── Step 3: Admin ─────────────────────────────── -->
<div class="step" id="step-3">
<h2>Admin &amp; API Configuration</h2>
<p class="step-desc">Kredensial dashboard dan kunci API.</p>
<div class="field"><label for="admin_user">Username Admin</label><input id="admin_user" type="text" value="<?= h($adminUser) ?>" maxlength="64" placeholder="admin"></div>
<div class="row">
<div class="field"><label for="admin_pass">Password Admin</label><input id="admin_pass" type="password" maxlength="255" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"></div>
<div class="field"><label for="admin_pass2">Konfirmasi Password</label><input id="admin_pass2" type="password" maxlength="255" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"></div>
</div>
<p class="note-text" id="pw-note">Minimal 8 karakter, wajib mengandung huruf kecil dan angka.</p>
<div class="field" style="margin-top:.875rem"><label for="api_key">API Key <span style="font-weight:400;color:var(--muted-foreground)">(opsional, auto-generate jika kosong)</span></label><input id="api_key" type="text" value="<?= h($apiKey) ?>" maxlength="64" placeholder="64 karakter hex" style="font-family:'SF Mono',Consolas,monospace;font-size:.75rem;letter-spacing:.02em"></div>
<div class="field"><label for="app_url">Application URL</label><input id="app_url" type="url" placeholder="https://domain.com" maxlength="255"></div>
<div class="status" id="admin-status"></div>
<div class="actions">
<button class="btn link" type="button" data-step="2">&larr; Kembali</button>
<div>
<button class="btn secondary btn-sm" type="button" id="api-generate">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M12 12h.01"/><path d="M17 12h.01"/><path d="M7 12h.01"/></svg>
    Generate key
</button>
<button class="btn primary btn-sm" type="button" id="admin-save">
    Simpan &amp; lanjut
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
</button>
</div>
</div>
</div>

<!-- ── Step 4: Install ───────────────────────────── -->
<div class="step" id="step-4">
<h2>Run Installation</h2>
<p class="step-desc">Menulis .env, membuat schema database, dan hardening file system.</p>
<pre class="log" id="install-log">$ srp installer --secure</pre>
<div class="status show load" id="install-status">Belum dijalankan.</div>
<div class="actions">
<button class="btn link" type="button" data-step="3">&larr; Kembali</button>
<div>
<button class="btn primary" type="button" id="install-run">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
    Jalankan instalasi
</button>
<button class="btn secondary hidden" type="button" id="summary-btn">
    Lihat ringkasan
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
</button>
</div>
</div>
</div>

<!-- ── Step 5: Final ─────────────────────────────── -->
<div class="step" id="step-5">
<h2>Installation Complete</h2>
<p class="step-desc">Simpan informasi berikut dan hapus installer.</p>
<div class="summary-item"><strong>API Key</strong><pre id="final-api"></pre></div>
<div class="summary-item"><strong>Cron Jobs</strong><pre id="final-crons"></pre></div>
<div class="summary-item">
    <strong>Langkah Selanjutnya</strong>
    <ol>
        <li>Pastikan document root mengarah ke <code>public_html/</code></li>
        <li>Login ke <code>/login.php</code> dan konfigurasi routing</li>
        <li>Pasang cron jobs di atas via cPanel atau <code>crontab -e</code></li>
        <li>Hapus <code>install.php</code> dari server sekarang juga</li>
    </ol>
</div>
<div class="status" id="delete-status"></div>
<div class="actions">
<button class="btn danger" type="button" id="delete-btn">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
    Hapus install.php
</button>
<a class="btn primary" href="/login.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
    Masuk dashboard
</a>
</div>
</div>

</div><!-- card-body -->
</div><!-- card -->

</div><!-- installer-wrapper -->
<script nonce="<?= h($nonce) ?>">
var BOOT = <?= $bootJson ?>;
var currentStep = BOOT.completed ? 5 : 1;
var installState = {apiKey: BOOT.apiKey || '', crons: Array.isArray(BOOT.crons) ? BOOT.crons : [], running: false};

function showStep(step) {
    var i;
    var steps = document.querySelectorAll('.step');
    var pills = document.querySelectorAll('.pill');
    for (i = 0; i < steps.length; i += 1) { steps[i].classList.remove('active'); }
    for (i = 0; i < pills.length; i += 1) {
        pills[i].classList.remove('active', 'done');
        if ((i + 1) < step) { pills[i].classList.add('done'); }
        if ((i + 1) === step) { pills[i].classList.add('active'); }
    }
    document.getElementById('step-' + String(step)).classList.add('active');
    currentStep = step;
    if (step === 5) {
        document.getElementById('final-api').textContent = installState.apiKey || '(lihat .env)';
        document.getElementById('final-crons').textContent = installState.crons.join('\n');
    }
}

async function callApi(action, payload) {
    var fd = new FormData();
    var key;
    fd.append('action', action);
    fd.append('tok', BOOT.csrf || '');
    for (key in payload) {
        if (Object.prototype.hasOwnProperty.call(payload, key)) {
            fd.append(key, payload[key]);
        }
    }
    try {
        var response = await fetch(window.location.pathname, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        if (!response.ok && response.status === 415) {
            return {ok: false, error: 'Server menolak request (415). Cek konfigurasi proxy/CDN.'};
        }
        var text = await response.text();
        try {
            return JSON.parse(text);
        } catch (parseErr) {
            return {ok: false, error: 'Response bukan JSON. Server mungkin mengembalikan error page. Status: ' + response.status};
        }
    } catch (error) {
        return {ok: false, error: 'Request installer gagal: ' + (error.message || 'network error')};
    }
}

function setStatus(id, msg, type) {
    var el = document.getElementById(id);
    el.className = 'status show ' + (type === 'ok' ? 'ok' : type === 'load' ? 'load' : 'err');
    el.textContent = msg;
}

async function checkReqs() {
    var list = document.getElementById('req-list');
    list.innerHTML = '<div class="item">Memeriksa requirement...</div>';
    var res = await callApi('check_reqs', {});
    var html = '';
    var i;
    if (!res.ok && !Array.isArray(res.items)) {
        html = '<div class="item err">Gagal memuat requirement: ' + escapeHtml(res.error || 'unknown') + '</div>';
        document.getElementById('req-next').disabled = true;
        document.getElementById('req-note').textContent = 'Requirement gagal dimuat.';
        list.innerHTML = html;
        return;
    }
    for (i = 0; i < res.items.length; i += 1) {
        var item = res.items[i];
        var cls = item.ok ? 'ok' : (item.warn ? 'warn' : 'err');
        html += '<div class="item ' + cls + '"><strong>' + escapeHtml(item.label) + '</strong>' + (item.note ? '<div>' + escapeHtml(item.note) + '</div>' : '') + '</div>';
    }
    list.innerHTML = html;
    document.getElementById('req-next').disabled = !res.ok;
    document.getElementById('req-note').textContent = res.ok ? 'Semua requirement wajib lolos.' : 'Masih ada requirement wajib yang gagal.';
}

function dbPayload() {
    return {db_host: document.getElementById('db_host').value, db_port: document.getElementById('db_port').value, db_name: document.getElementById('db_name').value, db_user: document.getElementById('db_user').value, db_pass: document.getElementById('db_pass').value};
}

async function testDb() {
    setStatus('db-status', 'Menguji koneksi database...', 'load');
    var res = await callApi('test_db', dbPayload());
    setStatus('db-status', res.ok ? res.message : res.error, res.ok ? 'ok' : 'err');
}

async function saveDb() {
    setStatus('db-status', 'Memvalidasi database...', 'load');
    var test = await callApi('test_db', dbPayload());
    if (!test.ok) { setStatus('db-status', test.error, 'err'); return; }
    var res = await callApi('save_db', dbPayload());
    if (!res.ok) { setStatus('db-status', res.error, 'err'); return; }
    setStatus('db-status', 'Database tersimpan di session installer.', 'ok');
    showStep(3);
}

function generateApiKey() {
    var bytes = new Uint8Array(32);
    var i;
    var out = '';
    window.crypto.getRandomValues(bytes);
    for (i = 0; i < bytes.length; i += 1) { out += bytes[i].toString(16).padStart(2, '0'); }
    document.getElementById('api_key').value = out;
}

async function saveAdmin() {
    if (document.getElementById('admin_pass').value !== document.getElementById('admin_pass2').value) {
        setStatus('admin-status', 'Konfirmasi password tidak sama.', 'err');
        return;
    }
    setStatus('admin-status', 'Menyimpan admin...', 'load');
    var res = await callApi('save_admin', {admin_user: document.getElementById('admin_user').value, admin_pass: document.getElementById('admin_pass').value, api_key: document.getElementById('api_key').value, app_url: document.getElementById('app_url').value});
    if (!res.ok) { setStatus('admin-status', res.error, 'err'); return; }
    installState.apiKey = res.api_key || '';
    setStatus('admin-status', 'Admin tersimpan.', 'ok');
    showStep(4);
}

async function runInstall() {
    if (installState.running) { return; }
    installState.running = true;
    document.getElementById('install-run').disabled = true;
    document.getElementById('summary-btn').classList.add('hidden');
    document.getElementById('install-log').textContent = '$ srp installer --secure';
    setStatus('install-status', 'Menjalankan instalasi...', 'load');
    var res = await callApi('run_install', {});
    var i;
    if (Array.isArray(res.log)) {
        for (i = 0; i < res.log.length; i += 1) {
            document.getElementById('install-log').textContent += '\n' + (res.log[i].ok ? '[OK] ' : '[ERR] ') + res.log[i].msg;
        }
    }
    if (res.ok) {
        installState.apiKey = res.api_key || installState.apiKey;
        installState.crons = Array.isArray(res.crons) ? res.crons : [];
        setStatus('install-status', 'Instalasi selesai dan installer terkunci.', 'ok');
        document.getElementById('summary-btn').classList.remove('hidden');
    } else {
        setStatus('install-status', res.error || 'Instalasi gagal.', 'err');
    }
    document.getElementById('install-run').disabled = false;
    installState.running = false;
}

async function deleteInstaller() {
    if (!window.confirm('Hapus install.php sekarang?')) { return; }
    setStatus('delete-status', 'Menghapus install.php...', 'load');
    var res = await callApi('delete_self', {});
    if (!res.ok) { setStatus('delete-status', res.error, 'err'); return; }
    document.getElementById('delete-btn').disabled = true;
    setStatus('delete-status', 'install.php berhasil dihapus.', 'ok');
}

function escapeHtml(value) {
    return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

document.getElementById('req-refresh').addEventListener('click', checkReqs);
document.getElementById('req-next').addEventListener('click', function () { showStep(2); });
document.getElementById('db-test').addEventListener('click', testDb);
document.getElementById('db-save').addEventListener('click', saveDb);
document.getElementById('api-generate').addEventListener('click', generateApiKey);
document.getElementById('admin-save').addEventListener('click', saveAdmin);
document.getElementById('install-run').addEventListener('click', runInstall);
document.getElementById('summary-btn').addEventListener('click', function () { showStep(5); });
document.getElementById('delete-btn').addEventListener('click', deleteInstaller);
Array.prototype.forEach.call(document.querySelectorAll('[data-step]'), function (el) { el.addEventListener('click', function () { showStep(Number(el.getAttribute('data-step'))); }); });

showStep(currentStep);
if (!BOOT.completed) { checkReqs(); }
</script>
</body>
</html>

<?php

declare(strict_types=1);

/**
 * ================================================================
 * SRP v2.1 — Deploy Verification Script
 * ================================================================
 * Jalankan di server production untuk memverifikasi semua file
 * dan konfigurasi sudah benar setelah deploy.
 *
 * Usage:
 *   php deploy-check.php              # CLI mode
 *   curl https://domain.com/deploy-check.php  # Web mode (hapus setelah selesai!)
 *
 * ⚠  HAPUS FILE INI SETELAH VERIFIKASI SELESAI!
 * ================================================================
 */

// ── Config ──────────────────────────────────────────────────────

$projectRoot = dirname(__DIR__);

// Deteksi apakah script ada di public_html/ atau root
if (basename(__DIR__) === 'public_html') {
    $projectRoot = dirname(__DIR__);
    $publicRoot  = __DIR__;
} else {
    $projectRoot = __DIR__;
    $publicRoot  = __DIR__ . DIRECTORY_SEPARATOR . 'public_html';
}

$isCli = (PHP_SAPI === 'cli');

// ── Output helpers ──────────────────────────────────────────────

$results = [];
$errors  = 0;
$warns   = 0;
$passes  = 0;

function check(string $label, bool $pass, string $detail = '', bool $isWarn = false): void
{
    global $results, $errors, $warns, $passes;

    if ($pass) {
        $passes++;
        $results[] = ['pass', $label, $detail];
    } elseif ($isWarn) {
        $warns++;
        $results[] = ['warn', $label, $detail];
    } else {
        $errors++;
        $results[] = ['fail', $label, $detail];
    }
}

function heading(string $title): void
{
    global $results;
    $results[] = ['heading', $title, ''];
}

// ── 1. Critical Files ───────────────────────────────────────────

heading('Critical Files — Brand Domain (public_html/)');

$criticalPublic = [
    'index.php'      => 'Dashboard entry',
    'decision.php'   => 'Decision engine endpoint',
    'api.php'        => 'REST API endpoint',
    'postback.php'   => 'Conversion tracking endpoint',
    'login.php'      => 'Auth page',
    'logout.php'     => 'Logout handler',
    'stats.php'      => 'Stats page',
    'data.php'       => 'Data export',
    'env-config.php' => 'Env config viewer',
    'landing.php'    => 'Landing page handler',
    'install.php'    => 'Web installer (hapus di production)',
    '.htaccess'      => 'Apache rewrite rules',
];

foreach ($criticalPublic as $file => $desc) {
    $path = $publicRoot . DIRECTORY_SEPARATOR . $file;
    $exists = file_exists($path);

    if ($file === 'install.php' && $exists) {
        check(
            "public_html/{$file}",
            false,
            "⚠ SECURITY: install.php masih ada! Hapus setelah instalasi selesai.",
            true
        );
    } elseif ($file === '.htaccess') {
        check("public_html/{$file}", $exists, $exists ? $desc : "MISSING — URL rewriting tidak aktif!");
    } else {
        check("public_html/{$file}", $exists, $exists ? $desc : "MISSING — {$desc}");
    }
}

// ── 2. Source Code ──────────────────────────────────────────────

heading('Source Code (src/)');

$criticalSrc = [
    'src/Config/Bootstrap.php'             => 'Autoloader & bootstrap',
    'src/Config/Database.php'              => 'Database connection',
    'src/Config/Environment.php'           => 'Env variable loader',
    'src/Config/Cache.php'                 => 'Cache backend',
    'src/Controllers/DecisionController.php'  => 'Core routing logic',
    'src/Controllers/PublicApiController.php'  => 'REST API v1',
    'src/Controllers/AuthController.php'      => 'Authentication',
    'src/Controllers/DashboardController.php' => 'Dashboard handler',
    'src/Controllers/LandingController.php'   => 'Landing page logic',
    'src/Controllers/ApiController.php'       => 'Internal API',
    'src/Middleware/Session.php'            => 'Session management',
    'src/Middleware/SecurityHeaders.php'    => 'Security headers',
    'src/Models/Settings.php'              => 'Settings model',
    'src/Models/TrafficLog.php'            => 'Traffic logging',
    'src/Models/Conversion.php'            => 'Conversion tracking',
    'src/Models/SrpClient.php'             => 'API client',
    'src/Models/Validator.php'             => 'Input validation',
    'src/Models/EnvConfig.php'             => 'Env config model',
];

foreach ($criticalSrc as $file => $desc) {
    $path = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    check($file, file_exists($path), $desc);
}

// ── 3. Views ────────────────────────────────────────────────────

heading('Views (src/Views/)');

$views = [
    'src/Views/dashboard.view.php',
    'src/Views/login.view.php',
    'src/Views/stats.view.php',
    'src/Views/landing.view.php',
    'src/Views/components/header.php',
    'src/Views/components/footer.php',
    'src/Views/components/overview-tab.php',
    'src/Views/components/routing-tab.php',
    'src/Views/components/logs-tab.php',
    'src/Views/components/analytics-tab.php',
    'src/Views/components/env-config-tab.php',
    'src/Views/components/api-docs-tab.php',
    'src/Views/components/dashboard-content.php',
    'src/Views/components/tabs-navigation.php',
    'src/Views/components/toast.php',
];

foreach ($views as $file) {
    $path = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    check($file, file_exists($path));
}

// ── 4. Tracking Domain (entry.php) ──────────────────────────────

heading('Tracking Domain (entry.php)');

$entryPath = $projectRoot . DIRECTORY_SEPARATOR . 'entry.php';
check('entry.php', file_exists($entryPath), 'Client-side tracking & redirect');

if (file_exists($entryPath)) {
    $entryContent = file_get_contents($entryPath);
    $hasApiUrl = (bool) preg_match("/define\s*\(\s*'HANTUIN_API_URL'/", $entryContent);
    $hasFallback = (bool) preg_match("/define\s*\(\s*'FALLBACK_PATH'/", $entryContent);
    check('entry.php → HANTUIN_API_URL defined', $hasApiUrl, 'API URL harus diset ke brand domain');
    check('entry.php → FALLBACK_PATH defined', $hasFallback, 'Fallback path harus diset');

    // Cek apakah API URL masih localhost
    if (preg_match("/HANTUIN_API_URL.*localhost/", $entryContent)) {
        check('entry.php → API URL production', false, 'MASIH LOCALHOST! Ganti ke domain production.', true);
    }
}

// ── 5. Config & Schema ──────────────────────────────────────────

heading('Config & Schema');

$configFiles = [
    '.env'            => 'Environment config (harus ada di production)',
    '.env.example'    => 'Template referensi',
    'schema.sql'      => 'Database schema',
    'composer.json'   => 'Dependency manifest',
    'composer.lock'   => 'Locked dependencies',
];

foreach ($configFiles as $file => $desc) {
    $path = $projectRoot . DIRECTORY_SEPARATOR . $file;
    check($file, file_exists($path), $desc);
}

// ── 6. Autoloader ───────────────────────────────────────────────

heading('Autoloader (vendor/)');

$autoload = $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
check('vendor/autoload.php', file_exists($autoload), 'Jalankan: composer install --no-dev');

// ── 7. Cron Scripts ─────────────────────────────────────────────

heading('Cron Scripts');

$cronFiles = [
    'cron/backup.php'       => 'Database backup',
    'cron/cleanup.php'      => 'Log cleanup',
    'cron/health-check.php' => 'System health monitoring',
    'cron/purge.php'        => 'Data purge',
];

foreach ($cronFiles as $file => $desc) {
    $path = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    $exists = file_exists($path);
    check($file, $exists, $exists ? $desc : "MISSING (optional)");
}

// ── 8. Directory Permissions ────────────────────────────────────

heading('Directories & Permissions');

$runtimeDirs = [
    'backups'     => 'Database backups',
    'logs'        => 'Application logs',
    'storage'     => 'Temporary storage',
    'storage/tmp' => 'Temp files',
];

foreach ($runtimeDirs as $dir => $desc) {
    $path = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dir);
    $exists = is_dir($path);
    check($dir . '/', $exists, $exists ? $desc : "MISSING — buat: mkdir -p {$dir}");

    if ($exists && !$isCli) {
        $writable = is_writable($path);
        check($dir . '/ writable', $writable, $writable ? 'OK' : 'Tidak writable! chmod 750', true);
    }
}

// ── 9. .htaccess Protection ─────────────────────────────────────

heading('Security (.htaccess protection)');

$protectedDirs = ['src', 'cron', 'logs', 'backups', 'storage'];
foreach ($protectedDirs as $dir) {
    $htaccess = $projectRoot . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . '.htaccess';
    $exists = file_exists($htaccess);
    check(
        "{$dir}/.htaccess",
        $exists,
        $exists ? 'Protected — Deny from all' : 'MISSING — Direktori terbuka dari web!'
    );
}

// ── 10. .env Validation ─────────────────────────────────────────

heading('.env Configuration');

$envPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';
if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    $envVars = [];
    foreach (explode("\n", $envContent) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $val] = explode('=', $line, 2);
            $envVars[trim($key)] = trim($val);
        }
    }

    // Critical checks
    $env = $envVars['SRP_ENV'] ?? '';
    check('.env → SRP_ENV', $env === 'production', $env ?: '(empty) — set ke "production"');

    $dbHost = $envVars['SRP_DB_HOST'] ?? '';
    check('.env → SRP_DB_HOST', $dbHost !== '', $dbHost ?: '(empty)');

    $dbName = $envVars['SRP_DB_NAME'] ?? '';
    check('.env → SRP_DB_NAME', $dbName !== '', $dbName ?: '(empty)');

    $apiKey = $envVars['SRP_API_KEY'] ?? '';
    check('.env → SRP_API_KEY', strlen($apiKey) >= 32, strlen($apiKey) . ' chars');

    $adminHash = $envVars['SRP_ADMIN_PASSWORD_HASH'] ?? '';
    $adminPlain = $envVars['SRP_ADMIN_PASSWORD'] ?? '';
    check(
        '.env → Admin auth',
        $adminHash !== '' || $adminPlain !== '',
        $adminHash !== '' ? 'bcrypt hash (aman)' : 'plain password (ganti ke hash!)'
    );

    if ($adminPlain !== '' && $adminHash === '') {
        check(
            '.env → Password security',
            false,
            'SRP_ADMIN_PASSWORD plaintext! Generate hash: php -r "echo password_hash(\'password\', PASSWORD_BCRYPT);"',
            true
        );
    }

    $secureCookies = $envVars['SRP_FORCE_SECURE_COOKIES'] ?? '';
    check(
        '.env → SRP_FORCE_SECURE_COOKIES',
        $secureCookies === 'true' || $secureCookies === '1',
        $secureCookies ?: '(empty) — set ke "true" di production!',
        $secureCookies !== 'true' && $secureCookies !== '1'
    );

    $debug = $envVars['APP_DEBUG'] ?? '';
    check(
        '.env → APP_DEBUG',
        $debug === 'false' || $debug === '0' || $debug === '',
        $debug ?: '(empty/false)',
        $debug === 'true' || $debug === '1'
    );

    $appUrl = $envVars['APP_URL'] ?? '';
    if (str_contains($appUrl, 'localhost')) {
        check('.env → APP_URL', false, "MASIH LOCALHOST: {$appUrl}", true);
    } else {
        check('.env → APP_URL', $appUrl !== '', $appUrl ?: '(empty)');
    }

    $remoteUrl = $envVars['SRP_REMOTE_DECISION_URL'] ?? '';
    if ($remoteUrl !== '' && str_contains($remoteUrl, 'localhost')) {
        check('.env → SRP_REMOTE_DECISION_URL', false, "MASIH LOCALHOST: {$remoteUrl}", true);
    }
} else {
    check('.env', false, 'File .env TIDAK ADA! Copy dari .env.example');
}

// ── 11. PHP Environment ─────────────────────────────────────────

heading('PHP Environment');

check('PHP version ≥ 8.3', version_compare(PHP_VERSION, '8.3.0', '>='), PHP_VERSION);

$requiredExts = ['curl', 'json', 'mbstring', 'openssl', 'pdo', 'pdo_mysql'];
foreach ($requiredExts as $ext) {
    check("ext-{$ext}", extension_loaded($ext));
}

$optionalExts = ['apcu', 'redis', 'memcached'];
foreach ($optionalExts as $ext) {
    $loaded = extension_loaded($ext);
    check("ext-{$ext} (optional)", $loaded, $loaded ? 'Loaded' : 'Not loaded (ok, fallback available)', !$loaded);
}

// ── 12. Database Connection ─────────────────────────────────────

heading('Database Connection');

if (file_exists($envPath)) {
    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $envVars['SRP_DB_HOST'] ?? '127.0.0.1',
            $envVars['SRP_DB_PORT'] ?? '3306',
            $envVars['SRP_DB_NAME'] ?? 'srp'
        );
        $socket = $envVars['SRP_DB_SOCKET'] ?? '';
        if ($socket !== '') {
            $dsn = sprintf(
                'mysql:unix_socket=%s;dbname=%s;charset=utf8mb4',
                $socket,
                $envVars['SRP_DB_NAME'] ?? 'srp'
            );
        }

        $pdo = new PDO(
            $dsn,
            $envVars['SRP_DB_USER'] ?? 'root',
            $envVars['SRP_DB_PASS'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );
        check('DB connection', true, 'Connected');

        // Cek tabel
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $requiredTables = ['settings', 'traffic_logs', 'conversions'];
        foreach ($requiredTables as $table) {
            check(
                "DB table: {$table}",
                in_array($table, $tables, true),
                in_array($table, $tables, true) ? 'Exists' : 'MISSING — import schema.sql'
            );
        }
    } catch (\PDOException $e) {
        check('DB connection', false, $e->getMessage());
    }
} else {
    check('DB connection', false, 'Skip — .env tidak ada');
}

// ── Output ──────────────────────────────────────────────────────

if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Robots-Tag: noindex');
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  SRP v2.1 — Deploy Verification Report\n";
echo "  " . date('Y-m-d H:i:s T') . "\n";
echo "  Server: " . php_uname('n') . " | PHP " . PHP_VERSION . "\n";
echo "  Root: " . $projectRoot . "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

foreach ($results as $r) {
    [$status, $label, $detail] = $r;

    if ($status === 'heading') {
        echo "\n── {$label} " . str_repeat('─', max(1, 56 - strlen($label))) . "\n\n";
        continue;
    }

    $icon = match ($status) {
        'pass' => '✓',
        'warn' => '⚠',
        'fail' => '✗',
    };

    $line = "  {$icon}  {$label}";
    if ($detail !== '') {
        $line .= "  →  {$detail}";
    }
    echo $line . "\n";
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  Summary:  {$passes} passed";
if ($warns > 0) {
    echo "  |  {$warns} warnings";
}
if ($errors > 0) {
    echo "  |  {$errors} ERRORS";
}
echo "\n";

if ($errors > 0) {
    echo "  ✗  Deploy BELUM LENGKAP — perbaiki error di atas!\n";
} elseif ($warns > 0) {
    echo "  ⚠  Deploy OK dengan catatan — review warning di atas.\n";
} else {
    echo "  ✓  Deploy SEMPURNA — semua file & konfigurasi valid!\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n⚠  HAPUS FILE INI SETELAH VERIFIKASI SELESAI!\n";

exit($errors > 0 ? 1 : 0);

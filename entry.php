<?php

declare(strict_types=1);

// =============================================================================
// Hantuin-v2 — Client Entry Point
// =============================================================================
// Upload file ini ke SERVER CLIENT (server yang terima traffic).
// File ini akan request keputusan ke server Hantuin-v2, lalu redirect visitor.
//
// Flow:
//   Visitor → entry.php → POST /api/v1/decision → redirect ke target
//
// Contoh URL traffic masuk:
//   https://client-domain.com/entry.php?click_id=ABC123&user_lp=campaign1
//
// Query params yang didukung:
//   click_id      (wajib)  — ID klik dari traffic network
//   user_lp       (opsional) — nama campaign / landing page
//   country_code  (opsional) — override country code (ISO Alpha-2)
//   user_agent    (opsional) — override device: mobile | desktop
//   ip_address    (opsional) — override IP address
// =============================================================================

// ── ENV LOADER ────────────────────────────────────────────────────────────────
// Baca .env di direktori yang sama dengan entry.php.
// Format: KEY=VALUE (satu per baris, # untuk komentar, tanpa spasi di = ).

/**
 * Parse .env file sederhana. Tidak butuh library eksternal.
 *
 * @return array<string,string>
 */
function loadEntryEnv(): array
{
    $envFile = __DIR__ . '/.env';
    if (!is_file($envFile) || !is_readable($envFile)) {
        return [];
    }

    $vars = [];
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }

    foreach ($lines as $line) {
        $line = trim($line);
        // Lewati komentar dan baris kosong
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));
        // Hapus quotes (single / double) yang membungkus value
        if (strlen($val) >= 2 && (($val[0] === '"' && $val[-1] === '"') || ($val[0] === "'" && $val[-1] === "'"))) {
            $val = substr($val, 1, -1);
        }
        $vars[$key] = $val;
    }

    return $vars;
}

/**
 * Ambil config value: .env file → environment variable → default.
 */
function entryConfig(string $key, string $default, array $envVars): string
{
    if (isset($envVars[$key]) && $envVars[$key] !== '') {
        return $envVars[$key];
    }
    $fromEnv = getenv($key);
    if ($fromEnv !== false && $fromEnv !== '') {
        return $fromEnv;
    }
    return $default;
}

$_entryEnv = loadEntryEnv();

// ── CONFIG ────────────────────────────────────────────────────────────────────

// URL endpoint decision di server Hantuin-v2
// Format: https://<domain-hantuinv2>/api/v1/decision
define('HANTUIN_API_URL', entryConfig('HANTUIN_API_URL', '', $_entryEnv));

// API key dari dashboard Hantuin-v2 (Settings → API Key)
define('HANTUIN_API_KEY', entryConfig('HANTUIN_API_KEY', '', $_entryEnv));

// Fallback URL jika decision gagal (relative path untuk keamanan)
define('FALLBACK_PATH', entryConfig('FALLBACK_PATH', '/_meetups/', $_entryEnv));

// Timeout koneksi ke server Hantuin-v2 (detik)
define('API_TIMEOUT', (int) entryConfig('API_TIMEOUT', '5', $_entryEnv));
define('API_CONNECT_TIMEOUT', (int) entryConfig('API_CONNECT_TIMEOUT', '3', $_entryEnv));

// Cache decision per URL (detik). 0 = nonaktif. Butuh APCu atau direktori cache writable.
define('DECISION_CACHE_TTL', (int) entryConfig('DECISION_CACHE_TTL', '3', $_entryEnv));
define('DECISION_CACHE_DIR', entryConfig('DECISION_CACHE_DIR', sys_get_temp_dir() . '/hantuin_cache', $_entryEnv));

unset($_entryEnv);

// ── JANGAN EDIT DI BAWAH INI ────────────────────────────────────────────────

/**
 * Build cache key dari params + API URL.
 *
 * @param array<string,string> $params
 */
function buildCacheKey(array $params): string
{
    // Key by routing-relevant fields only (ip, country, device).
    // click_id and user_lp don't affect the routing decision, so excluding
    // them allows cache hits across different visitors with the same profile.
    return 'hantuin_' . sha1(
        HANTUIN_API_URL . '|' .
        ($params['ip_address']   ?? '') . '|' .
        ($params['country_code'] ?? '') . '|' .
        ($params['user_agent']   ?? '')
    );
}

/**
 * Baca decision dari cache (APCu atau file).
 *
 * @return array<string,mixed>|null
 */
function readDecisionCache(string $key): ?array
{
    if (DECISION_CACHE_TTL <= 0) {
        return null;
    }

    if (function_exists('apcu_fetch')) {
        $hit   = false;
        $value = apcu_fetch($key, $hit);
        if ($hit && is_array($value)) {
            return $value;
        }
    }

    $file = DECISION_CACHE_DIR . '/' . $key . '.json';
    if (!is_file($file) || !is_readable($file)) {
        return null;
    }

    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || !isset($decoded['until'], $decoded['data'])) {
        return null;
    }

    if ((int) $decoded['until'] < time() || !is_array($decoded['data'])) {
        return null;
    }

    return $decoded['data'];
}

/**
 * Simpan decision ke cache (APCu dan file).
 *
 * @param array<string,mixed> $data
 */
function writeDecisionCache(string $key, array $data): void
{
    if (DECISION_CACHE_TTL <= 0) {
        return;
    }

    if (function_exists('apcu_store')) {
        apcu_store($key, $data, DECISION_CACHE_TTL);
    }

    if (!is_dir(DECISION_CACHE_DIR)) {
        @mkdir(DECISION_CACHE_DIR, 0755, true);
    }

    if (!is_dir(DECISION_CACHE_DIR) || !is_writable(DECISION_CACHE_DIR)) {
        return;
    }

    $payload = json_encode(['until' => time() + DECISION_CACHE_TTL, 'data' => $data]);
    if ($payload !== false) {
        file_put_contents(DECISION_CACHE_DIR . '/' . $key . '.json', $payload, LOCK_EX);
    }
}

/**
 * Request keputusan redirect ke server Hantuin-v2.
 *
 * @param array<string,string> $params
 * @return array<string,mixed>|null
 */
function getDecision(array $params): ?array
{
    if (HANTUIN_API_KEY === '' || HANTUIN_API_KEY === 'GANTI_DENGAN_API_KEY_ANDA') {
        error_log('Hantuin-v2: API key belum dikonfigurasi');
        return null;
    }

    $required = ['country_code', 'user_agent', 'ip_address'];
    foreach ($required as $field) {
        if (empty($params[$field])) {
            error_log("Hantuin-v2: Missing required field: {$field}");
            return null;
        }
    }

    $cacheKey = buildCacheKey($params);
    $cached   = readDecisionCache($cacheKey);
    if ($cached !== null) {
        return $cached;
    }

    $ch = curl_init(HANTUIN_API_URL);
    if ($ch === false) {
        error_log('Hantuin-v2: curl_init failed');
        return null;
    }

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-API-Key: ' . HANTUIN_API_KEY,
            'User-Agent: Hantuin-v2-Client/1.0',
        ],
        CURLOPT_POSTFIELDS     => (string) json_encode($params),
        CURLOPT_TIMEOUT        => API_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => API_CONNECT_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    unset($ch);

    if ($error !== '') {
        error_log("Hantuin-v2 API Error: {$error}");
        return null;
    }

    if ($httpCode !== 200) {
        error_log("Hantuin-v2 API HTTP {$httpCode}: {$response}");
        return null;
    }

    if (!is_string($response)) {
        return null;
    }

    $data = json_decode($response, true);

    if (!is_array($data) || !isset($data['ok']) || $data['ok'] !== true) {
        error_log("Hantuin-v2 API Invalid Response: {$response}");
        return null;
    }

    writeDecisionCache($cacheKey, $data);

    return $data;
}

/**
 * Detect country code dari CDN geo headers, query string, atau fallback XX.
 */
function detectCountry(): string
{
    $code = strtoupper(trim((string) (
        $_SERVER['HTTP_CF_IPCOUNTRY']
        ?? $_SERVER['HTTP_X_VERCEL_IP_COUNTRY']
        ?? $_SERVER['HTTP_X_COUNTRY_CODE']
        ?? $_SERVER['HTTP_X_APPENGINE_COUNTRY']
        ?? $_SERVER['HTTP_X_GEO_COUNTRY']
        ?? $_GET['country_code']
        ?? 'XX'
    )));

    if (!preg_match('/\A[A-Z]{2}\z/', $code)) {
        return 'XX';
    }

    return $code;
}

/**
 * Detect device type: BOT | wap (mobile/tablet) | web (desktop).
 */
function detectDevice(): string
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $override = strtolower((string) ($_GET['user_agent'] ?? ''));

    // Bot detection first
    if (preg_match('~bot|crawl|spider|facebook|whatsapp|telegram|twitter~i', $ua)) {
        return 'BOT';
    }

    // Device from UA
    $device = 'web';
    if (preg_match('/tablet|ipad/i', $ua)) {
        $device = 'wap';
    } elseif (preg_match('/mobile|android|iphone|ipod|blackberry|iemobile|opera mini|windows phone/i', $ua)) {
        $device = 'wap';
    }

    // Query string override
    $map = ['mobile' => 'wap', 'desktop' => 'web'];
    $override = $map[$override] ?? $override;
    if (in_array($override, ['wap', 'web'], true)) {
        $device = $override;
    }

    return $device;
}

/**
 * Detect real client IP (supports Cloudflare, reverse proxies, X-Forwarded-For).
 */
function detectIp(): string
{
    // Trusted single-IP headers (set by reverse proxy / CDN)
    $trusted = [
        'HTTP_CF_CONNECTING_IP',   // Cloudflare
        'HTTP_TRUE_CLIENT_IP',     // Cloudflare Enterprise / Akamai
        'HTTP_X_REAL_IP',          // Nginx reverse proxy
    ];

    foreach ($trusted as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = trim((string) $_SERVER[$header]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    // X-Forwarded-For: pick first public IP (max 10 to prevent DoS from huge headers)
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = array_slice(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']), 0, 10);
        foreach ($ips as $ip) {
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '';
}

// ── MAIN ─────────────────────────────────────────────────────────────────────

$clickId     = trim((string) ($_GET['click_id'] ?? ''));
$countryCode = detectCountry();
$device      = detectDevice();
$ipAddress   = detectIp();
$campaign    = (string) ($_GET['user_lp'] ?? '');

// Tanpa click_id, langsung fallback — jangan buang resource ke API
$decision = null;
if ($clickId !== '') {
    $decision = getDecision([
        'click_id'     => $clickId,
        'country_code' => $countryCode,
        'user_agent'   => $device,
        'ip_address'   => $ipAddress,
        'user_lp'      => $campaign,
    ]);
}

// Decision berhasil — redirect ke target
if ($decision !== null && isset($decision['target'])) {
    $target         = (string) $decision['target'];
    $decisionType   = (string) ($decision['decision'] ?? '');

    // Validasi URL — block non-http(s) untuk cegah open-redirect
    if (preg_match('#^https?://#i', $target) && filter_var($target, FILTER_VALIDATE_URL) !== false) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Location: ' . $target, true, 302);
        exit;
    }

    // Decision B always returns a relative fallback URL — not an error, skip log
    if ($decisionType !== 'B') {
        error_log("Hantuin-v2: rejected invalid target URL: {$target}");
    }
}

// Fallback — decision gagal atau target invalid
// Hanya teruskan click_id dan user_lp, buang parameter lain (country_code, user_agent, dll.)
$fallbackParams = [];
if ($clickId !== '') {
    $fallbackParams['click_id'] = strtolower($clickId);
}
if ($campaign !== '') {
    $fallbackParams['user_lp'] = strtolower($campaign);
}
$fallback = FALLBACK_PATH . (!empty($fallbackParams) ? '?' . http_build_query($fallbackParams, '', '&', PHP_QUERY_RFC3986) : '');

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Location: ' . $fallback, true, 302);
exit;

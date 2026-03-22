<?php

declare(strict_types=1);

namespace SRP\Models;

use SRP\Config\Environment;

/**
 * Environment Configuration Model
 * Manages .env file configuration without manual file editing.
 * DB keys (SRP_DB_*) are preserved in the .env on every save but are not
 * exposed in the dashboard UI — configure them directly in .env.
 */
class EnvConfig
{
    private static string $envFilePath;

    /** Placeholder returned to the browser in place of real secret values. */
    private const MASK_SENTINEL = '••••••••';

    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'SRP_DB_PASS',
        'SRP_ADMIN_PASSWORD_HASH',
        'SRP_ADMIN_PASSWORD',
        'SRP_USER_PASSWORD_HASH',
        'SRP_USER_PASSWORD',
        'SRP_REMOTE_API_KEY',
        'REDIS_PASSWORD',
    ];

    /** @var list<string> */
    private const ALLOWED_KEY_PREFIXES = [
        'SRP_',
        'APP_',
        'SESSION_',
        'RATE_LIMIT_',
        'CACHE_',
        'REDIS_',
        'MEMCACHED_',
    ];

    private static function init(): void
    {
        if (!isset(self::$envFilePath)) {
            self::$envFilePath = dirname(__DIR__, 2) . '/.env';
        }
    }

    /**
     * Get all environment configuration.
     * Keys match what Database.php and the rest of the app actually read.
     *
     * @return array<string,string>
     */
    public static function getAll(): array
    {
        self::init();

        return [
            // Application
            'APP_URL'   => getenv('APP_URL') ?: '',
            'APP_ENV'   => getenv('APP_ENV') ?: 'production',
            'APP_DEBUG' => getenv('APP_DEBUG') ?: 'false',
            'SRP_ENV'   => getenv('SRP_ENV') ?: 'production',
            'SRP_ENV_FILE' => getenv('SRP_ENV_FILE') ?: '',

            // Database (canonical SRP_DB_* — matches Database.php)
            'SRP_DB_HOST'   => getenv('SRP_DB_HOST') ?: '127.0.0.1',
            'SRP_DB_PORT'   => getenv('SRP_DB_PORT') ?: '3306',
            'SRP_DB_NAME'   => getenv('SRP_DB_NAME') ?: '',
            'SRP_DB_USER'   => getenv('SRP_DB_USER') ?: '',
            'SRP_DB_PASS'   => getenv('SRP_DB_PASS') ?: '',
            'SRP_DB_SOCKET' => getenv('SRP_DB_SOCKET') ?: '',

            // API Keys
            'SRP_API_KEY' => getenv('SRP_API_KEY') ?: '',

            // Remote decision server (S2S)
            'SRP_REMOTE_DECISION_URL' => getenv('SRP_REMOTE_DECISION_URL') ?: '',
            'SRP_REMOTE_API_KEY'      => getenv('SRP_REMOTE_API_KEY') ?: '',

            // API client tuning
            'SRP_API_TIMEOUT'                => getenv('SRP_API_TIMEOUT') ?: '8',
            'SRP_API_CONNECT_TIMEOUT'        => getenv('SRP_API_CONNECT_TIMEOUT') ?: '3',
            'SRP_API_FAILURE_COOLDOWN'       => getenv('SRP_API_FAILURE_COOLDOWN') ?: '30',
            'SRP_API_MAX_RETRIES'            => getenv('SRP_API_MAX_RETRIES') ?: '0',
            'SRP_API_BACKOFF_BASE_MS'        => getenv('SRP_API_BACKOFF_BASE_MS') ?: '250',
            'SRP_API_BACKOFF_MAX_MS'         => getenv('SRP_API_BACKOFF_MAX_MS') ?: '1500',
            'SRP_API_RESPONSE_CACHE_SECONDS' => getenv('SRP_API_RESPONSE_CACHE_SECONDS') ?: '3',
            'SRP_API_INFLIGHT_WAIT_MS'       => getenv('SRP_API_INFLIGHT_WAIT_MS') ?: '300',

            // VPN Check
            'SRP_VPN_CHECK_ENABLED' => getenv('SRP_VPN_CHECK_ENABLED') ?: '1',

            // Rate Limiting
            'SRP_PUBLIC_API_RATE_WINDOW'    => getenv('SRP_PUBLIC_API_RATE_WINDOW') ?: '60',
            'SRP_PUBLIC_API_RATE_MAX'       => getenv('SRP_PUBLIC_API_RATE_MAX') ?: '1000',
            'SRP_PUBLIC_API_RATE_HEAVY_MAX' => getenv('SRP_PUBLIC_API_RATE_HEAVY_MAX') ?: '30',
            'RATE_LIMIT_ATTEMPTS'           => getenv('RATE_LIMIT_ATTEMPTS') ?: '5',
            'RATE_LIMIT_WINDOW'             => getenv('RATE_LIMIT_WINDOW') ?: '900',

            // Admin credentials
            'SRP_ADMIN_USER'          => getenv('SRP_ADMIN_USER') ?: 'admin',
            'SRP_ADMIN_PASSWORD_HASH' => getenv('SRP_ADMIN_PASSWORD_HASH') ?: '',
            'SRP_ADMIN_PASSWORD'      => getenv('SRP_ADMIN_PASSWORD') ?: '',
            'SRP_USER_USER'           => getenv('SRP_USER_USER') ?: '',
            'SRP_USER_PASSWORD_HASH'  => getenv('SRP_USER_PASSWORD_HASH') ?: '',
            'SRP_USER_PASSWORD'       => getenv('SRP_USER_PASSWORD') ?: '',

            // Security
            'SRP_TRUSTED_PROXIES'      => getenv('SRP_TRUSTED_PROXIES') ?: '',
            'SRP_FORCE_SECURE_COOKIES' => getenv('SRP_FORCE_SECURE_COOKIES') ?: 'true',

            // Cache
            'CACHE_DRIVER'    => getenv('CACHE_DRIVER') ?: '',
            'CACHE_PREFIX'    => getenv('CACHE_PREFIX') ?: 'srp_',
            'REDIS_HOST'      => getenv('REDIS_HOST') ?: '127.0.0.1',
            'REDIS_PORT'      => getenv('REDIS_PORT') ?: '6379',
            'REDIS_PASSWORD'  => getenv('REDIS_PASSWORD') ?: '',
            'REDIS_DB'        => getenv('REDIS_DB') ?: '0',
            'MEMCACHED_HOST'  => getenv('MEMCACHED_HOST') ?: '127.0.0.1',
            'MEMCACHED_PORT'  => getenv('MEMCACHED_PORT') ?: '11211',

            // Session
            'SESSION_LIFETIME' => getenv('SESSION_LIFETIME') ?: '3600',
        ];
    }

    /**
     * Get all configuration with sensitive values masked.
     *
     * @return array<string,string>
     */
    public static function getAllMasked(): array
    {
        $all = self::getAll();
        foreach (self::SENSITIVE_KEYS as $key) {
            if (isset($all[$key]) && $all[$key] !== '') {
                $all[$key] = self::maskValue($all[$key]);
            }
        }
        return $all;
    }

    /**
     * Partial-update environment configuration and persist to .env.
     *
     * @param array<string,string> $newConfig
     */
    public static function update(array $newConfig): bool
    {
        self::init();

        try {
            $envContent = file_exists(self::$envFilePath)
                ? (string)file_get_contents(self::$envFilePath)
                : '';

            $envVars = self::parseEnvFile($envContent);

            foreach ($newConfig as $key => $value) {
                if (!self::isValidEnvKey($key) || !self::isAllowedKey($key)) {
                    continue;
                }
                // If the browser sent back the mask sentinel for a sensitive key, the user
                // did not touch that field — preserve the existing secret unchanged.
                if (self::isMaskedSentinel($key, $value)) {
                    continue;
                }
                $envVars[$key] = self::sanitizeValue($value);
            }

            $newContent = self::buildEnvContent($envVars);

            // Backup current file
            if (file_exists(self::$envFilePath)) {
                if (!copy(self::$envFilePath, self::$envFilePath . '.backup')) {
                    error_log('EnvConfig: failed to create .env.backup — proceeding with save anyway');
                }
            }

            if (file_put_contents(self::$envFilePath, $newContent) === false) {
                throw new \RuntimeException('Failed to write .env file');
            }

            // Apply to current process
            foreach ($newConfig as $key => $value) {
                if (!self::isValidEnvKey($key) || !self::isAllowedKey($key)) {
                    continue;
                }
                if (self::isMaskedSentinel($key, $value)) {
                    continue;
                }
                $clean = self::sanitizeValue($value);
                putenv("$key=$clean");
                $_ENV[$key] = $clean;
            }
            return true;
        } catch (\Throwable $e) {
            error_log('EnvConfig update error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Test if SRP_API_KEY is correctly set by hitting the local api.php endpoint.
     *
     * Two-strategy approach to maximise compatibility across hosting environments:
     *
     * Strategy 1 — Plain HTTP to 127.0.0.1 with a Host header.
     *   Avoids SSL/SNI entirely.  Works on any Apache/LiteSpeed server that has
     *   a port-80 listener, including cPanel shared hosting where the HTTPS vhost
     *   may not respond correctly to loopback CURLOPT_RESOLVE connections.
     *
     * Strategy 2 — HTTPS with CURLOPT_RESOLVE (fallback).
     *   Routes domain:port → 127.0.0.1 while preserving SNI + Host headers.
     *   SSL verification disabled — no MITM risk on loopback; cert may not cover
     *   every vhost alias.  Used when the server has no HTTP listener.
     *
     * @param string $apiKey   Value of SRP_API_KEY to test
     * @return array{success:bool,message:string,response?:mixed}
     */
    public static function testLocalApiKey(string $apiKey): array
    {
        // If the dashboard sent back the mask sentinel (key was never changed in the UI),
        // resolve it to the actual key stored in the environment.
        if ($apiKey === self::MASK_SENTINEL) {
            $apiKey = Environment::get('SRP_API_KEY');
        }

        if ($apiKey === '') {
            return ['success' => false, 'message' => 'API key is empty. Set SRP_API_KEY in .env first.'];
        }

        $appUrl = Environment::get('APP_URL');
        if ($appUrl === '') {
            return ['success' => false, 'message' => 'APP_URL is not set. Configure it in .env first.'];
        }

        // Parse APP_URL once — extract all components in a single call.
        $parsed   = parse_url($appUrl);
        $scheme   = (string)(($parsed['scheme'] ?? '') ?: 'https');
        $host     = (string)($parsed['host'] ?? '');
        if ($host === '') {
            return ['success' => false, 'message' => 'APP_URL is invalid (cannot parse host).'];
        }

        // Preserve sub-directory base path (e.g. APP_URL=https://host/app → /app).
        $basePath = rtrim((string)($parsed['path'] ?? ''), '/');
        $urlPort  = (int)(($parsed['port'] ?? 0) ?: ($scheme === 'https' ? 443 : 80));

        // Clean URL — routes through mod_rewrite → api.php with PATH_INFO=/status.
        $apiPath = $basePath . '/api/v1/status';
        $headers = ['X-API-Key: ' . $apiKey, 'X-Requested-With: XMLHttpRequest'];

        try {
            // ── Strategy 1: plain HTTP to 127.0.0.1 ─────────────────────────────
            [$code1, $err1, $body1] = self::curlGet(
                'http://127.0.0.1' . $apiPath,
                array_merge($headers, ['Host: ' . $host]),
            );

            if ($err1 === '') {
                if ($code1 === 200) {
                    return self::evaluateApiResponse($body1);
                }
                // Definitive auth rejection — key is wrong regardless of strategy.
                if ($code1 === 401 || $code1 === 403) {
                    return ['success' => false, 'message' => 'API key is invalid or not set correctly.'];
                }
                // Any other code (301 redirect, 404, 5xx) → fall through to strategy 2.
            }

            // ── Strategy 2: HTTPS with CURLOPT_RESOLVE ───────────────────────────
            $httpsUrl = $scheme . '://' . $host
                . ($urlPort !== 443 && $urlPort !== 80 ? ':' . $urlPort : '')
                . $apiPath;

            [$code2, $err2, $body2] = self::curlGet(
                $httpsUrl,
                $headers,
                [
                    CURLOPT_RESOLVE        => ["{$host}:{$urlPort}:127.0.0.1"],
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                ],
            );

            if ($err2 !== '') {
                return self::fallbackKeyCompare($apiKey, 'cURL error: ' . $err2);
            }
            if ($code2 === 401 || $code2 === 403) {
                return ['success' => false, 'message' => 'API key is invalid or not set correctly.'];
            }
            if ($code2 !== 200) {
                // Strategy 3: HTTP routing unavailable (loopback 404/5xx).
                // Fall back to direct in-process key comparison.
                return self::fallbackKeyCompare($apiKey, "HTTP {$code2} from loopback");
            }

            return self::evaluateApiResponse($body2);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Execute a GET request and return [httpCode, curlError, responseBody].
     *
     * @param  list<string>   $headers    HTTP headers (each as "Name: value")
     * @param  array<int,mixed> $extraOpts  Additional CURLOPT_* constants to merge in
     * @return array{0:int,1:string,2:string}
     */
    private static function curlGet(string $url, array $headers, array $extraOpts = []): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return [0, 'curl_init() failed', ''];
        }
        curl_setopt_array($ch, array_replace([
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 3,
        ], $extraOpts));
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);
        return [$httpCode, $curlErr, is_string($response) ? $response : ''];
    }

    /**
     * Strategy 3: compare the provided key directly against SRP_API_KEY from env.
     * Used when loopback HTTP requests fail (404, cURL error, firewall, etc.).
     *
     * @return array{success:bool,message:string}
     */
    private static function fallbackKeyCompare(string $apiKey, string $httpReason): array
    {
        $storedKey = Environment::get('SRP_API_KEY');
        if ($storedKey !== '' && hash_equals($storedKey, $apiKey)) {
            return ['success' => true, 'message' => 'API key is valid.'];
        }

        return ['success' => false, 'message' => "API key test failed ({$httpReason}). Key does not match stored SRP_API_KEY."];
    }

    /**
     * Parse a 200 response body from the status endpoint.
     *
     * @return array{success:bool,message:string,response?:mixed}
     */
    private static function evaluateApiResponse(string $body): array
    {
        $data = json_decode($body, true);
        if (!is_array($data) || empty($data['ok'])) {
            return ['success' => false, 'message' => 'Unexpected response from the API endpoint.'];
        }
        return [
            'success'  => true,
            'message'  => 'API key valid. Local API reachable.',
            'response' => $data['data'] ?? $data,
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────

    /**
     * @return array<string,string>
     */
    private static function parseEnvFile(string $content): array
    {
        $vars = [];

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key   = trim($key);
                $value = trim($value, " \t\"'");
                if ($key !== '') {
                    $vars[$key] = $value;
                }
            }
        }

        return $vars;
    }

    /**
     * @param array<string,string> $vars
     */
    private static function buildEnvContent(array $vars): string
    {
        $ts = date('Y-m-d H:i:s');

        $c  = "# ===================================================================\n";
        $c .= "# Hantuin-v2 Environment Configuration\n";
        $c .= "# Last updated: {$ts}\n";
        $c .= "# ===================================================================\n\n";

        $c .= "# ── Application ────────────────────────────────────────────────────\n";
        $c .= 'APP_URL='      . ($vars['APP_URL']      ?? '')           . "\n";
        $c .= 'APP_ENV='      . ($vars['APP_ENV']      ?? 'production') . "\n";
        $c .= 'APP_DEBUG='    . ($vars['APP_DEBUG']    ?? 'false')      . "\n";
        $c .= 'SRP_ENV='      . ($vars['SRP_ENV']      ?? 'production') . "\n";
        $c .= 'SRP_ENV_FILE=' . ($vars['SRP_ENV_FILE'] ?? '')           . "\n\n";

        $c .= "# ── Database ───────────────────────────────────────────────────────\n";
        $c .= 'SRP_DB_HOST='   . ($vars['SRP_DB_HOST']   ?? '127.0.0.1') . "\n";
        $c .= 'SRP_DB_PORT='   . ($vars['SRP_DB_PORT']   ?? '3306')       . "\n";
        $c .= 'SRP_DB_NAME='   . ($vars['SRP_DB_NAME']   ?? '')           . "\n";
        $c .= 'SRP_DB_USER='   . ($vars['SRP_DB_USER']   ?? '')           . "\n";
        $c .= 'SRP_DB_PASS='   . ($vars['SRP_DB_PASS']   ?? '')           . "\n";
        $c .= 'SRP_DB_SOCKET=' . ($vars['SRP_DB_SOCKET'] ?? '')           . "\n\n";

        $c .= "# ── API Keys ───────────────────────────────────────────────────────\n";
        $c .= 'SRP_API_KEY=' . ($vars['SRP_API_KEY'] ?? '') . "\n\n";
        $c .= "# Remote Decision Server (S2S)\n";
        $c .= 'SRP_REMOTE_DECISION_URL=' . ($vars['SRP_REMOTE_DECISION_URL'] ?? '') . "\n";
        $c .= 'SRP_REMOTE_API_KEY='      . ($vars['SRP_REMOTE_API_KEY']      ?? '') . "\n\n";

        $c .= "# ── API Client Tuning ──────────────────────────────────────────────\n";
        $c .= 'SRP_API_TIMEOUT='                . ($vars['SRP_API_TIMEOUT']                ?? '8')    . "\n";
        $c .= 'SRP_API_CONNECT_TIMEOUT='        . ($vars['SRP_API_CONNECT_TIMEOUT']        ?? '3')    . "\n";
        $c .= 'SRP_API_FAILURE_COOLDOWN='       . ($vars['SRP_API_FAILURE_COOLDOWN']       ?? '30')   . "\n";
        $c .= 'SRP_API_MAX_RETRIES='            . ($vars['SRP_API_MAX_RETRIES']            ?? '0')    . "\n";
        $c .= 'SRP_API_BACKOFF_BASE_MS='        . ($vars['SRP_API_BACKOFF_BASE_MS']        ?? '250')  . "\n";
        $c .= 'SRP_API_BACKOFF_MAX_MS='         . ($vars['SRP_API_BACKOFF_MAX_MS']         ?? '1500') . "\n";
        $c .= 'SRP_API_RESPONSE_CACHE_SECONDS=' . ($vars['SRP_API_RESPONSE_CACHE_SECONDS'] ?? '3')    . "\n";
        $c .= 'SRP_API_INFLIGHT_WAIT_MS='       . ($vars['SRP_API_INFLIGHT_WAIT_MS']       ?? '300')  . "\n\n";

        $c .= "# ── VPN Check ─────────────────────────────────────────────────────\n";
        $c .= 'SRP_VPN_CHECK_ENABLED=' . ($vars['SRP_VPN_CHECK_ENABLED'] ?? '1') . "\n\n";

        $c .= "# ── Rate Limiting ──────────────────────────────────────────────────\n";
        $c .= 'SRP_PUBLIC_API_RATE_WINDOW='    . ($vars['SRP_PUBLIC_API_RATE_WINDOW']    ?? '60')   . "\n";
        $c .= 'SRP_PUBLIC_API_RATE_MAX='       . ($vars['SRP_PUBLIC_API_RATE_MAX']       ?? '1000') . "\n";
        $c .= 'SRP_PUBLIC_API_RATE_HEAVY_MAX=' . ($vars['SRP_PUBLIC_API_RATE_HEAVY_MAX'] ?? '30')   . "\n";
        $c .= 'RATE_LIMIT_ATTEMPTS='           . ($vars['RATE_LIMIT_ATTEMPTS']           ?? '5')    . "\n";
        $c .= 'RATE_LIMIT_WINDOW='             . ($vars['RATE_LIMIT_WINDOW']             ?? '900')  . "\n\n";

        $c .= "# ── Admin Credentials ──────────────────────────────────────────────\n";
        $c .= 'SRP_ADMIN_USER='          . ($vars['SRP_ADMIN_USER']          ?? 'admin') . "\n";
        $c .= 'SRP_ADMIN_PASSWORD_HASH=' . ($vars['SRP_ADMIN_PASSWORD_HASH'] ?? '')      . "\n";
        $c .= 'SRP_ADMIN_PASSWORD='      . ($vars['SRP_ADMIN_PASSWORD']      ?? '')      . "\n\n";
        $c .= 'SRP_USER_USER='           . ($vars['SRP_USER_USER']           ?? '')      . "\n";
        $c .= 'SRP_USER_PASSWORD_HASH='  . ($vars['SRP_USER_PASSWORD_HASH']  ?? '')      . "\n";
        $c .= 'SRP_USER_PASSWORD='       . ($vars['SRP_USER_PASSWORD']       ?? '')      . "\n\n";

        $c .= "# ── Security ───────────────────────────────────────────────────────\n";
        $c .= 'SRP_TRUSTED_PROXIES='      . ($vars['SRP_TRUSTED_PROXIES']      ?? '')     . "\n";
        $c .= 'SRP_FORCE_SECURE_COOKIES=' . ($vars['SRP_FORCE_SECURE_COOKIES'] ?? 'true') . "\n\n";

        $c .= "# ── Cache ──────────────────────────────────────────────────────────\n";
        $c .= 'CACHE_DRIVER='    . ($vars['CACHE_DRIVER']    ?? '')          . "\n";
        $c .= 'CACHE_PREFIX='    . ($vars['CACHE_PREFIX']    ?? 'srp_')      . "\n";
        $c .= 'REDIS_HOST='      . ($vars['REDIS_HOST']      ?? '127.0.0.1') . "\n";
        $c .= 'REDIS_PORT='      . ($vars['REDIS_PORT']      ?? '6379')      . "\n";
        $c .= 'REDIS_PASSWORD='  . ($vars['REDIS_PASSWORD']  ?? '')          . "\n";
        $c .= 'REDIS_DB='        . ($vars['REDIS_DB']        ?? '0')         . "\n";
        $c .= 'MEMCACHED_HOST='  . ($vars['MEMCACHED_HOST']  ?? '127.0.0.1') . "\n";
        $c .= 'MEMCACHED_PORT='  . ($vars['MEMCACHED_PORT']  ?? '11211')     . "\n\n";

        $c .= "# ── Session ────────────────────────────────────────────────────────\n";
        $c .= 'SESSION_LIFETIME=' . ($vars['SESSION_LIFETIME'] ?? '3600') . "\n";

        // Preserve any unknown keys that were already in the file
        $standardKeys = [
            'APP_URL', 'APP_ENV', 'APP_DEBUG', 'SRP_ENV', 'SRP_ENV_FILE',
            'SRP_DB_HOST', 'SRP_DB_PORT', 'SRP_DB_NAME', 'SRP_DB_USER', 'SRP_DB_PASS', 'SRP_DB_SOCKET',
            'SRP_API_KEY',
            'SRP_REMOTE_DECISION_URL', 'SRP_REMOTE_API_KEY',
            'SRP_API_TIMEOUT', 'SRP_API_CONNECT_TIMEOUT', 'SRP_API_FAILURE_COOLDOWN',
            'SRP_API_MAX_RETRIES', 'SRP_API_BACKOFF_BASE_MS', 'SRP_API_BACKOFF_MAX_MS',
            'SRP_API_RESPONSE_CACHE_SECONDS', 'SRP_API_INFLIGHT_WAIT_MS',
            'SRP_VPN_CHECK_ENABLED',
            'SRP_PUBLIC_API_RATE_WINDOW', 'SRP_PUBLIC_API_RATE_MAX', 'SRP_PUBLIC_API_RATE_HEAVY_MAX',
            'RATE_LIMIT_ATTEMPTS', 'RATE_LIMIT_WINDOW',
            'SRP_ADMIN_USER', 'SRP_ADMIN_PASSWORD_HASH', 'SRP_ADMIN_PASSWORD',
            'SRP_USER_USER', 'SRP_USER_PASSWORD_HASH', 'SRP_USER_PASSWORD',
            'SRP_TRUSTED_PROXIES', 'SRP_FORCE_SECURE_COOKIES',
            'CACHE_DRIVER', 'CACHE_PREFIX',
            'REDIS_HOST', 'REDIS_PORT', 'REDIS_PASSWORD', 'REDIS_DB',
            'MEMCACHED_HOST', 'MEMCACHED_PORT',
            'SESSION_LIFETIME',
        ];

        $extra = array_diff_key($vars, array_flip($standardKeys));
        if (!empty($extra)) {
            $c .= "\n# Other Configuration\n";
            foreach ($extra as $k => $v) {
                $c .= "$k=$v\n";
            }
        }

        return $c;
    }

    private static function isMaskedSentinel(string $key, string $value): bool
    {
        return in_array($key, self::SENSITIVE_KEYS, true) && $value === self::MASK_SENTINEL;
    }

    private static function isValidEnvKey(string $key): bool
    {
        return (bool) preg_match('/^[A-Z_][A-Z0-9_]*$/', $key);
    }

    private static function sanitizeValue(string $value): string
    {
        return str_replace(["\r\n", "\r", "\n"], '', $value);
    }

    private static function maskValue(string $value): string
    {
        if ($value === '') {
            return '';
        }
        return self::MASK_SENTINEL;
    }

    private static function isAllowedKey(string $key): bool
    {
        foreach (self::ALLOWED_KEY_PREFIXES as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get configuration groups for dashboard UI.
     *
     * @return array<string,mixed>
     */
    public static function getConfigGroups(): array
    {
        $all = self::getAllMasked();

        return [
            'api' => [
                'label' => 'Public API',
                'icon'  => 'api',
                'fields' => [
                    'SRP_API_KEY' => [
                        'label' => 'API Key', 'type' => 'password',
                        'value' => $all['SRP_API_KEY'], 'placeholder' => 'openssl rand -hex 32',
                    ],
                    'SRP_PUBLIC_API_RATE_WINDOW' => [
                        'label' => 'Rate Window (seconds)', 'type' => 'number',
                        'value' => $all['SRP_PUBLIC_API_RATE_WINDOW'], 'placeholder' => '60',
                    ],
                    'SRP_PUBLIC_API_RATE_MAX' => [
                        'label' => 'Rate Max Requests', 'type' => 'number',
                        'value' => $all['SRP_PUBLIC_API_RATE_MAX'], 'placeholder' => '120',
                    ],
                ],
            ],
            'remote' => [
                'label' => 'Remote Decision Server',
                'icon'  => 'link',
                'fields' => [
                    'SRP_REMOTE_DECISION_URL' => [
                        'label' => 'Decision URL', 'type' => 'url',
                        'value' => $all['SRP_REMOTE_DECISION_URL'], 'placeholder' => 'https://trackng.us/api/v1/decision',
                    ],
                    'SRP_REMOTE_API_KEY' => [
                        'label' => 'Remote API Key', 'type' => 'password',
                        'value' => $all['SRP_REMOTE_API_KEY'], 'placeholder' => '••••••••',
                    ],
                    'SRP_API_TIMEOUT' => [
                        'label' => 'Timeout (seconds)', 'type' => 'number',
                        'value' => $all['SRP_API_TIMEOUT'], 'placeholder' => '10',
                    ],
                    'SRP_API_CONNECT_TIMEOUT' => [
                        'label' => 'Connect Timeout (seconds)', 'type' => 'number',
                        'value' => $all['SRP_API_CONNECT_TIMEOUT'], 'placeholder' => '3',
                    ],
                    'SRP_API_FAILURE_COOLDOWN' => [
                        'label' => 'Failure Cooldown (seconds)', 'type' => 'number',
                        'value' => $all['SRP_API_FAILURE_COOLDOWN'], 'placeholder' => '30',
                    ],
                    'SRP_API_MAX_RETRIES' => [
                        'label' => 'Max Retries', 'type' => 'number',
                        'value' => $all['SRP_API_MAX_RETRIES'], 'placeholder' => '0',
                    ],
                    'SRP_API_RESPONSE_CACHE_SECONDS' => [
                        'label' => 'Response Cache (seconds)', 'type' => 'number',
                        'value' => $all['SRP_API_RESPONSE_CACHE_SECONDS'], 'placeholder' => '3',
                    ],
                ],
            ],
            'admin' => [
                'label' => 'Admin Credentials',
                'icon'  => 'user',
                'fields' => [
                    'SRP_ADMIN_USER' => [
                        'label' => 'Username', 'type' => 'text',
                        'value' => $all['SRP_ADMIN_USER'], 'placeholder' => 'admin',
                    ],
                    'SRP_ADMIN_PASSWORD_HASH' => [
                        'label' => 'Password Hash (bcrypt)', 'type' => 'password',
                        'value' => $all['SRP_ADMIN_PASSWORD_HASH'],
                        'placeholder' => 'php -r "echo password_hash(\'pass\', PASSWORD_DEFAULT);"',
                    ],
                ],
            ],
            'application' => [
                'label' => 'Application',
                'icon'  => 'settings',
                'fields' => [
                    'APP_URL' => [
                        'label' => 'Application URL', 'type' => 'url',
                        'value' => $all['APP_URL'], 'placeholder' => 'https://trackng.us',
                    ],
                    'APP_ENV' => [
                        'label' => 'Environment', 'type' => 'select',
                        'value' => $all['APP_ENV'],
                        'options' => [
                            'production'  => 'Production',
                            'staging'     => 'Staging',
                            'development' => 'Development',
                        ],
                    ],
                    'APP_DEBUG' => [
                        'label' => 'Debug Mode', 'type' => 'select',
                        'value' => $all['APP_DEBUG'],
                        'options' => ['false' => 'Disabled', 'true' => 'Enabled'],
                    ],
                    'SESSION_LIFETIME' => [
                        'label' => 'Session Lifetime (seconds)', 'type' => 'number',
                        'value' => $all['SESSION_LIFETIME'], 'placeholder' => '3600',
                    ],
                ],
            ],
            'security' => [
                'label' => 'Security',
                'icon'  => 'shield',
                'fields' => [
                    'SRP_TRUSTED_PROXIES' => [
                        'label' => 'Trusted Proxies (comma-separated CIDRs)', 'type' => 'text',
                        'value' => $all['SRP_TRUSTED_PROXIES'], 'placeholder' => '173.245.48.0/20,103.21.244.0/22',
                    ],
                    'SRP_FORCE_SECURE_COOKIES' => [
                        'label' => 'Force Secure Cookies', 'type' => 'select',
                        'value' => $all['SRP_FORCE_SECURE_COOKIES'],
                        'options' => ['true' => 'Enabled (HTTPS only)', 'false' => 'Disabled'],
                    ],
                    'SRP_VPN_CHECK_ENABLED' => [
                        'label' => 'VPN Check', 'type' => 'select',
                        'value' => $all['SRP_VPN_CHECK_ENABLED'],
                        'options' => ['1' => 'Enabled', '0' => 'Disabled'],
                    ],
                    'RATE_LIMIT_ATTEMPTS' => [
                        'label' => 'Login Rate Limit Attempts', 'type' => 'number',
                        'value' => $all['RATE_LIMIT_ATTEMPTS'], 'placeholder' => '5',
                    ],
                    'RATE_LIMIT_WINDOW' => [
                        'label' => 'Login Rate Limit Window (seconds)', 'type' => 'number',
                        'value' => $all['RATE_LIMIT_WINDOW'], 'placeholder' => '900',
                    ],
                    'SRP_PUBLIC_API_RATE_HEAVY_MAX' => [
                        'label' => 'Heavy Endpoint Rate Limit', 'type' => 'number',
                        'value' => $all['SRP_PUBLIC_API_RATE_HEAVY_MAX'], 'placeholder' => '30',
                    ],
                ],
            ],
            'cache' => [
                'label' => 'Cache',
                'icon'  => 'database',
                'fields' => [
                    'CACHE_DRIVER' => [
                        'label' => 'Cache Driver', 'type' => 'select',
                        'value' => $all['CACHE_DRIVER'],
                        'options' => [
                            ''          => 'Auto-detect',
                            'redis'     => 'Redis',
                            'memcached' => 'Memcached',
                            'apcu'      => 'APCu',
                            'none'      => 'None',
                        ],
                    ],
                    'CACHE_PREFIX' => [
                        'label' => 'Cache Key Prefix', 'type' => 'text',
                        'value' => $all['CACHE_PREFIX'], 'placeholder' => 'srp_',
                    ],
                    'REDIS_HOST' => [
                        'label' => 'Redis Host', 'type' => 'text',
                        'value' => $all['REDIS_HOST'], 'placeholder' => '127.0.0.1',
                    ],
                    'REDIS_PORT' => [
                        'label' => 'Redis Port', 'type' => 'number',
                        'value' => $all['REDIS_PORT'], 'placeholder' => '6379',
                    ],
                    'REDIS_PASSWORD' => [
                        'label' => 'Redis Password', 'type' => 'password',
                        'value' => $all['REDIS_PASSWORD'], 'placeholder' => '',
                    ],
                    'REDIS_DB' => [
                        'label' => 'Redis DB', 'type' => 'number',
                        'value' => $all['REDIS_DB'], 'placeholder' => '0',
                    ],
                ],
            ],
        ];
    }
}

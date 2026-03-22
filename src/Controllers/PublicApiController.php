<?php

declare(strict_types=1);

namespace SRP\Controllers;

use SRP\Config\Cache;
use SRP\Config\Environment;
use SRP\Models\AuditLog;
use SRP\Models\Conversion;
use SRP\Models\Settings;
use SRP\Models\TrafficLog;

/**
 * Public REST API v1
 *
 * Authentication : X-API-Key: <SRP_API_KEY>  (header only)
 *
 * Base URL  :  /api/v1/
 * Endpoints :
 *   GET  status         system on/off, redirect URL, filter config
 *   GET  stats          weekly traffic + conversion totals
 *   GET  logs           paginated traffic logs  (?limit=50&page=1)
 *   GET  analytics      daily breakdown          (?days=30)
 *   GET  conversions    paginated conversions    (?limit=30&page=1)
 *   POST settings       partial-update settings  (JSON body)
 *   POST decision       routing decision + traffic log
 */
class PublicApiController
{
    private const MAX_LIMIT        = 200;
    private const RATE_WINDOW_DEFAULT = 60;   // seconds
    private const RATE_WINDOW_MIN     = 1;
    private const RATE_WINDOW_MAX     = 3600;
    private const RATE_MAX_DEFAULT    = 120;  // requests per window per IP (realtime)
    private const RATE_MAX_MIN        = 10;
    private const RATE_MAX_MAX        = 10000;
    private const RATE_HEAVY_DEFAULT  = 30;   // requests per window for heavy endpoints
    private const API_VERSION         = '1.0';

    // Response cache TTL (seconds) for read-only endpoints
    private const STATUS_CACHE_TTL    = 5;
    private const STATS_CACHE_TTL     = 10;

    // Heavy endpoints generate significant DB load — they get a tighter rate limit
    private const HEAVY_ENDPOINTS = ['logs', 'analytics', 'conversions'];

    /** Unique request identifier — generated once per request, emitted in X-Request-ID header. */
    private static string $requestId = '';

    // ── Entry point ───────────────────────────────────────

    public static function handle(): void
    {
        self::setCorsHeaders();

        // Generate or propagate a request ID for end-to-end tracing
        self::$requestId = trim($_SERVER['HTTP_X_REQUEST_ID'] ?? '') ?: bin2hex(random_bytes(8));
        header('X-Request-ID: ' . self::$requestId);

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Preflight
        if ($method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        header('X-API-Version: ' . self::API_VERSION);

        // Auth
        if (!self::authenticate()) {
            self::abort(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        $endpoint = self::parseEndpoint();
        $rateWindow = self::getRateWindow();

        // Rate limit
        if (!self::rateLimit($endpoint, $rateWindow)) {
            header('Retry-After: ' . $rateWindow);
            self::abort(['ok' => false, 'error' => 'Too Many Requests'], 429);
        }

        match (true) {
            $endpoint === 'status'      && $method === 'GET'  => self::getStatus(),
            $endpoint === 'stats'       && $method === 'GET'  => self::getStats(),
            $endpoint === 'logs'        && $method === 'GET'  => self::getLogs(),
            $endpoint === 'analytics'   && $method === 'GET'  => self::getAnalytics(),
            $endpoint === 'conversions' && $method === 'GET'  => self::getConversions(),
            $endpoint === 'settings'    && $method === 'POST' => self::postSettings(),
            $endpoint === 'decision'    && $method === 'POST' => self::postDecision(),
            $endpoint === ''            && $method === 'GET'  => self::getIndex(),
            default => self::abort(['ok' => false, 'error' => 'Not Found'], 404),
        };
    }

    // ── Route handlers ────────────────────────────────────

    /** GET /api/v1/ — API index / health check */
    private static function getIndex(): never
    {
        self::json([
            'ok'      => true,
            'service' => 'SRP Public API',
            'version' => self::API_VERSION,
            'endpoints' => [
                'GET  /api/v1/status',
                'GET  /api/v1/stats',
                'GET  /api/v1/logs?limit=50&page=1',
                'GET  /api/v1/analytics?days=30',
                'GET  /api/v1/conversions?limit=30&page=1',
                'POST /api/v1/settings',
                'POST /api/v1/decision',
            ],
        ]);
    }

    /**
     * GET /api/v1/status
     * Returns current system state (read-only config snapshot). Cached briefly.
     */
    private static function getStatus(): never
    {
        $cacheKey = 'pub_api_status';
        $cached   = Cache::get($cacheKey);
        if (is_array($cached)) {
            self::json($cached);
        }

        $cfg     = Settings::get();
        $payload = [
            'ok'   => true,
            'data' => [
                'system_on'           => (bool) $cfg['system_on'],
                'redirect_url'        => (string) $cfg['redirect_url'],
                'country_filter_mode' => (string) $cfg['country_filter_mode'],
                'country_filter_list' => (string) $cfg['country_filter_list'],
                'updated_at'          => (int) $cfg['updated_at'],
            ],
        ];
        Cache::set($cacheKey, $payload, self::STATUS_CACHE_TTL);
        self::json($payload);
    }

    /**
     * GET /api/v1/stats
     * Weekly traffic totals + weekly conversion totals. Cached briefly.
     */
    private static function getStats(): never
    {
        $cacheKey = 'pub_api_stats';
        $cached   = Cache::get($cacheKey);
        if (is_array($cached)) {
            self::json($cached);
        }

        $weekly  = TrafficLog::getWeeklyStats();
        $weekCnv = Conversion::getWeeklyStats();

        $payload = [
            'ok'   => true,
            'data' => [
                'traffic'     => [
                    'total'   => $weekly['total'],
                    'a_count' => $weekly['a_count'],
                    'b_count' => $weekly['b_count'],
                    'since'   => $weekly['since'],
                ],
                'conversions' => [
                    'total'        => $weekCnv['total'],
                    'total_payout' => $weekCnv['total_payout'],
                ],
            ],
        ];
        Cache::set($cacheKey, $payload, self::STATS_CACHE_TTL);
        self::json($payload);
    }

    /**
     * GET /api/v1/logs?limit=50&page=1
     * Paginated traffic logs, newest first.
     */
    private static function getLogs(): never
    {
        $limit  = max(1, min(self::MAX_LIMIT, (int)($_GET['limit'] ?? 50)));
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $limit;

        $rows = TrafficLog::getPage($limit, $offset);

        self::json([
            'ok'   => true,
            'data' => $rows,
            'meta' => ['page' => $page, 'limit' => $limit, 'count' => count($rows)],
        ]);
    }

    /**
     * GET /api/v1/analytics?days=30
     * Daily aggregated traffic + conversion data.
     */
    private static function getAnalytics(): never
    {
        $days    = max(1, min(90, (int)($_GET['days'] ?? 30)));
        $traffic = TrafficLog::getDailyStats($days);

        // Merge conversion data
        $convMap = [];
        foreach (Conversion::getDailyStats($days) as $row) {
            $convMap[$row['day']] = $row;
        }
        foreach ($traffic as &$row) {
            $c = $convMap[$row['day']] ?? null;
            $row['conv_count']  = $c ? (int)$c['total'] : 0;
            $row['conv_payout'] = $c ? round((float)$c['total_payout'], 2) : 0.0;
        }
        unset($row);

        self::json(['ok' => true, 'data' => $traffic]);
    }

    /**
     * GET /api/v1/conversions?limit=30&page=1
     * Paginated inbound conversion records.
     */
    private static function getConversions(): never
    {
        $limit  = max(1, min(self::MAX_LIMIT, (int)($_GET['limit'] ?? 30)));
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $limit;

        $rows = Conversion::getPage($limit, $offset);

        self::json([
            'ok'   => true,
            'data' => $rows,
            'meta' => ['page' => $page, 'limit' => $limit, 'count' => count($rows)],
        ]);
    }

    /**
     * POST /api/v1/decision
     * Evaluate a routing decision and log the traffic entry.
     *
     * Request body (JSON):
     *   click_id      string  — tracking ID (alphanumeric, _ -)
     *   country_code  string  — ISO Alpha-2 country code
     *   user_agent    string  — raw UA string or shorthand (wap/web/mobile/desktop)
     *   ip_address    string  — visitor IP (IPv4 or IPv6)
     *   user_lp       string  — landing page / campaign identifier
     *
     * Response:
     *   { "ok": true, "decision": "A"|"B", "target": "https://..." }
     */
    private static function postDecision(): never
    {
        $raw = (string)file_get_contents('php://input');
        if (strlen($raw) > 10_240) {
            self::abort(['ok' => false, 'error' => 'Payload too large'], 413);
        }

        try {
            $input = json_decode($raw ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            self::abort(['ok' => false, 'error' => 'Invalid JSON body'], 400);
        }

        if (!is_array($input)) {
            self::abort(['ok' => false, 'error' => 'Expected JSON object'], 400);
        }

        try {
            $result = DecisionController::resolve($input);
        } catch (\InvalidArgumentException $e) {
            self::abort(['ok' => false, 'error' => $e->getMessage()], 400);
        }

        // Output response before post-response work
        http_response_code(200);
        echo json_encode(
            [
                'ok'       => true,
                'decision' => $result['decision'],
                'target'   => $result['target'],
                'reason'   => $result['reason'],
                'ts'       => time(),
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        // Log traffic entry — wrapped so a DB failure cannot corrupt the already-sent response
        try {
            TrafficLog::create([
                'ip'       => $result['ip'],
                'ua'       => $result['ua'],
                'cid'      => $result['cid'],
                'cc'       => $result['cc'],
                'lp'       => $result['lp'],
                'decision' => $result['decision'],
            ]);
        } catch (\Throwable $e) {
            error_log('PublicApiController: TrafficLog::create() failed — ' . $e->getMessage());
        }

        exit;
    }

    /**
     * POST /api/v1/settings
     * Partial-update system settings. Only provided fields are updated.
     *
     * Body (JSON, all fields optional):
     *   system_on           bool
     *   redirect_url        string  (must be HTTPS)
     *   country_filter_mode string  (all|whitelist|blacklist)
     *   country_filter_list string  (comma-separated ISO codes)
     *   postback_token      string
     */
    private static function postSettings(): never
    {
        $raw = (string)file_get_contents('php://input');

        if (strlen($raw) > 10_240) {
            self::abort(['ok' => false, 'error' => 'Payload too large'], 413);
        }

        try {
            $body = json_decode($raw ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            self::abort(['ok' => false, 'error' => 'Invalid JSON body'], 400);
        }

        if (!is_array($body) || empty($body)) {
            self::abort(['ok' => false, 'error' => 'Empty or non-object body'], 400);
        }

        // Merge provided fields over current values (partial update)
        $cfg = Settings::get();

        try {
            Settings::update(
                isset($body['system_on']) ? (bool)$body['system_on'] : (bool)$cfg['system_on'],
                self::readBodyString($body, 'redirect_url', $cfg['redirect_url']),
                isset($body['country_filter_mode'])
                    ? self::readBodyString($body, 'country_filter_mode', $cfg['country_filter_mode'])
                    : $cfg['country_filter_mode'],
                isset($body['country_filter_list'])
                    ? self::readBodyString($body, 'country_filter_list', $cfg['country_filter_list'])
                    : $cfg['country_filter_list'],
                self::readBodyString($body, 'postback_url', $cfg['postback_url']),
                self::readBodyString($body, 'postback_token', $cfg['postback_token']),
            );
        } catch (\InvalidArgumentException $e) {
            self::abort(['ok' => false, 'error' => $e->getMessage()], 400);
        }

        /** @var array<string,mixed> $changedKeys */
        $changedKeys = array_fill_keys(array_keys($body), true);
        AuditLog::record('settings_update', 'api', $changedKeys);

        self::json(['ok' => true]);
    }

    // ── Auth & rate-limit ─────────────────────────────────

    private static function authenticate(): bool
    {
        $configuredKey = Environment::get('SRP_API_KEY');
        if ($configuredKey === '') {
            return false;   // API key not configured on server
        }

        // Header only — query-param auth is intentionally not supported
        // because URL parameters appear in server access logs and proxy caches.
        $provided = trim($_SERVER['HTTP_X_API_KEY'] ?? '');

        return $provided !== '' && hash_equals($configuredKey, $provided);
    }

    private static function rateLimit(string $endpoint, int $rateWindow): bool
    {
        $ip      = self::getRateLimitClientIp();
        $rateMax = in_array($endpoint, self::HEAVY_ENDPOINTS, true)
            ? self::getRateHeavyMax()
            : self::getRateMax();

        // Bare key (no srp_ prefix) — Cache layer prepends its own prefix
        $key   = 'pub_api_rl_' . md5($ip . '|' . $endpoint);
        $count = Cache::increment($key, $rateWindow);

        return $count <= $rateMax;
    }

    private static function getRateWindow(): int
    {
        return self::clampEnvInt(
            Environment::get('SRP_PUBLIC_API_RATE_WINDOW'),
            self::RATE_WINDOW_DEFAULT,
            self::RATE_WINDOW_MIN,
            self::RATE_WINDOW_MAX,
        );
    }

    private static function getRateMax(): int
    {
        return self::clampEnvInt(
            Environment::get('SRP_PUBLIC_API_RATE_MAX'),
            self::RATE_MAX_DEFAULT,
            self::RATE_MAX_MIN,
            self::RATE_MAX_MAX,
        );
    }

    private static function getRateHeavyMax(): int
    {
        return self::clampEnvInt(
            Environment::get('SRP_PUBLIC_API_RATE_HEAVY_MAX'),
            self::RATE_HEAVY_DEFAULT,
            self::RATE_MAX_MIN,
            self::RATE_MAX_MAX,
        );
    }

    private static function clampEnvInt(string $raw, int $default, int $min, int $max): int
    {
        $value = $default;

        if ($raw !== '' && ctype_digit($raw)) {
            $value = (int)$raw;
        }

        if ($value < $min) {
            return $min;
        }

        if ($value > $max) {
            return $max;
        }

        return $value;
    }

    private static function getRateLimitClientIp(): string
    {
        $remoteAddr = self::extractSingleIp((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($remoteAddr === null) {
            return '0.0.0.0';
        }

        if (!self::isTrustedProxySource($remoteAddr)) {
            return $remoteAddr;
        }

        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_TRUE_CLIENT_IP', 'HTTP_X_REAL_IP'] as $header) {
            $candidate = self::extractSingleIp((string)($_SERVER[$header] ?? ''));
            if ($candidate !== null) {
                return $candidate;
            }
        }

        $xff = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        $xffCandidate = self::extractForwardedForIp($xff);
        if ($xffCandidate !== null) {
            return $xffCandidate;
        }

        return $remoteAddr;
    }

    private static function extractForwardedForIp(string $headerValue): ?string
    {
        if ($headerValue === '') {
            return null;
        }

        $firstValid = null;

        foreach (explode(',', $headerValue) as $part) {
            $candidate = self::extractSingleIp($part);
            if ($candidate === null) {
                continue;
            }

            if (self::isPublicIp($candidate)) {
                return $candidate;
            }

            if ($firstValid === null) {
                $firstValid = $candidate;
            }
        }

        return $firstValid;
    }

    private static function extractSingleIp(string $rawValue): ?string
    {
        $value = trim($rawValue);
        if ($value === '' || strlen($value) > 128) {
            return null;
        }

        if (preg_match('/^\[(.+)\]:(\d{1,5})$/', $value, $matches) === 1) {
            $value = $matches[1];
        } elseif (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3}):\d{1,5}$/', $value, $matches) === 1) {
            $value = $matches[1];
        }

        if (filter_var($value, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return $value;
    }

    private static function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    /**
     * @param array<string,mixed> $body
     */
    private static function readBodyString(array $body, string $key, string $default): string
    {
        if (!array_key_exists($key, $body)) {
            return $default;
        }

        $value = $body[$key];
        if (!is_scalar($value) && $value !== null) {
            return $default;
        }

        return (string)$value;
    }

    private static function isTrustedProxySource(string $remoteAddr): bool
    {
        foreach (self::getTrustedProxyCidrs() as $cidr) {
            if (self::ipMatchesCidr($remoteAddr, $cidr)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function getTrustedProxyCidrs(): array
    {
        $defaultCidrs = [
            '127.0.0.1/8',
            '::1/128',
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            'fc00::/7',
        ];

        $configured = trim(Environment::get('SRP_TRUSTED_PROXIES'));
        if ($configured === '') {
            return $defaultCidrs;
        }

        $result = [];
        $entries = preg_split('/[\s,]+/', $configured);
        if ($entries === false) {
            return $defaultCidrs;
        }

        foreach ($entries as $entry) {
            $entry = trim((string)$entry);
            if ($entry === '') {
                continue;
            }
            $result[] = $entry;
        }

        if (empty($result)) {
            return $defaultCidrs;
        }

        return $result;
    }

    private static function ipMatchesCidr(string $ip, string $cidr): bool
    {
        $ipBin = inet_pton($ip);
        if ($ipBin === false) {
            return false;
        }

        $cidr = trim($cidr);
        if ($cidr === '') {
            return false;
        }

        if (!str_contains($cidr, '/')) {
            $exact = self::extractSingleIp($cidr);
            return $exact !== null && hash_equals($exact, $ip);
        }

        [$subnet, $prefixRaw] = explode('/', $cidr, 2);
        $subnet = trim($subnet);
        $prefixRaw = trim($prefixRaw);

        if ($prefixRaw === '' || ctype_digit($prefixRaw) === false) {
            return false;
        }

        $subnetBin = inet_pton($subnet);
        if ($subnetBin === false || strlen($subnetBin) !== strlen($ipBin)) {
            return false;
        }

        $maxPrefix = strlen($ipBin) * 8;
        $prefix = (int)$prefixRaw;
        if ($prefix < 0 || $prefix > $maxPrefix) {
            return false;
        }

        $fullBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;

        if ($fullBytes > 0) {
            if (substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
                return false;
            }
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
        return (ord($ipBin[$fullBytes]) & $mask) === (ord($subnetBin[$fullBytes]) & $mask);
    }

    // ── URL routing ───────────────────────────────────────

    /**
     * Extract the endpoint segment from the request.
     * Supports:
     *   PATH_INFO   →  /v1/status    or  /status
     *   REQUEST_URI →  /api/v1/status
     *   Query param →  ?endpoint=status  (last resort)
     */
    private static function parseEndpoint(): string
    {
        $pathInfo = trim($_SERVER['PATH_INFO'] ?? '', '/');

        if ($pathInfo !== '') {
            // Strip leading version prefix (v1/, v2/, …)
            $pathInfo = (string)preg_replace('#^v\d+/#i', '', $pathInfo);
            return strtolower(trim($pathInfo, '/'));
        }

        // Fallback: parse REQUEST_URI
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/api(?:\.php)?/v\d+/([^/?]+)#i', (string)$uri, $m)) {
            return strtolower(trim($m[1], '/'));
        }

        // Last resort: ?endpoint=
        return strtolower(trim((string)($_GET['endpoint'] ?? ''), '/'));
    }

    // ── Response helpers ──────────────────────────────────

    /** @return never */
    /**
     * @param array<string, mixed> $payload
     */
    private static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** @return never */
    /**
     * @param array<string, mixed> $payload
     */
    private static function abort(array $payload, int $status): never
    {
        self::json($payload, $status);
    }

    private static function setCorsHeaders(): void
    {
        // Public API — open to any origin (callers authenticate via API key)
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
        header('Access-Control-Max-Age: 86400');
    }
}

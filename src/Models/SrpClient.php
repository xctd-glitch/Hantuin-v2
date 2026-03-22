<?php

declare(strict_types=1);

namespace SRP\Models;

use SRP\Config\Cache;

class SrpClient
{
    private const DEFAULT_TIMEOUT_SECONDS = 8;
    private const DEFAULT_CONNECT_TIMEOUT_SECONDS = 3;
    private const DEFAULT_FAILURE_COOLDOWN_SECONDS = 30;
    private const DEFAULT_MAX_RETRIES = 0;
    private const DEFAULT_BACKOFF_BASE_MS = 250;
    private const DEFAULT_BACKOFF_MAX_MS = 1500;
    private const DEFAULT_RESPONSE_CACHE_SECONDS = 3;
    private const DEFAULT_INFLIGHT_WAIT_MS = 300;
    private const MIN_TIMEOUT_SECONDS = 1;
    private const MAX_TIMEOUT_SECONDS = 30;
    private const MIN_CONNECT_TIMEOUT_SECONDS = 1;
    private const MAX_CONNECT_TIMEOUT_SECONDS = 15;
    private const MIN_FAILURE_COOLDOWN_SECONDS = 0;
    private const MAX_FAILURE_COOLDOWN_SECONDS = 300;
    private const MIN_MAX_RETRIES = 0;
    private const MAX_MAX_RETRIES = 5;
    private const MIN_BACKOFF_BASE_MS = 50;
    private const MAX_BACKOFF_BASE_MS = 5000;
    private const MIN_BACKOFF_MAX_MS = 100;
    private const MAX_BACKOFF_MAX_MS = 10000;
    private const MIN_RESPONSE_CACHE_SECONDS = 0;
    private const MAX_RESPONSE_CACHE_SECONDS = 30;
    private const MIN_INFLIGHT_WAIT_MS = 0;
    private const MAX_INFLIGHT_WAIT_MS = 2000;

    private string $apiUrl;
    private string $apiKey;
    private bool $debugMode;
    private int $timeoutSeconds;
    private int $connectTimeoutSeconds;
    private int $failureCooldownSeconds;
    private int $maxRetries;
    private int $backoffBaseMs;
    private int $backoffMaxMs;
    private int $responseCacheSeconds;
    private int $inflightWaitMs;

    public function __construct(
        ?string $apiUrl = null,
        ?string $apiKey = null,
        bool $debugMode = false,
        ?int $timeoutSeconds = null,
        ?int $connectTimeoutSeconds = null,
        ?int $failureCooldownSeconds = null,
        ?int $maxRetries = null,
        ?int $backoffBaseMs = null,
        ?int $backoffMaxMs = null,
        ?int $responseCacheSeconds = null,
        ?int $inflightWaitMs = null,
    ) {
        $configuredApiUrl = trim((string)(getenv('SRP_REMOTE_DECISION_URL') ?: getenv('SRP_API_URL') ?: ''));
        if ($configuredApiUrl === '') {
            $configuredApiUrl = \SRP\Config\Environment::getAppUrl() . '/api/v1/decision';
        }

        $configuredApiKey = trim((string)(getenv('SRP_REMOTE_API_KEY') ?: getenv('SRP_API_KEY') ?: ''));

        $this->apiUrl    = $apiUrl ?? $configuredApiUrl;
        $this->apiKey    = $apiKey ?? $configuredApiKey;
        $this->debugMode = $debugMode;

        $configuredTimeoutSeconds = self::normalizeIntSetting(
            getenv('SRP_API_TIMEOUT'),
            self::DEFAULT_TIMEOUT_SECONDS,
            self::MIN_TIMEOUT_SECONDS,
            self::MAX_TIMEOUT_SECONDS,
        );
        $configuredConnectTimeoutSeconds = self::normalizeIntSetting(
            getenv('SRP_API_CONNECT_TIMEOUT'),
            self::DEFAULT_CONNECT_TIMEOUT_SECONDS,
            self::MIN_CONNECT_TIMEOUT_SECONDS,
            self::MAX_CONNECT_TIMEOUT_SECONDS,
        );
        $configuredFailureCooldownSeconds = self::normalizeIntSetting(
            getenv('SRP_API_FAILURE_COOLDOWN'),
            self::DEFAULT_FAILURE_COOLDOWN_SECONDS,
            self::MIN_FAILURE_COOLDOWN_SECONDS,
            self::MAX_FAILURE_COOLDOWN_SECONDS,
        );
        $configuredMaxRetries = self::normalizeIntSetting(
            getenv('SRP_API_MAX_RETRIES'),
            self::DEFAULT_MAX_RETRIES,
            self::MIN_MAX_RETRIES,
            self::MAX_MAX_RETRIES,
        );
        $configuredBackoffBaseMs = self::normalizeIntSetting(
            getenv('SRP_API_BACKOFF_BASE_MS'),
            self::DEFAULT_BACKOFF_BASE_MS,
            self::MIN_BACKOFF_BASE_MS,
            self::MAX_BACKOFF_BASE_MS,
        );
        $configuredBackoffMaxMs = self::normalizeIntSetting(
            getenv('SRP_API_BACKOFF_MAX_MS'),
            self::DEFAULT_BACKOFF_MAX_MS,
            self::MIN_BACKOFF_MAX_MS,
            self::MAX_BACKOFF_MAX_MS,
        );
        $configuredResponseCacheSeconds = self::normalizeIntSetting(
            getenv('SRP_API_RESPONSE_CACHE_SECONDS'),
            self::DEFAULT_RESPONSE_CACHE_SECONDS,
            self::MIN_RESPONSE_CACHE_SECONDS,
            self::MAX_RESPONSE_CACHE_SECONDS,
        );
        $configuredInflightWaitMs = self::normalizeIntSetting(
            getenv('SRP_API_INFLIGHT_WAIT_MS'),
            self::DEFAULT_INFLIGHT_WAIT_MS,
            self::MIN_INFLIGHT_WAIT_MS,
            self::MAX_INFLIGHT_WAIT_MS,
        );

        $this->timeoutSeconds = self::normalizeIntSetting(
            $timeoutSeconds,
            $configuredTimeoutSeconds,
            self::MIN_TIMEOUT_SECONDS,
            self::MAX_TIMEOUT_SECONDS,
        );
        $this->connectTimeoutSeconds = self::normalizeIntSetting(
            $connectTimeoutSeconds,
            $configuredConnectTimeoutSeconds,
            self::MIN_CONNECT_TIMEOUT_SECONDS,
            self::MAX_CONNECT_TIMEOUT_SECONDS,
        );
        $this->failureCooldownSeconds = self::normalizeIntSetting(
            $failureCooldownSeconds,
            $configuredFailureCooldownSeconds,
            self::MIN_FAILURE_COOLDOWN_SECONDS,
            self::MAX_FAILURE_COOLDOWN_SECONDS,
        );
        $this->maxRetries = self::normalizeIntSetting(
            $maxRetries,
            $configuredMaxRetries,
            self::MIN_MAX_RETRIES,
            self::MAX_MAX_RETRIES,
        );
        $this->backoffBaseMs = self::normalizeIntSetting(
            $backoffBaseMs,
            $configuredBackoffBaseMs,
            self::MIN_BACKOFF_BASE_MS,
            self::MAX_BACKOFF_BASE_MS,
        );
        $this->backoffMaxMs = self::normalizeIntSetting(
            $backoffMaxMs,
            $configuredBackoffMaxMs,
            self::MIN_BACKOFF_MAX_MS,
            self::MAX_BACKOFF_MAX_MS,
        );
        $this->responseCacheSeconds = self::normalizeIntSetting(
            $responseCacheSeconds,
            $configuredResponseCacheSeconds,
            self::MIN_RESPONSE_CACHE_SECONDS,
            self::MAX_RESPONSE_CACHE_SECONDS,
        );
        $this->inflightWaitMs = self::normalizeIntSetting(
            $inflightWaitMs,
            $configuredInflightWaitMs,
            self::MIN_INFLIGHT_WAIT_MS,
            self::MAX_INFLIGHT_WAIT_MS,
        );
    }

    /**
     * @param array<string, string> $params
     * @return array<string, mixed>|null
     */
    public function getDecision(array $params): ?array
    {
        if (empty($this->apiKey)) {
            $this->debugLog('API key not configured');
            return null;
        }

        foreach (['click_id', 'country_code', 'user_agent', 'ip_address'] as $field) {
            if (empty($params[$field])) {
                $this->debugLog("Missing required field: {$field}");
                return null;
            }
        }

        $responseCacheKey = $this->getResponseCacheKey($params);
        $cachedResponse = $this->readCachedDecision($responseCacheKey);
        if ($cachedResponse !== null) {
            $this->debugLog('Returning cached SRP decision');
            return $cachedResponse;
        }

        if ($this->isNetworkCooldownActive()) {
            $this->debugLog('Skipping SRP API call during cooldown', [
                'cooldown_seconds' => $this->failureCooldownSeconds,
            ]);
            return null;
        }

        $payload = json_encode($params, JSON_INVALID_UTF8_SUBSTITUTE);
        if ($payload === false) {
            $this->debugLog('Failed to encode SRP payload');
            return null;
        }

        $lockHandle = $this->acquireDecisionLock($responseCacheKey);

        try {
            $cachedResponse = $this->readCachedDecision($responseCacheKey);
            if ($cachedResponse !== null) {
                $this->debugLog('Returning cached SRP decision after wait');
                return $cachedResponse;
            }

            $attempt = 0;
            while (true) {
                $this->debugLog('Calling SRP API', [
                    'attempt' => $attempt + 1,
                    'max_retries' => $this->maxRetries,
                ] + $params);

                $result = $this->executeDecisionRequest($payload);

                if ($result['error'] !== '') {
                    if ($this->shouldRetryRequest(0, $attempt)) {
                        $this->sleepBeforeRetry($attempt, '');
                        $attempt += 1;
                        continue;
                    }

                    $this->activateNetworkCooldown();
                    $this->debugLog('cURL error', ['error' => $result['error']]);
                    return null;
                }

                if ($result['http_code'] !== 200) {
                    $retryAfterSeconds = $this->parseRetryAfterSeconds($result['retry_after']);
                    if ($this->shouldRetryRequest($result['http_code'], $attempt)) {
                        $this->sleepBeforeRetry($attempt, $result['retry_after']);
                        $attempt += 1;
                        continue;
                    }

                    if ($result['http_code'] === 429) {
                        $this->activateNetworkCooldown($retryAfterSeconds);
                    }

                    $this->debugLog('HTTP error', ['code' => $result['http_code']]);
                    return null;
                }

                $this->clearNetworkCooldown();

                $responseText = is_string($result['response']) ? $result['response'] : '';
                $data = json_decode($responseText, true);
                if (!is_array($data) || !isset($data['ok']) || !$data['ok']) {
                    $this->debugLog('Invalid API response', ['response' => $result['response']]);
                    return null;
                }

                $this->writeCachedDecision($responseCacheKey, $data);
                $this->debugLog('Decision received', ['decision' => $data['decision'] ?? '?']);
                return $data;
            }
        } finally {
            $this->releaseDecisionLock($lockHandle);
        }
    }

    /**
     * Returns true when the system is in a muted time slot (slots 2-4 of a 5-slot cycle).
     * Slots 0-1 = Decision A active (2 min), slots 2-4 = muted (3 min), repeating.
     */
    public static function isInMutedSlot(): bool
    {
        return ((int)(time() / 60) % 5) >= 2;
    }

    public static function getClientIP(): string
    {
        $remoteAddr = self::extractSingleIp((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($remoteAddr === null) {
            return '0.0.0.0';
        }

        if (!self::isTrustedProxySource($remoteAddr)) {
            return $remoteAddr;
        }

        $trustedHeaderSources = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_TRUE_CLIENT_IP',
            'HTTP_X_REAL_IP',
        ];

        foreach ($trustedHeaderSources as $header) {
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

        // Query string fallback — affiliate network S2S pass-through (?ipaddress= or ?ip_address=)
        $qsIp = trim((string)($_GET['ipaddress'] ?? $_GET['ip_address'] ?? ''));
        if ($qsIp !== '') {
            $qsCandidate = self::extractSingleIp($qsIp);
            if ($qsCandidate !== null) {
                return $qsCandidate;
            }
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

        $configured = trim((string)(getenv('SRP_TRUSTED_PROXIES') ?: ''));
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

    public static function detectDevice(string $userAgent): string
    {
        if (preg_match('~bot|crawl|spider|facebook|whatsapp|telegram~i', $userAgent)) {
            return 'bot';
        }

        if (preg_match('/tablet|ipad/i', $userAgent)) {
            return 'wap';
        }

        if (preg_match('/mobile|android|iphone|ipod|blackberry|iemobile|opera mini/i', $userAgent)) {
            return 'wap';
        }

        return 'web';
    }

    public static function getCountryCode(): string
    {
        // 1. CDN/proxy geo headers (trusted, set by infrastructure)
        $geoHeaders = [
            'HTTP_CF_IPCOUNTRY',        // Cloudflare
            'HTTP_X_VERCEL_IP_COUNTRY', // Vercel
            'HTTP_X_COUNTRY_CODE',      // KeyCDN, BunnyCDN, generic
            'HTTP_X_APPENGINE_COUNTRY', // Google App Engine
            'HTTP_X_GEO_COUNTRY',       // custom proxy setups
        ];
        foreach ($geoHeaders as $header) {
            $value = trim((string)($_SERVER[$header] ?? ''));
            if ($value !== '' && preg_match('/\A[A-Z]{2}\z/', strtoupper($value))) {
                $cc = strtoupper($value);
                if ($cc !== 'XX' && Validator::isValidCountryCode($cc)) {
                    return $cc;
                }
            }
        }

        // 2. Query string override (for testing or remote clients)
        $qsCountry = strtoupper(trim((string)($_GET['country_code'] ?? '')));
        if ($qsCountry !== '' && $qsCountry !== 'XX' && Validator::isValidCountryCode($qsCountry)) {
            return $qsCountry;
        }

        // 3. IP-based GeoIP lookup via ip-api.com (free, no key needed, max 45 req/min)
        $ip = self::getClientIP();
        if ($ip !== '' && $ip !== '0.0.0.0') {
            $cc = self::lookupCountryByIp($ip);
            if ($cc !== null) {
                return $cc;
            }
        }

        return 'XX';
    }

    /**
     * Resolve country code from IP via ip-api.com.
     * Returns null on failure. Uses APCu cache when available.
     */
    private static function lookupCountryByIp(string $ip): ?string
    {
        // Skip private/reserved IPs
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        $cacheKey = 'geo_' . md5($ip);
        $cached   = Cache::get($cacheKey);
        if (is_string($cached)) {
            return $cached !== '' ? $cached : null;
        }

        try {
            $ctx = stream_context_create([
                'http' => [
                    'timeout'       => 2,
                    'ignore_errors' => true,
                    'header'        => "User-Agent: Hantuin-v2/1.0\r\n",
                ],
            ]);
            $raw = @file_get_contents(
                'http://ip-api.com/json/' . urlencode($ip) . '?fields=status,countryCode',
                false,
                $ctx,
            );
        } catch (\Throwable) {
            $raw = false;
        }

        $cc = null;
        if ($raw !== false) {
            $data = json_decode($raw, true);
            if (
                is_array($data)
                && ($data['status'] ?? '') === 'success'
                && isset($data['countryCode'])
                && Validator::isValidCountryCode((string) $data['countryCode'])
            ) {
                $cc = strtoupper((string) $data['countryCode']);
            }
        }

        Cache::set($cacheKey, $cc ?? '', 3600);

        return $cc;
    }

    public static function getFallbackUrl(string $path = '/_meetups/'): string
    {
        $proto = $_SERVER['HTTP_X_FORWARDED_PROTO']
            ?? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
        $proto   = in_array($proto, ['http', 'https'], true) ? $proto : 'https';
        $rawHost = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
        $host    = preg_replace('/[^a-zA-Z0-9.\-:\[\]]/', '', $rawHost);
        $qs      = $_SERVER['QUERY_STRING'] ?? '';

        return $proto . '://' . $host . $path . ($qs !== '' ? '?' . $qs : '');
    }

    /**
     * @param array<string, mixed> $context
     */
    private function debugLog(string $message, array $context = []): void
    {
        if (!$this->debugMode) {
            return;
        }

        $encodedContext = json_encode($context);
        $suffix = !empty($context) && is_string($encodedContext)
            ? ' | ' . $encodedContext
            : '';
        error_log("[SRP Debug] {$message}{$suffix}");
    }

    private static function normalizeIntSetting(mixed $value, int $default, int $min, int $max): int
    {
        $normalized = $default;

        if (is_int($value)) {
            $normalized = $value;
        } elseif (is_string($value) && $value !== '' && ctype_digit($value)) {
            $normalized = (int)$value;
        }

        if ($normalized < $min) {
            return $min;
        }

        if ($normalized > $max) {
            return $max;
        }

        return $normalized;
    }

    private function isNetworkCooldownActive(): bool
    {
        if ($this->failureCooldownSeconds <= 0) {
            return false;
        }

        $currentTime = time();
        $cacheKey      = $this->getCooldownCacheKey();
        $cooldownUntil = self::normalizeEpochValue(Cache::get($cacheKey));

        if ($cooldownUntil <= 0) {
            $cooldownUntil = $this->readCooldownUntilFromFile();
            if ($cooldownUntil > $currentTime) {
                Cache::set($cacheKey, $cooldownUntil, $cooldownUntil - $currentTime);
            }
        }

        if ($cooldownUntil <= $currentTime) {
            $this->clearNetworkCooldown();
            return false;
        }

        return true;
    }

    private function activateNetworkCooldown(?int $seconds = null): void
    {
        $cooldownSeconds = $seconds ?? $this->failureCooldownSeconds;
        if ($cooldownSeconds <= 0) {
            return;
        }

        $cooldownUntil = time() + $cooldownSeconds;

        Cache::set($this->getCooldownCacheKey(), $cooldownUntil, $cooldownSeconds);
        $this->writeCooldownUntilToFile($cooldownUntil);
    }

    private function clearNetworkCooldown(): void
    {
        Cache::delete($this->getCooldownCacheKey());

        $cooldownFilePath = $this->getCooldownFilePath();
        if (is_file($cooldownFilePath)) {
            try {
                unlink($cooldownFilePath);
            } catch (\Throwable $e) {
                $this->debugLog('Failed to clear cooldown file', ['error' => $e->getMessage()]);
            }
        }
    }

    private static function normalizeEpochValue(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '' && ctype_digit($value)) {
            return (int)$value;
        }

        return 0;
    }

    private function getCooldownCacheKey(): string
    {
        return 'srp_api_cooldown_' . sha1($this->apiUrl);
    }

    private function getCooldownFilePath(): string
    {
        return dirname(__DIR__, 2) . '/logs/srp-api-cooldown-' . sha1($this->apiUrl) . '.json';
    }

    /**
     * @param array<string, string> $params
     */
    private function getResponseCacheKey(array $params): string
    {
        ksort($params);
        $encoded = json_encode($params, JSON_INVALID_UTF8_SUBSTITUTE);
        return sha1($this->apiUrl . '|' . ($encoded !== false ? $encoded : ''));
    }

    private function getDecisionCacheFilePath(string $cacheKey): string
    {
        return dirname(__DIR__, 2) . '/logs/srp-api-response-' . $cacheKey . '.json';
    }

    private function getDecisionLockFilePath(string $cacheKey): string
    {
        return dirname(__DIR__, 2) . '/logs/srp-api-inflight-' . $cacheKey . '.lock';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readCachedDecision(string $cacheKey): ?array
    {
        if ($this->responseCacheSeconds <= 0) {
            return null;
        }

        $cacheStoreKey = 'api_resp_' . $cacheKey;
        $cached        = Cache::get($cacheStoreKey);
        if (is_array($cached)) {
            return $cached;
        }

        $cacheFilePath = $this->getDecisionCacheFilePath($cacheKey);
        if (!is_file($cacheFilePath) || !is_readable($cacheFilePath)) {
            return null;
        }

        try {
            $rawContent = file_get_contents($cacheFilePath);
            if ($rawContent === false || $rawContent === '') {
                return null;
            }

            $decoded = json_decode($rawContent, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded) || !isset($decoded['until'], $decoded['data'])) {
                return null;
            }

            $expiresAt = self::normalizeEpochValue($decoded['until']);
            if ($expiresAt < time() || !is_array($decoded['data'])) {
                return null;
            }

            Cache::set($cacheStoreKey, $decoded['data'], max(1, $expiresAt - time()));

            return $decoded['data'];
        } catch (\Throwable $e) {
            $this->debugLog('Failed to read response cache', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeCachedDecision(string $cacheKey, array $data): void
    {
        if ($this->responseCacheSeconds <= 0) {
            return;
        }

        $expiresAt = time() + $this->responseCacheSeconds;

        Cache::set('api_resp_' . $cacheKey, $data, $this->responseCacheSeconds);

        $cacheFilePath = $this->getDecisionCacheFilePath($cacheKey);
        $cacheDirectory = dirname($cacheFilePath);
        if (!is_dir($cacheDirectory) || !is_writable($cacheDirectory)) {
            return;
        }

        try {
            $payload = json_encode(
                ['until' => $expiresAt, 'data' => $data],
                JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE,
            );
            file_put_contents($cacheFilePath, $payload, LOCK_EX);
        } catch (\Throwable $e) {
            $this->debugLog('Failed to write response cache', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @return resource|null
     */
    private function acquireDecisionLock(string $cacheKey)
    {
        if ($this->inflightWaitMs <= 0) {
            return null;
        }

        $lockFilePath = $this->getDecisionLockFilePath($cacheKey);
        $lockDirectory = dirname($lockFilePath);
        if (!is_dir($lockDirectory) || !is_writable($lockDirectory)) {
            return null;
        }

        $handle = fopen($lockFilePath, 'cb');
        if ($handle === false) {
            return null;
        }

        $deadline = microtime(true) + ($this->inflightWaitMs / 1000);

        do {
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                return $handle;
            }

            if ($this->readCachedDecision($cacheKey) !== null) {
                fclose($handle);
                return null;
            }

            usleep(50_000);
        } while (microtime(true) < $deadline);

        fclose($handle);
        return null;
    }

    /**
     * @param resource|null $handle
     */
    private function releaseDecisionLock($handle): void
    {
        if (!is_resource($handle)) {
            return;
        }

        try {
            flock($handle, LOCK_UN);
        } catch (\Throwable $e) {
            $this->debugLog('Failed to release decision lock', ['error' => $e->getMessage()]);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array{response:string|false,http_code:int,error:string,retry_after:string}
     */
    private function executeDecisionRequest(string $payload): array
    {
        $responseHeaders = [];
        $ch = curl_init($this->apiUrl);
        if ($ch === false) {
            return [
                'response' => false,
                'http_code' => 0,
                'error' => 'cURL init failed',
                'retry_after' => '',
            ];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-API-Key: ' . $this->apiKey,
                'User-Agent: SRP-Client/1.0',
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeoutSeconds,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => static function ($curlHandle, string $headerLine) use (&$responseHeaders): int {
                $length = strlen($headerLine);
                $parts = explode(':', $headerLine, 2);
                if (count($parts) === 2) {
                    $name = strtolower(trim($parts[0]));
                    $value = trim($parts[1]);
                    if ($name !== '') {
                        $responseHeaders[$name] = $value;
                    }
                }

                return $length;
            },
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $normalizedResponse = is_string($response) ? $response : false;

        return [
            'response' => $normalizedResponse,
            'http_code' => (int)$httpCode,
            'error' => $error !== '' ? $error : ($normalizedResponse === false ? 'request failed' : ''),
            'retry_after' => (string)($responseHeaders['retry-after'] ?? ''),
        ];
    }

    private function shouldRetryRequest(int $httpCode, int $attempt): bool
    {
        if ($attempt >= $this->maxRetries) {
            return false;
        }

        return in_array($httpCode, [0, 408, 429, 500, 502, 503, 504], true);
    }

    private function sleepBeforeRetry(int $attempt, string $retryAfterHeader): void
    {
        $delayMs = $this->getBackoffDelayMs($attempt, $retryAfterHeader);
        if ($delayMs <= 0) {
            return;
        }

        usleep($delayMs * 1000);
    }

    private function getBackoffDelayMs(int $attempt, string $retryAfterHeader): int
    {
        $retryAfterSeconds = $this->parseRetryAfterSeconds($retryAfterHeader);
        if ($retryAfterSeconds > 0) {
            return min($retryAfterSeconds * 1000, $this->backoffMaxMs);
        }

        $delayMs = $this->backoffBaseMs * (2 ** $attempt);
        if ($delayMs < $this->backoffBaseMs) {
            return $this->backoffBaseMs;
        }

        if ($delayMs > $this->backoffMaxMs) {
            return $this->backoffMaxMs;
        }

        return $delayMs;
    }

    private function parseRetryAfterSeconds(string $retryAfterHeader): int
    {
        $value = trim($retryAfterHeader);
        if ($value === '' || ctype_digit($value) === false) {
            return 0;
        }

        $seconds = (int)$value;
        if ($seconds < 1) {
            return 1;
        }

        if ($seconds > 3600) {
            return 3600;
        }

        return $seconds;
    }

    private function readCooldownUntilFromFile(): int
    {
        $cooldownFilePath = $this->getCooldownFilePath();
        if (!is_file($cooldownFilePath) || !is_readable($cooldownFilePath)) {
            return 0;
        }

        try {
            $rawContent = file_get_contents($cooldownFilePath);
            if ($rawContent === false || $rawContent === '') {
                return 0;
            }

            $decoded = json_decode($rawContent, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded) || !array_key_exists('until', $decoded)) {
                return 0;
            }

            return self::normalizeEpochValue($decoded['until']);
        } catch (\Throwable $e) {
            $this->debugLog('Failed to read cooldown file', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    private function writeCooldownUntilToFile(int $cooldownUntil): void
    {
        $cooldownFilePath = $this->getCooldownFilePath();
        $cooldownDirectory = dirname($cooldownFilePath);
        if (!is_dir($cooldownDirectory) || !is_writable($cooldownDirectory)) {
            return;
        }

        $handle = fopen($cooldownFilePath, 'cb');
        if ($handle === false) {
            return;
        }

        $lockAcquired = false;

        try {
            $lockAcquired = flock($handle, LOCK_EX);
            if (!$lockAcquired) {
                return;
            }

            $payload = json_encode(['until' => $cooldownUntil], JSON_THROW_ON_ERROR);

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, $payload);
            fflush($handle);
        } catch (\Throwable $e) {
            $this->debugLog('Failed to write cooldown file', ['error' => $e->getMessage()]);
        } finally {
            if ($lockAcquired) {
                flock($handle, LOCK_UN);
            }
            fclose($handle);
        }
    }
}

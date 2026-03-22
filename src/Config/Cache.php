<?php

declare(strict_types=1);

namespace SRP\Config;

/**
 * Unified cache facade: Redis → Memcached → APCu → no-op.
 *
 * Driver selection via CACHE_DRIVER env var:
 *   redis | memcached | apcu | none
 * When unset, auto-detects: Redis first, then APCu, then no-op.
 *
 * Env variables:
 *   CACHE_DRIVER      redis | memcached | apcu | none  (default: auto)
 *   CACHE_PREFIX      key prefix (default: srp_)
 *   REDIS_HOST        default: 127.0.0.1
 *   REDIS_PORT        default: 6379
 *   REDIS_PASSWORD    default: (empty)
 *   REDIS_DB          default: 0
 *   MEMCACHED_HOST    default: 127.0.0.1
 *   MEMCACHED_PORT    default: 11211
 */
class Cache
{
    private static bool $initialized = false;
    private static string $driver    = 'none';
    private static string $prefix    = 'srp_';

    /** @var \Redis|null */
    private static ?\Redis $redis = null;

    /** @var \Memcached|null */
    private static ?\Memcached $memcached = null;

    // ── Public API ─────────────────────────────────────────

    /**
     * Retrieve a cached value. Returns null when key is missing or driver is unavailable.
     */
    public static function get(string $key): mixed
    {
        self::init();
        $k = self::$prefix . $key;

        if (self::$driver === 'redis' && self::$redis !== null) {
            try {
                $raw = self::$redis->get($k);
                if ($raw === false) {
                    return null;
                }
                $decoded = unserialize((string) (is_string($raw) ? $raw : ''));
                return $decoded === false ? null : $decoded;
            } catch (\Throwable $e) {
                error_log('Cache: Redis get() failed — ' . $e->getMessage());
                return null;
            }
        }

        if (self::$driver === 'memcached' && self::$memcached !== null) {
            $val = self::$memcached->get($k);
            if (self::$memcached->getResultCode() !== \Memcached::RES_SUCCESS) {
                return null;
            }
            return $val;
        }

        if (self::$driver === 'apcu') {
            $hit = false;
            $val = apcu_fetch($k, $hit);
            return $hit ? $val : null;
        }

        return null;
    }

    /**
     * Store a value. Returns true on success.
     * $ttl = 0 means no expiry (driver-dependent: Redis/APCu honour it; Memcached ignores 0 = no expiry).
     */
    public static function set(string $key, mixed $value, int $ttl = 0): bool
    {
        self::init();
        $k = self::$prefix . $key;

        if (self::$driver === 'redis' && self::$redis !== null) {
            try {
                $serialized = serialize($value);
                if ($ttl > 0) {
                    return (bool) self::$redis->setex($k, $ttl, $serialized);
                }
                return (bool) self::$redis->set($k, $serialized);
            } catch (\Throwable $e) {
                error_log('Cache: Redis set() failed — ' . $e->getMessage());
                return false;
            }
        }

        if (self::$driver === 'memcached' && self::$memcached !== null) {
            return self::$memcached->set($k, $value, $ttl > 0 ? $ttl : 0);
        }

        if (self::$driver === 'apcu') {
            return (bool) apcu_store($k, $value, $ttl);
        }

        return false;
    }

    /**
     * Delete a single key. Returns true on success (or key already absent).
     */
    public static function delete(string $key): bool
    {
        self::init();
        $k = self::$prefix . $key;

        if (self::$driver === 'redis' && self::$redis !== null) {
            try {
                self::$redis->del($k);
                return true;
            } catch (\Throwable $e) {
                error_log('Cache: Redis del() failed — ' . $e->getMessage());
                return false;
            }
        }

        if (self::$driver === 'memcached' && self::$memcached !== null) {
            self::$memcached->delete($k);
            return true;
        }

        if (self::$driver === 'apcu') {
            apcu_delete($k);
            return true;
        }

        return false;
    }

    /**
     * Atomic increment. Initialises key to 1 when it does not exist.
     * $ttl is applied only when the key is created (i.e. counter = 1).
     * Returns the new counter value, or 0 on failure / no-op driver.
     */
    public static function increment(string $key, int $ttl = 0): int
    {
        self::init();
        $k = self::$prefix . $key;

        if (self::$driver === 'redis' && self::$redis !== null) {
            try {
                $newVal = self::$redis->incr($k);
                // Apply expiry only when the key is brand-new
                if ($newVal === 1 && $ttl > 0) {
                    self::$redis->expire($k, $ttl);
                }
                return max(0, (int) $newVal);
            } catch (\Throwable $e) {
                error_log('Cache: Redis incr() failed — ' . $e->getMessage());
                return 0;
            }
        }

        if (self::$driver === 'memcached' && self::$memcached !== null) {
            // Memcached::increment() fails on a missing key — use add() as fallback
            $result = self::$memcached->increment($k, 1, 1, $ttl > 0 ? $ttl : 0);
            return $result !== false ? (int) $result : 0;
        }

        if (self::$driver === 'apcu') {
            // apcu_add() is atomic — returns false if key already exists
            if (apcu_add($k, 1, $ttl)) {
                return 1;
            }
            $newVal = apcu_inc($k);
            return max(0, (int) $newVal);
        }

        return 0;
    }

    /**
     * Returns the active driver name: redis | memcached | apcu | none.
     */
    public static function getDriver(): string
    {
        self::init();
        return self::$driver;
    }

    // ── Initialization ─────────────────────────────────────

    private static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;
        self::$prefix      = Environment::get('CACHE_PREFIX') ?: 'srp_';

        $configured = strtolower(trim(Environment::get('CACHE_DRIVER')));

        if ($configured === 'redis') {
            self::$driver = self::setupRedis() ? 'redis' : 'none';
            return;
        }

        if ($configured === 'memcached') {
            self::$driver = self::setupMemcached() ? 'memcached' : 'none';
            return;
        }

        if ($configured === 'apcu') {
            self::$driver = function_exists('apcu_fetch') ? 'apcu' : 'none';
            return;
        }

        if ($configured === 'none') {
            self::$driver = 'none';
            return;
        }

        // Auto-detect: Redis → Memcached → APCu → no-op (juga berlaku untuk 'auto')
        if (self::setupRedis()) {
            self::$driver = 'redis';
            return;
        }

        if (self::setupMemcached()) {
            self::$driver = 'memcached';
            return;
        }

        if (function_exists('apcu_fetch')) {
            self::$driver = 'apcu';
            return;
        }

        self::$driver = 'none';
    }

    private static function setupRedis(): bool
    {
        if (!class_exists('Redis')) {
            return false;
        }

        $host     = Environment::get('REDIS_HOST') ?: '127.0.0.1';
        $port     = (int) (Environment::get('REDIS_PORT') ?: '6379');
        $password = Environment::get('REDIS_PASSWORD');
        $db       = (int) (Environment::get('REDIS_DB') ?: '0');

        try {
            $redis = new \Redis();
            if (!$redis->connect($host, $port, 2.0)) {
                return false;
            }
            if ($password !== '') {
                $redis->auth($password);
            }
            if ($db !== 0) {
                $redis->select($db);
            }
            self::$redis = $redis;
            return true;
        } catch (\Throwable $e) {
            error_log('Cache: Redis connection failed — ' . $e->getMessage());
            return false;
        }
    }

    private static function setupMemcached(): bool
    {
        if (!class_exists('Memcached')) {
            return false;
        }

        $host = Environment::get('MEMCACHED_HOST') ?: '127.0.0.1';
        $port = (int) (Environment::get('MEMCACHED_PORT') ?: '11211');

        try {
            $memcached = new \Memcached();
            if (!$memcached->addServer($host, $port)) {
                return false;
            }
            self::$memcached = $memcached;
            return true;
        } catch (\Throwable $e) {
            error_log('Cache: Memcached setup failed — ' . $e->getMessage());
            return false;
        }
    }
}

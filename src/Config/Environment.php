<?php

declare(strict_types=1);

namespace SRP\Config;

class Environment
{
    private static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        $baseDir = dirname(__DIR__, 2);
        $baseFile = $baseDir . '/.env';
        self::loadEnvFile($baseFile, false);

        $envName = getenv('SRP_ENV') ?: ($_ENV['SRP_ENV'] ?? '');
        $envName = trim((string)$envName);
        if ($envName !== '') {
            $namedFile = sprintf('%s/.env.%s', $baseDir, $envName);
            self::loadEnvFile($namedFile, true);
        }

        $explicitFile = getenv('SRP_ENV_FILE') ?: ($_ENV['SRP_ENV_FILE'] ?? '');
        $explicitFile = trim((string)$explicitFile);
        if ($explicitFile !== '') {
            $path = str_contains($explicitFile, '/') ? $explicitFile : sprintf('%s/%s', $baseDir, $explicitFile);
            self::loadEnvFile($path, true);
        }

        self::$loaded = true;
    }

    public static function get(string $key, ?string $default = null): string
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === '') {
            return $default ?? '';
        }

        return (string)$value;
    }

    /**
     * Returns APP_URL from .env (trailing slash stripped).
     * Falls back to auto-detecting scheme + host from the current request.
     */
    /**
     * Returns APP_URL from .env (trailing slash stripped).
     * Falls back to auto-detecting scheme + host from the current request
     * when APP_URL is empty or set to 'auto'.
     */
    public static function getAppUrl(): string
    {
        $url = rtrim(trim(self::get('APP_URL')), '/');
        if ($url !== '' && strtolower($url) !== 'auto') {
            return $url;
        }

        // Auto-detect dari request headers
        // Cloudflare / reverse proxy: X-Forwarded-Proto, CF-Visitor
        $scheme = 'http';
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $scheme = 'https';
        } elseif (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
            $scheme = 'https';
        } elseif (str_contains(($_SERVER['HTTP_CF_VISITOR'] ?? ''), '"https"')) {
            $scheme = 'https';
        }

        $host = (string) ($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost'));

        return $scheme . '://' . $host;
    }

    /**
     * Strip surrounding whitespace and balanced quotes from a raw .env value.
     * e.g. `"hello world"` → `hello world`, `'foo'` → `foo`, `bare` → `bare`.
     */
    private static function normalizeValue(string $value): string
    {
        $value = trim($value);
        $len   = strlen($value);
        if ($len >= 2) {
            $first = $value[0];
            $last  = $value[$len - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($value, 1, $len - 2);
            }
        }
        return $value;
    }

    private static function loadEnvFile(string $path, bool $override = false): void
    {
        static $fileLoadedKeys = [];

        if (!is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key = trim($key);
            if ($key === '') {
                continue;
            }

            $value = self::normalizeValue($value);
            $hasSystemValue = getenv($key) !== false && !isset($fileLoadedKeys[$key]);

            if ($hasSystemValue) {
                continue;
            }

            if (!$override && isset($fileLoadedKeys[$key])) {
                continue;
            }

            $fileLoadedKeys[$key] = true;
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

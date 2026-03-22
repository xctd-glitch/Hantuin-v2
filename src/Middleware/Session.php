<?php

declare(strict_types=1);

namespace SRP\Middleware;

use SRP\Config\Environment;

class Session
{
    public static function isSecure(): bool
    {
        if (strtolower(Environment::get('SRP_FORCE_SECURE_COOKIES')) === 'true') {
            return true;
        }
        return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }

    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            // Strict mode: reject session IDs not issued by this server (prevents fixation).
            ini_set('session.use_strict_mode', '1');

            // Honour SESSION_LIFETIME env var for server-side GC (default 1 h).
            // Note: cookie lifetime stays 0 (browser session) — these serve different purposes.
            $lifetime = (int)(Environment::get('SESSION_LIFETIME') ?: '3600');
            ini_set('session.gc_maxlifetime', (string)$lifetime);

            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => self::isSecure(),
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            session_start();
        }
    }

    public static function requireAuth(): void
    {
        self::start();

        if (empty($_SESSION['srp_admin_id'])) {
            header('Location: /login.php');
            exit;
        }
    }

    public static function getCsrfToken(): string
    {
        self::start();

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function validateCsrfToken(string $providedToken): bool
    {
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        if ($sessionToken === '' || $providedToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $providedToken);
    }
}

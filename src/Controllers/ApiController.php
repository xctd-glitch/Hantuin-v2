<?php

declare(strict_types=1);

namespace SRP\Controllers;

use SRP\Middleware\SecurityHeaders;
use SRP\Middleware\Session;
use SRP\Models\Conversion;
use SRP\Models\Settings;
use SRP\Models\TrafficLog;

class ApiController
{
    public static function handleDataRequest(): void
    {
        Session::start();

        if (empty($_SESSION['srp_admin_id'])) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Unauthorized'], JSON_THROW_ON_ERROR);
            exit;
        }

        SecurityHeaders::send();

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        self::handleCors();

        $method = $_SERVER['REQUEST_METHOD'] ?? '';

        if ($method === 'OPTIONS') {
            exit;
        }

        try {
            match ($method) {
                'GET'    => isset($_GET['analytics']) ? self::getAnalytics() : self::getData(),
                'POST'   => self::guardPost(),
                'DELETE' => self::guardDelete(),
                default  => throw new \RuntimeException('Method Not Allowed', 405),
            };
        } catch (\Throwable $e) {
            $code = ($e->getCode() >= 100 && $e->getCode() <= 599) ? $e->getCode() : 500;
            http_response_code($code);
            echo json_encode(['ok' => false, 'error' => match ($code) {
                405     => 'Method Not Allowed',
                400     => 'Bad Request',
                default => 'An error occurred',
            }], JSON_THROW_ON_ERROR);
            exit;
        }
    }

    /**
     * Safely call a DB-dependent callable; on failure return $default and set $failed.
     *
     * @template T
     * @param callable(): T $fn
     * @param T $default
     * @return T
     */
    private static function tryDb(callable $fn, mixed $default, bool &$failed = false): mixed
    {
        try {
            return $fn();
        } catch (\PDOException $e) {
            error_log('ApiController DB error: ' . $e->getMessage());
            $failed = true;
            return $default;
        }
    }

    private static function getData(): never
    {
        $dbFailed = false;
        $cfg      = self::tryDb(static function () {
            return Settings::get();
        }, [], $dbFailed);
        echo json_encode([
            'ok'             => true,
            'cfg'            => $cfg,
            'logs'           => self::tryDb(static function () {
                return TrafficLog::getAll(50);
            }, [], $dbFailed),
            'weekStats'      => self::tryDb(static function () {
                return TrafficLog::getWeeklyStats();
            }, ['total' => 0, 'a_count' => 0, 'b_count' => 0, 'since' => ''], $dbFailed),
            'weekConversions' => self::tryDb(static function () {
                return Conversion::getWeeklyStats();
            }, ['total' => 0, 'total_payout' => 0], $dbFailed),
            'conversions'    => self::tryDb(static function () {
                return Conversion::getRecent(150);
            }, [], $dbFailed),
            'db_error'       => $dbFailed,
        ], JSON_THROW_ON_ERROR);
        exit;
    }

    private static function getAnalytics(): never
    {
        $days    = max(1, min(90, (int)($_GET['days'] ?? 30)));
        $traffic = self::tryDb(static function () use ($days) {
            return TrafficLog::getDailyStats($days);
        }, []);
        $convMap = [];
        foreach (
            self::tryDb(static function () use ($days) {
                return Conversion::getDailyStats($days);
            }, []) as $row
        ) {
            $convMap[$row['day']] = $row;
        }
        // Merge conversion data into traffic rows
        foreach ($traffic as &$row) {
            $c = $convMap[$row['day']] ?? null;
            $row['conv_count']   = $c ? $c['total'] : 0;
            $row['conv_payout']  = $c ? $c['total_payout'] : 0.0;
        }
        unset($row);

        echo json_encode([
            'ok'    => true,
            'stats' => $traffic,
        ], JSON_THROW_ON_ERROR);
        exit;
    }

    private static function postData(): never
    {
        $raw = (string)file_get_contents('php://input');

        if (strlen($raw) > 10240) {
            http_response_code(413);
            echo json_encode(['ok' => false, 'error' => 'Payload too large'], JSON_THROW_ON_ERROR);
            exit;
        }

        try {
            $data = json_decode($raw ?: '[]', true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid JSON payload'], JSON_THROW_ON_ERROR);
            exit;
        }

        if (!is_array($data) || !isset($data['system_on'], $data['redirect_url'])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Missing required fields'], JSON_THROW_ON_ERROR);
            exit;
        }

        try {
            $currentCfg = Settings::get();
            Settings::update(
                (bool)$data['system_on'],
                (string)$data['redirect_url'],
                (string)($data['country_filter_mode'] ?? 'all'),
                (string)($data['country_filter_list'] ?? ''),
                (string)($data['postback_url'] ?? $currentCfg['postback_url']),
                (string)($data['postback_token']       ?? ''),
            );
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_THROW_ON_ERROR);
            exit;
        }

        echo json_encode(['ok' => true], JSON_THROW_ON_ERROR);
        exit;
    }

    private static function deleteLogs(): never
    {
        $count = TrafficLog::clearAll();
        echo json_encode(['ok' => true, 'deleted' => $count], JSON_THROW_ON_ERROR);
        exit;
    }

    private static function guardPost(): never
    {
        self::requireCsrfToken();
        self::requireAdminRole();
        self::postData();
    }

    private static function guardDelete(): never
    {
        self::requireCsrfToken();
        self::requireAdminRole();
        self::deleteLogs();
    }

    private static function requireAdminRole(): void
    {
        $role = $_SESSION['srp_role'] ?? '';
        if ($role !== 'admin') {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Forbidden: admin role required'], JSON_THROW_ON_ERROR);
            exit;
        }
    }

    private static function requireCsrfToken(): void
    {
        $sessionToken  = $_SESSION['csrf_token'] ?? '';
        $providedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if ($sessionToken === '' || !hash_equals($sessionToken, $providedToken)) {
            http_response_code(419);
            echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token'], JSON_THROW_ON_ERROR);
            exit;
        }
    }

    private static function handleCors(): void
    {
        $allowedOrigins = [
            'https://localhost', 'http://localhost',
            'http://localhost:8000', 'http://localhost:3000',
        ];

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        }

        header('Vary: Origin');
        header('Access-Control-Allow-Credentials: true');

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            header('Access-Control-Allow-Methods: GET, POST, DELETE');
            header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With');
        }
    }
}

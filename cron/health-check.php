#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    echo "SRP health-check must be run from CLI only." . PHP_EOL;
    exit(1);
}

chdir(dirname(__DIR__));

require_once __DIR__ . '/../src/bootstrap.php';

use SRP\Config\Database;
use SRP\Models\Settings;
use SRP\Models\TrafficLog;

/**
 * Simple CLI logger with timestamp.
 */
function logCli(string $level, string $message): void
{
    $timestamp = date('Y-m-d H:i:s');
    echo '[' . $timestamp . '] [' . $level . '] ' . $message . PHP_EOL;
}

/**
 * Check database connectivity.
 *
 * @param string[] $errors
 */
function checkDatabase(array &$errors): void
{
    try {
        /** @var mixed $db */
        $db = null;

        if (method_exists(Database::class, 'getInstance')) {
            $db = Database::getInstance();
        } elseif (method_exists(Database::class, 'getConnection')) {
            $db = Database::getConnection();
        } elseif (method_exists(Database::class, 'getPdo')) {
            $db = Database::getPdo();
        } else {
            throw new \RuntimeException(
                'No known static connection method found on '
                . Database::class
                . ' (tried getInstance/getConnection/getPdo)'
            );
        }

        if ($db instanceof \PDO) {
            $stmt = $db->query('SELECT 1');
            if ($stmt === false) {
                throw new \RuntimeException('SELECT 1 failed on PDO connection');
            }
        } elseif ($db instanceof \mysqli) {
            if (!$db->ping()) {
                throw new \RuntimeException('mysqli ping() failed');
            }
        } elseif ($db !== null && method_exists($db, 'ping')) {
            /** @var mixed $result */
            $result = $db->ping();
            if ($result === false) {
                throw new \RuntimeException('Custom DB ping() failed');
            }
        } else {
            throw new \RuntimeException(
                'Unsupported database connection type from '
                . Database::class
            );
        }

        logCli('OK', 'Database connection is healthy');
    } catch (\Throwable $e) {
        $errors[] = 'Database connection failed: ' . $e->getMessage();
    }
}

/**
 * Check application settings.
 *
 * @param string[] $errors
 * @param string[] $warnings
 */
function checkSettings(array &$errors, array &$warnings): void
{
    try {
        $settings = Settings::get();

        if (!is_array($settings)) {
            $errors[] = 'Settings::get() did not return an array';
            return;
        }

        if (empty($settings['redirect_url'])) {
            $warnings[] = 'Redirect URL not configured';
        }

        logCli('OK', 'Settings loaded');
    } catch (\Throwable $e) {
        $errors[] = 'Failed to load settings: ' . $e->getMessage();
    }
}

/**
 * Check SRP API connectivity if configured.
 *
 * @param string[] $warnings
 */
function checkSrpApi(array &$warnings): void
{
    $apiUrl = getenv('SRP_API_URL') ?: '';
    $apiKey = getenv('SRP_API_KEY') ?: '';

    if ($apiUrl === '' || $apiKey === '') {
        logCli('WARN', 'SRP API not configured (SRP_API_URL / SRP_API_KEY empty)');
        return;
    }

    if (!function_exists('curl_init')) {
        $warnings[] = 'cURL extension not available, cannot check SRP API';
        return;
    }

    try {
        $ch = curl_init($apiUrl);

        if ($ch === false) {
            $warnings[] = 'Failed to initialize cURL for SRP API';
            return;
        }

        $payload = [
            'click_id'     => 'HEALTH_CHECK_' . time(),
            'country_code' => 'XX',
            'user_agent'   => 'srp-health-check',
            'ip_address'   => '127.0.0.1',
            'user_lp'      => 'health_check',
        ];

        $options = [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-API-Key: ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_THROW_ON_ERROR),
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        if (!curl_setopt_array($ch, $options)) {
            $warnings[] = 'Failed to set cURL options for SRP API';
            curl_close($ch);
            return;
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        if ($curlError !== '') {
            $warnings[] = 'SRP API unreachable: ' . $curlError;
            return;
        }

        if ($httpCode === 401 || $httpCode === 403) {
            $warnings[] = 'SRP API authentication failed (HTTP ' . $httpCode . ')';
            return;
        }

        if ($httpCode !== 200) {
            $warnings[] = 'SRP API returned HTTP ' . $httpCode;
            return;
        }

        logCli('OK', 'SRP API connection is healthy');
    } catch (\Throwable $e) {
        $warnings[] = 'SRP API check failed: ' . $e->getMessage();
    }
}

/**
 * Check disk space usage.
 *
 * @param string[] $errors
 * @param string[] $warnings
 */
function checkDiskSpace(array &$errors, array &$warnings): void
{
    $path = __DIR__;

    $freeSpace = @disk_free_space($path);
    $totalSpace = @disk_total_space($path);

    if ($freeSpace === false || $totalSpace === false || $totalSpace <= 0) {
        $warnings[] = 'Unable to determine disk space usage';
        return;
    }

    $usedPercent = 100 - ($freeSpace / $totalSpace * 100);

    if ($usedPercent > 90.0) {
        $errors[] = sprintf('Disk space critical: %.1f%% used', $usedPercent);
    } elseif ($usedPercent > 80.0) {
        $warnings[] = sprintf('Disk space warning: %.1f%% used', $usedPercent);
    } else {
        logCli('OK', sprintf('Disk space OK (%.1f%% used)', $usedPercent));
    }
}

/**
 * Check traffic log count.
 */
function checkTrafficLog(): void
{
    try {
        $logs = TrafficLog::getAll(50);

        if (!is_array($logs)) {
            logCli('INFO', 'TrafficLog::getAll did not return an array');
            return;
        }

        $logCount = count($logs);
        logCli('INFO', 'Traffic logs: ' . $logCount . ' entries (sample size 50)');
    } catch (\Throwable $e) {
        logCli('WARN', 'Failed to read traffic logs: ' . $e->getMessage());
    }
}

/**
 * Check .env presence.
 *
 * @param string[] $errors
 */
function checkEnvFile(array &$errors): void
{
    $envPath = dirname(__DIR__) . '/.env';

    if (!file_exists($envPath)) {
        $errors[] = '.env file not found at ' . $envPath;
        return;
    }

    logCli('OK', '.env file exists');
}

/**
 * Main entry point.
 */
function main(): int
{
    $errors = [];
    $warnings = [];

    logCli('INFO', 'SRP Cron: Starting health check');

    checkDatabase($errors);
    checkSettings($errors, $warnings);
    checkSrpApi($warnings);
    checkDiskSpace($errors, $warnings);
    checkTrafficLog();
    checkEnvFile($errors);

    echo PHP_EOL . '--- Health Check Summary ---' . PHP_EOL;

    if (!empty($errors)) {
        echo 'ERRORS:' . PHP_EOL;
        foreach ($errors as $error) {
            echo '  - ' . $error . PHP_EOL;
        }
    }

    if (!empty($warnings)) {
        echo 'WARNINGS:' . PHP_EOL;
        foreach ($warnings as $warning) {
            echo '  - ' . $warning . PHP_EOL;
        }
    }

    if (empty($errors) && empty($warnings)) {
        echo 'All checks passed.' . PHP_EOL;
        return 0;
    }

    if (!empty($errors)) {
        return 1;
    }

    // Only warnings: considered pass with signal
    return 0;
}

exit(main());

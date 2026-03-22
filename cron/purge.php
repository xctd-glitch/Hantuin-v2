#!/usr/bin/env php
<?php

/**
 * SRP — Unified auto-cleanup script
 *
 * Cleans: DB traffic logs · PHP sessions · upload tmp files ·
 *         project logs/ dir · rotated error logs · old backups · APCu cache
 *
 * Usage:
 *   php cron/purge.php [OPTIONS]
 *
 * Options:
 *   --log-days=N      DB log retention in days          (default: 7)
 *   --session-ttl=N   Session file max age in seconds   (default: php.ini gc_maxlifetime)
 *   --tmp-age=N       Upload/tmp file max age in seconds (default: 86400)
 *   --log-max-mb=N    Error log rotation threshold MB   (default: 10)
 *   --backup-days=N   Backup file retention in days     (default: 30)
 *   --dry-run         Preview — do not delete anything
 *
 * Suggested crontab (crontab -e):
 *   # Full purge nightly at 03:00
 *   0 3 * * * php /path/to/hantuin/cron/purge.php >> /path/to/hantuin/logs/purge.log 2>&1
 *
 *   # DB-only quick cleanup every hour
 *   0 * * * * php /path/to/hantuin/cron/purge.php --log-days=7 --session-ttl=0 --tmp-age=0 --backup-days=0 >> /dev/null 2>&1
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "purge.php must be run from CLI.\n");
    exit(1);
}

chdir(dirname(__DIR__));

require_once __DIR__ . '/../src/bootstrap.php';

use SRP\Models\TrafficLog;

// ── Parse CLI options ──────────────────────────────────────────────────────────
$opts = getopt('', ['log-days::', 'session-ttl::', 'tmp-age::', 'log-max-mb::', 'backup-days::', 'dry-run']);

$logDays     = isset($opts['log-days'])    ? max(1, (int)$opts['log-days'])    : 7;
$sessionTtl  = isset($opts['session-ttl']) ? max(60, (int)$opts['session-ttl']) : max(1440, (int)ini_get('session.gc_maxlifetime'));
$tmpMaxAge   = isset($opts['tmp-age'])     ? max(3600, (int)$opts['tmp-age'])     : 86400;
$logMaxBytes = (isset($opts['log-max-mb']) ? max(1, (int)$opts['log-max-mb']) : 10) * 1024 * 1024;
$backupDays  = isset($opts['backup-days']) ? max(1, (int)$opts['backup-days']) : 30;
$dryRun      = isset($opts['dry-run']);

// ── Helpers ────────────────────────────────────────────────────────────────────
function logLine(string $level, string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . '] [' . str_pad($level, 4) . '] ' . $msg . PHP_EOL;
}

function section(string $title): void
{
    echo PHP_EOL;
    logLine('----', str_pad("── {$title} ", 60, '─'));
}

/**
 * Delete files matching $glob inside $dir that are older than $cutoff.
 * Returns count of deleted (or would-be-deleted in dry-run) files.
 */
function purgeFiles(string $dir, string $glob, int $cutoff, bool $dryRun): int
{
    if (!is_dir($dir)) {
        return 0;
    }

    $count = 0;
    $files = glob(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $glob) ?: [];

    foreach ($files as $file) {
        if (!is_file($file)) {
            continue;
        }
        $mtime = @filemtime($file);
        if ($mtime === false || $mtime >= $cutoff) {
            continue;
        }
        if ($dryRun || @unlink($file)) {
            $count++;
        }
    }

    return $count;
}

$ROOT = dirname(__DIR__);
$verb = $dryRun ? 'Would delete' : 'Deleted';
if ($dryRun) {
    logLine('DRY', '*** DRY-RUN mode — nothing will be deleted ***');
}
logLine('INFO', 'SRP purge started');

$exitCode = 0;

// ── 1. DB traffic logs ─────────────────────────────────────────────────────────
section("DB traffic logs  (retention: {$logDays}d)");
if (!$dryRun) {
    try {
        $deleted = TrafficLog::autoCleanup($logDays);
        logLine('OK', "Removed {$deleted} log row(s) older than {$logDays} day(s)");
    } catch (\Throwable $e) {
        logLine('ERR', 'DB log cleanup failed: ' . $e->getMessage());
        $exitCode = 1;
    }
} else {
    logLine('DRY', "Would delete rows older than {$logDays} day(s)");
}

// ── 2. PHP session files ───────────────────────────────────────────────────────
section("PHP sessions  (TTL: {$sessionTtl}s  ≈ " . round($sessionTtl / 60) . 'min)');

$sessDir = rtrim((string)(ini_get('session.save_path') ?: sys_get_temp_dir()), '/\\');
// Handle "N;/path" format in session.save_path
if (str_contains($sessDir, ';')) {
    $sessDir = ltrim((string)strrchr($sessDir, ';'), ';');
}
$sessDir = trim($sessDir);

logLine('INFO', "Session dir: {$sessDir}");

if ($sessDir !== '' && is_dir($sessDir)) {
    $cutoff = time() - $sessionTtl;
    $n      = purgeFiles($sessDir, 'sess_*', $cutoff, $dryRun);
    logLine($n > 0 ? 'OK' : 'INFO', "{$verb} {$n} expired session file(s)");
} else {
    logLine('SKIP', 'Session directory not found or not accessible');
}

// ── 3. PHP upload tmp files ────────────────────────────────────────────────────
section("Upload tmp  (max age: " . round($tmpMaxAge / 3600, 1) . 'h)');

$uploadTmp = rtrim((string)(ini_get('upload_tmp_dir') ?: sys_get_temp_dir()), '/\\');
logLine('INFO', "Upload tmp dir: {$uploadTmp}");

if (is_dir($uploadTmp)) {
    $cutoff = time() - $tmpMaxAge;
    $n      = purgeFiles($uploadTmp, 'php*', $cutoff, $dryRun);
    logLine($n > 0 ? 'OK' : 'INFO', "{$verb} {$n} stale upload file(s)");
} else {
    logLine('SKIP', 'Upload tmp dir not found');
}

// ── 4. Project logs/ directory ─────────────────────────────────────────────────
section('Project logs/  (old files > ' . round($tmpMaxAge / 86400, 1) . 'd)');

$projectLogs = $ROOT . '/logs';
if (is_dir($projectLogs)) {
    $cutoff = time() - $tmpMaxAge;
    // Keep the most-recent log; only purge rotated/dated copies
    $n = purgeFiles($projectLogs, '*.log.*', $cutoff, $dryRun);
    $n += purgeFiles($projectLogs, '*.log.gz', $cutoff, $dryRun);
    logLine($n > 0 ? 'OK' : 'INFO', "{$verb} {$n} old rotated log file(s) from {$projectLogs}");
} else {
    logLine('SKIP', "Project logs/ dir not found ({$projectLogs})");
}

// ── 5. Error log rotation ──────────────────────────────────────────────────────
$logMaxMb = round($logMaxBytes / 1024 / 1024, 0);
section("Error log rotation  (rotate at {$logMaxMb} MB)");

$errorLog = (string)(ini_get('error_log') ?: '');
if ($errorLog !== '' && is_file($errorLog)) {
    $size = (int)filesize($errorLog);
    if ($size >= $logMaxBytes) {
        $rotated = $errorLog . '.' . date('Ymd_His');
        if ($dryRun) {
            logLine('DRY', sprintf('Would rotate %s (%.2f MB) → %s', basename($errorLog), $size / 1024 / 1024, basename($rotated)));
        } elseif (@rename($errorLog, $rotated) && @touch($errorLog)) {
            logLine('OK', sprintf('Rotated %s (%.2f MB) → %s', basename($errorLog), $size / 1024 / 1024, basename($rotated)));
        } else {
            logLine('ERR', 'Could not rotate: ' . $errorLog);
            $exitCode = 1;
        }
    } else {
        logLine('INFO', sprintf('OK — %.2f MB (limit %d MB)', $size / 1024 / 1024, $logMaxMb));
    }

    // Remove old rotated copies beyond backup retention
    $cutoff = time() - ($backupDays * 86400);
    $n      = purgeFiles(dirname($errorLog), basename($errorLog) . '.*', $cutoff, $dryRun);
    if ($n > 0) {
        logLine('OK', "{$verb} {$n} old rotated error log(s)");
    }
} else {
    logLine('SKIP', $errorLog !== '' ? "error_log path not found: {$errorLog}" : 'error_log not configured in php.ini');
}

// ── 6. Old backup files ────────────────────────────────────────────────────────
section("Backups  (retention: {$backupDays}d)");

$backupDir = $ROOT . '/backups';
if (is_dir($backupDir)) {
    $cutoff = time() - ($backupDays * 86400);
    $n      = purgeFiles($backupDir, 'srp_backup_*', $cutoff, $dryRun);
    logLine($n > 0 ? 'OK' : 'INFO', "{$verb} {$n} old backup file(s)");
} else {
    logLine('SKIP', "Backup dir not found ({$backupDir})");
}

// ── 7. APCu cache status (+ emergency flush) ───────────────────────────────────
section('APCu cache');

if (function_exists('apcu_sma_info') && function_exists('apcu_cache_info') && function_exists('apcu_enabled') && apcu_enabled()) {
    $sma = apcu_sma_info();
    if (!is_array($sma)) {
        logLine('SKIP', 'APCu SMA info unavailable (disabled in CLI?)');
    } else {
    $memTotal = (float)($sma['num_seg'] * $sma['seg_size']);
    $memFree  = (float)($sma['avail_mem'] ?? 0);
    $memUsed  = $memTotal - $memFree;
    $usedPct  = $memTotal > 0 ? round($memUsed / $memTotal * 100, 1) : 0.0;

    try {
        $info      = apcu_cache_info(false);
        if (!is_array($info)) {
            throw new \RuntimeException('APCu cache info unavailable');
        }
        $numSlots  = (int)($info['num_slots']   ?? 0);
        $numEntries = (int)($info['num_entries'] ?? 0);
        $expiries  = (int)($info['expunges']    ?? 0);
    } catch (\Throwable) {
        $numSlots = $numEntries = $expiries = 0;
    }

    logLine('INFO', sprintf(
        'Memory: %.2f MB used / %.2f MB total (%s%%)  |  Entries: %d  |  Evictions: %d',
        $memUsed  / 1024 / 1024,
        $memTotal / 1024 / 1024,
        $usedPct,
        $numEntries,
        $expiries
    ));

    if ($usedPct > 90.0) {
        logLine('WARN', "APCu memory at {$usedPct}% — flushing all cache");
        if (!$dryRun) {
            apcu_clear_cache();
            logLine('OK', 'APCu cache flushed');
        } else {
            logLine('DRY', 'Would flush APCu cache');
        }
    } else {
        logLine('OK', "APCu healthy ({$usedPct}% used)");
    }
    } // end if ($sma is_array)
} else {
    logLine('SKIP', 'APCu not available (extension not loaded or disabled in CLI)');
}

// ── Summary ────────────────────────────────────────────────────────────────────
echo PHP_EOL;
logLine('INFO', $exitCode === 0 ? 'SRP purge finished successfully' : 'SRP purge finished with errors');
exit($exitCode);

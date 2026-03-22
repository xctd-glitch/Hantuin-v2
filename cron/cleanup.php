#!/usr/bin/env php
<?php

/**
 * SRP — DB log cleanup (legacy wrapper).
 *
 * Delegates to purge.php for a full cleanup run.
 * Kept for backward compatibility with existing crontab entries.
 *
 * Usage (legacy):  php cron/cleanup.php [retention_days]
 * Preferred:       php cron/purge.php --log-days=7
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "cleanup.php must be run from CLI.\n");
    exit(1);
}

$retentionDays = isset($argv[1]) ? max(1, (int)$argv[1]) : 7;

// Forward to purge.php with the same retention, passing through any extra flags
$purge  = __DIR__ . '/purge.php';
$phpBin = PHP_BINARY;
$args   = '--log-days=' . $retentionDays;

// Detect --dry-run forwarded from caller
foreach (array_slice($argv, 2) as $extra) {
    if ($extra === '--dry-run') {
        $args .= ' --dry-run';
    }
}

passthru($phpBin . ' ' . escapeshellarg($purge) . ' ' . $args, $code);
exit($code);

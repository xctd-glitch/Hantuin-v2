#!/usr/bin/env php
<?php

declare(strict_types=1);

// Change to project root directory
chdir(dirname(__DIR__));

require_once __DIR__ . '/../src/bootstrap.php';

use SRP\Config\Database;

// Parse command line arguments
$retentionDays = isset($argv[1]) ? (int) $argv[1] : 30;

if ($retentionDays < 1) {
    echo "Error: Retention days must be at least 1\n";
    exit(1);
}

// Configuration
$backupDir = dirname(__DIR__) . '/backups';
$timestamp = date('Y-m-d H:i:s');
$dateStr = date('Y-m-d_His');

echo "[{$timestamp}] SRP Cron: Starting database backup\n";

try {
    // Create backup directory if not exists
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
        echo "[{$timestamp}] Created backup directory: {$backupDir}\n";
    }

    // Get database connection info (support both DB_* and SRP_DB_* format)
    $dbHost = getenv('DB_HOST') ?: getenv('SRP_DB_HOST') ?: 'localhost';
    $dbName = getenv('DB_NAME') ?: getenv('SRP_DB_NAME') ?: '';
    $dbUser = getenv('DB_USER') ?: getenv('SRP_DB_USER') ?: '';
    $dbPass = getenv('DB_PASS') ?: getenv('SRP_DB_PASS') ?: '';
    $dbPort = getenv('DB_PORT') ?: getenv('SRP_DB_PORT') ?: '3306';

    if (empty($dbName)) {
        echo "Error: Database name not configured in .env\n";
        echo "Please set DB_NAME or SRP_DB_NAME in .env file\n";
        exit(1);
    }

    // Backup filename
    $backupFile = "{$backupDir}/srp_backup_{$dateStr}.sql";

    // Build mysqldump command
    $command = sprintf(
        'mysqldump --host=%s --user=%s --password=%s --single-transaction --quick --lock-tables=false %s > %s 2>&1',
        escapeshellarg($dbHost),
        escapeshellarg($dbUser),
        escapeshellarg($dbPass),
        escapeshellarg($dbName),
        escapeshellarg($backupFile)
    );

    // Execute backup
    exec($command, $output, $returnCode);

    if ($returnCode !== 0) {
        echo "Error: mysqldump failed with code {$returnCode}\n";
        echo "Output: " . implode("\n", $output) . "\n";
        exit(1);
    }

    // Compress backup
    $gzipCommand = sprintf('gzip -f %s', escapeshellarg($backupFile));
    exec($gzipCommand, $gzipOutput, $gzipCode);

    if ($gzipCode === 0) {
        $backupFile .= '.gz';
        $fileSize = filesize($backupFile);
        $fileSizeMB = round($fileSize / 1024 / 1024, 2);

        echo "[{$timestamp}] ✓ Backup created successfully: {$backupFile} ({$fileSizeMB} MB)\n";
    } else {
        $fileSize = filesize($backupFile);
        $fileSizeMB = round($fileSize / 1024 / 1024, 2);

        echo "[{$timestamp}] ✓ Backup created (uncompressed): {$backupFile} ({$fileSizeMB} MB)\n";
    }

    // Clean up old backups
    echo "[{$timestamp}] Cleaning up old backups (retention: {$retentionDays} days)\n";

    $cutoffTime = time() - ($retentionDays * 86400);
    $deletedCount = 0;

    $files = glob("{$backupDir}/srp_backup_*.sql*");
    foreach ($files as $file) {
        if (filemtime($file) < $cutoffTime) {
            if (unlink($file)) {
                $deletedCount++;
                echo "[{$timestamp}] Deleted old backup: " . basename($file) . "\n";
            }
        }
    }

    echo "[{$timestamp}] ✓ Cleanup complete: {$deletedCount} old backups deleted\n";

    exit(0);
} catch (\Throwable $e) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] SRP Cron: Error - {$e->getMessage()}\n";
    echo "Stack trace:\n{$e->getTraceAsString()}\n";

    exit(1);
}

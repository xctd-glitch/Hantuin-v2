#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * SRP — Auto Cron Setup (Non-Interactive)
 *
 * Otomatis install cron jobs tanpa prompt. Aman dijalankan berulang
 * (idempotent) — cek duplikat sebelum menambahkan entry baru.
 *
 * Usage:
 *   php cron/auto-setup.php              # Install semua cron jobs
 *   php cron/auto-setup.php --remove     # Hapus semua cron jobs SRP
 *   php cron/auto-setup.php --list       # Tampilkan status saja
 *   php cron/auto-setup.php --dry-run    # Preview tanpa install
 *
 * Cron jobs yang di-install:
 *   1. purge.php     — Cleanup harian (03:00) — logs, sessions, backups
 *   2. backup.php    — Database backup harian (01:00)
 *   3. health-check  — Health monitoring setiap 15 menit
 */

if (PHP_SAPI !== 'cli') {
    echo "auto-setup.php must be run from CLI.\n";
    exit(1);
}

// ── Config ──────────────────────────────────────────────────────

$ROOT     = dirname(__DIR__);
$CRON_DIR = __DIR__;
$LOG_DIR  = $ROOT . '/logs';

// ── Detect PHP binary ───────────────────────────────────────────

$phpBin = PHP_BINARY;

// cPanel: cari PHP 8.3 spesifik
$cpanelPaths = [
    '/opt/cpanel/ea-php83/root/usr/bin/php',
    '/opt/cpanel/ea-php82/root/usr/bin/php',
    '/usr/local/bin/php',
    '/usr/bin/php',
];

foreach ($cpanelPaths as $path) {
    if (is_executable($path)) {
        // Cek versi >= 8.3
        $ver = trim(shell_exec($path . ' -r "echo PHP_VERSION;"') ?? '');
        if (version_compare($ver, '8.3.0', '>=')) {
            $phpBin = $path;
            break;
        }
    }
}

// ── Parse args ──────────────────────────────────────────────────

$doRemove = in_array('--remove', $argv, true);
$doList   = in_array('--list', $argv, true);
$dryRun   = in_array('--dry-run', $argv, true);

// ── Cron job definitions ────────────────────────────────────────

$marker = '# SRP-AUTO-CRON';

$jobs = [
    [
        'schedule' => '0 3 * * *',
        'command'  => $phpBin . ' ' . $CRON_DIR . '/purge.php --log-days=7 --backup-days=30',
        'log'      => $LOG_DIR . '/cron-purge.log',
        'label'    => 'Purge (cleanup harian)',
    ],
    [
        'schedule' => '0 1 * * *',
        'command'  => $phpBin . ' ' . $CRON_DIR . '/backup.php 30',
        'log'      => $LOG_DIR . '/cron-backup.log',
        'label'    => 'Backup DB harian',
    ],
    [
        'schedule' => '*/15 * * * *',
        'command'  => $phpBin . ' ' . $CRON_DIR . '/health-check.php',
        'log'      => $LOG_DIR . '/cron-health.log',
        'label'    => 'Health check (15 min)',
    ],
];

// ── Helpers ─────────────────────────────────────────────────────

function ok(string $msg): void
{
    echo "  ✓  {$msg}\n";
}

function info(string $msg): void
{
    echo "  →  {$msg}\n";
}

function warn(string $msg): void
{
    echo "  ⚠  {$msg}\n";
}

function err(string $msg): void
{
    fwrite(STDERR, "  ✗  {$msg}\n");
}

/**
 * Baca crontab saat ini.
 *
 * @return string[]
 */
function readCrontab(): array
{
    $output = [];
    exec('crontab -l 2>/dev/null', $output, $code);
    return $code === 0 ? $output : [];
}

/**
 * Tulis crontab baru.
 *
 * @param string[] $lines
 */
function writeCrontab(array $lines): bool
{
    $tmp = tempnam(sys_get_temp_dir(), 'srp_cron_');
    if ($tmp === false) {
        return false;
    }

    file_put_contents($tmp, implode("\n", $lines) . "\n");
    exec('crontab ' . escapeshellarg($tmp), $out, $code);
    @unlink($tmp);

    return $code === 0;
}

// ── Main ────────────────────────────────────────────────────────

echo "━━━  SRP Auto Cron Setup  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
info("PHP: {$phpBin}");
info("Cron dir: {$CRON_DIR}");
info("Log dir: {$LOG_DIR}");
echo "\n";

// Pastikan log dir ada
if (!is_dir($LOG_DIR)) {
    @mkdir($LOG_DIR, 0755, true);
}

$currentLines = readCrontab();
$currentText  = implode("\n", $currentLines);

// ── List mode ───────────────────────────────────────────────────

if ($doList) {
    $found = 0;
    foreach ($currentLines as $line) {
        if (str_contains($line, $marker) || str_contains($line, 'cron/purge.php') || str_contains($line, 'cron/backup.php') || str_contains($line, 'cron/health-check.php')) {
            info($line);
            $found++;
        }
    }

    if ($found === 0) {
        info('Tidak ada cron jobs SRP yang terpasang.');
    } else {
        info("{$found} cron job(s) SRP ditemukan.");
    }
    exit(0);
}

// ── Remove mode ─────────────────────────────────────────────────

if ($doRemove) {
    echo "Menghapus cron jobs SRP...\n\n";

    $newLines = [];
    $removed  = 0;

    foreach ($currentLines as $line) {
        if (str_contains($line, $marker) || str_contains($line, 'cron/purge.php') || str_contains($line, 'cron/backup.php') || str_contains($line, 'cron/health-check.php') || str_contains($line, 'cron/cleanup.php')) {
            warn("Remove: {$line}");
            $removed++;
            continue;
        }
        $newLines[] = $line;
    }

    if ($removed === 0) {
        info('Tidak ada cron jobs SRP untuk dihapus.');
        exit(0);
    }

    if ($dryRun) {
        info("[DRY-RUN] Akan menghapus {$removed} cron job(s).");
        exit(0);
    }

    if (writeCrontab($newLines)) {
        ok("Berhasil menghapus {$removed} cron job(s) SRP.");
    } else {
        err('Gagal menulis crontab.');
        exit(1);
    }
    exit(0);
}

// ── Install mode ────────────────────────────────────────────────

echo "Menginstall cron jobs...\n\n";

// Hapus entry SRP lama (replace, bukan duplikat)
$cleanLines = [];
foreach ($currentLines as $line) {
    if (str_contains($line, $marker) || str_contains($line, 'cron/purge.php') || str_contains($line, 'cron/backup.php') || str_contains($line, 'cron/health-check.php') || str_contains($line, 'cron/cleanup.php')) {
        continue;
    }
    $cleanLines[] = $line;
}

// Tambahkan header + jobs baru
$cleanLines[] = '';
$cleanLines[] = "{$marker} ── SRP Automated Maintenance ──";

$installed = 0;
foreach ($jobs as $job) {
    $entry = sprintf(
        '%s %s >> %s 2>&1 %s',
        $job['schedule'],
        $job['command'],
        $job['log'],
        $marker
    );

    $cleanLines[] = $entry;

    if ($dryRun) {
        info("[DRY-RUN] {$job['label']}");
        info("  {$entry}");
    } else {
        ok("{$job['label']}");
        info("  {$entry}");
    }
    $installed++;
}

echo "\n";

if ($dryRun) {
    info("[DRY-RUN] Akan menginstall {$installed} cron job(s). Tidak ada perubahan.");
    exit(0);
}

if (writeCrontab($cleanLines)) {
    ok("Berhasil menginstall {$installed} cron job(s).");

    // Simpan juga ke file untuk referensi
    $crontabFile = $CRON_DIR . '/crontab.txt';
    file_put_contents($crontabFile, implode("\n", $cleanLines) . "\n");
    info("Crontab saved to: {$crontabFile}");
} else {
    err('Gagal menulis crontab. Coba manual: crontab -e');
    exit(1);
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  Cron jobs terpasang:\n";
echo "    1. Purge    — setiap hari 03:00 (logs 7d, backups 30d)\n";
echo "    2. Backup   — setiap hari 01:00 (retain 30d)\n";
echo "    3. Health   — setiap 15 menit\n";
echo "\n";
echo "  Monitor: tail -f {$LOG_DIR}/cron-*.log\n";
echo "  Hapus:   php cron/auto-setup.php --remove\n";
echo "  Status:  php cron/auto-setup.php --list\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

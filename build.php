<?php

declare(strict_types=1);

/**
 * ================================================================
 * SRP v2.1 — Production Zip Builder
 * ================================================================
 * Membuat distribusi zip bersih untuk deploy ke shared hosting.
 *
 * Usage:  php build.php
 * Output: dist/srp-v2.1-YYYYMMDD-HHMM.zip
 * ================================================================
 */

// ── Config ──────────────────────────────────────────────────────

$projectRoot = __DIR__;
$version     = '2.1';
$timestamp   = date('Ymd-Hi');
$distDir     = $projectRoot . DIRECTORY_SEPARATOR . 'dist';
$zipName     = "srp-v{$version}-{$timestamp}.zip";
$zipPath     = $distDir . DIRECTORY_SEPARATOR . $zipName;
$zipPrefix   = 'srp/'; // folder root di dalam zip

// ── File / folder yang di-INCLUDE ───────────────────────────────
$includes = [
    // Source code
    'src/',
    'public_html/',
    'cron/',

    // Entry & schema
    'entry.php',
    'schema.sql',

    // Config templates
    '.env.example',
    '.env.entry.example',
    '.htaccess.example',

    // Installer
    'install.sh',

    // Composer (dibutuhkan untuk autoloader)
    'composer.json',
    'composer.lock',

    // Docs
    'docs/',
];

// ── File / pattern yang di-EXCLUDE ──────────────────────────────
$excludes = [
    // Environment & secrets
    '.env',
    '.env.backup',
    '.installed',

    // IDE / editor
    '.vscode/',
    '.claude/',
    '.idea/',

    // Dev tools
    'vendor/',
    'tests/',
    'node_modules/',

    // Runtime data
    'backups/',
    'logs/',
    'storage/',

    // Build output
    'dist/',
    'build.php',

    // OS junk
    '.DS_Store',
    'Thumbs.db',
    'desktop.ini',

    // Generated htaccess (user generates via install.sh)
    'public_html/.htaccess',
];

// ── Helper Functions ────────────────────────────────────────────

function normalizePath(string $path): string
{
    return str_replace('\\', '/', $path);
}

function isExcluded(string $relativePath, array $excludes): bool
{
    $normalized = normalizePath($relativePath);
    foreach ($excludes as $pattern) {
        $pattern = normalizePath($pattern);
        // Exact match
        if ($normalized === $pattern || $normalized === rtrim($pattern, '/')) {
            return true;
        }
        // Directory prefix
        if (str_ends_with($pattern, '/') && str_starts_with($normalized, $pattern)) {
            return true;
        }
        // Basename match (e.g. .DS_Store)
        if (!str_contains($pattern, '/') && basename($normalized) === $pattern) {
            return true;
        }
    }
    return false;
}

function addDirectoryToZip(
    ZipArchive $zip,
    string $baseDir,
    string $dir,
    string $prefix,
    array $excludes
): int {
    $count = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        $realPath = $file->getRealPath();
        $relativePath = normalizePath(
            ltrim(str_replace(normalizePath($baseDir), '', normalizePath($realPath)), '/')
        );

        if (isExcluded($relativePath, $excludes)) {
            continue;
        }

        $zipEntryName = $prefix . $relativePath;

        if ($file->isDir()) {
            $zip->addEmptyDir($zipEntryName);
        } else {
            $zip->addFile($realPath, $zipEntryName);
            $count++;
        }
    }

    return $count;
}

// ── Placeholder directories (created empty with .gitkeep) ───────
$emptyDirs = [
    'backups/',
    'logs/',
    'storage/',
];

// ── Main ────────────────────────────────────────────────────────

echo "━━━  SRP v{$version} Production Build  ━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Create dist/
if (!is_dir($distDir)) {
    mkdir($distDir, 0755, true);
    echo "  ✓  Created dist/\n";
}

// Init zip
$zip = new ZipArchive();
$result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
if ($result !== true) {
    echo "  ✗  Failed to create zip (error code: {$result})\n";
    exit(1);
}

$totalFiles = 0;

// Process includes
foreach ($includes as $include) {
    $fullPath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $include);

    if (!file_exists($fullPath)) {
        echo "  ⚠  Skipped (not found): {$include}\n";
        continue;
    }

    if (is_dir($fullPath)) {
        $added = addDirectoryToZip($zip, $projectRoot, $fullPath, $zipPrefix, $excludes);
        $totalFiles += $added;
        echo "  ✓  Added {$include} ({$added} files)\n";
    } else {
        $entryName = $zipPrefix . normalizePath($include);
        if (!isExcluded($include, $excludes)) {
            $zip->addFile($fullPath, $entryName);
            $totalFiles++;
            echo "  ✓  Added {$include}\n";
        }
    }
}

// Create empty runtime directories with .gitkeep
foreach ($emptyDirs as $emptyDir) {
    $zip->addEmptyDir($zipPrefix . $emptyDir);
    $zip->addFromString($zipPrefix . $emptyDir . '.gitkeep', '');
    echo "  ✓  Created empty {$emptyDir}\n";
}

// Add a README for the installer
$readmeInstall = <<<'TXT'
# SRP v2.1 — Quick Install

## Requirements
- PHP 8.3+ with extensions: curl, json, mbstring, openssl, pdo, pdo_mysql
- MySQL 5.7+ / MariaDB 10.3+
- Apache with mod_rewrite (shared hosting / cPanel)

## Steps

1. Upload & extract this zip to your hosting root
2. Copy `.env.example` to `.env` and configure:
   - Database credentials (SRP_DB_*)
   - Admin password (SRP_ADMIN_PASSWORD or SRP_ADMIN_PASSWORD_HASH)
   - API key (SRP_API_KEY)
3. Run the installer:
   ```bash
   chmod +x install.sh
   ./install.sh
   ```
4. Point your domain to `public_html/`
5. Access dashboard at your domain

## Alternative Manual Setup
1. `composer install --no-dev --classmap-authoritative`
2. Import `schema.sql` into your database
3. Copy `.htaccess.example` to `public_html/.htaccess`
4. Set directory permissions: `chmod 750 backups logs storage cron`

TXT;

$zip->addFromString($zipPrefix . 'INSTALL.txt', $readmeInstall);
$totalFiles++;

$zip->close();

// Stats
$zipSize = filesize($zipPath);
$sizeMB = round($zipSize / 1024 / 1024, 2);
$sizeKB = round($zipSize / 1024, 1);
$sizeStr = $sizeMB >= 1 ? "{$sizeMB} MB" : "{$sizeKB} KB";

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  ✓  Build complete!\n";
echo "  📦 {$zipName}\n";
echo "  📁 {$totalFiles} files | {$sizeStr}\n";
echo "  📂 dist/{$zipName}\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

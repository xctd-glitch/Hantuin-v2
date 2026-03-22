<?php

declare(strict_types=1);

/**
 * Memastikan tidak ada arrow function (fn) di codebase.
 * Dipanggil via: composer run check:no-arrow
 */

$baseDir = dirname(__DIR__);
$dirs = [
    $baseDir . '/src',
    $baseDir . '/public_html',
    $baseDir . '/cron',
];

$violations = [];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $filePath = $file->getRealPath();
        if ($filePath === false) {
            continue;
        }

        $tokens = token_get_all((string) file_get_contents($filePath));

        foreach ($tokens as $i => $token) {
            if (!is_array($token)) {
                continue;
            }

            // T_FN is the arrow function token (PHP 7.4+)
            if ($token[0] === T_FN) {
                $line = $token[2];
                $relative = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $filePath);
                $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
                $violations[] = sprintf('  %s:%d', $relative, $line);
            }
        }
    }
}

if ($violations !== []) {
    fwrite(STDERR, "Arrow functions (fn) found — not allowed by project convention:\n");
    fwrite(STDERR, implode("\n", $violations) . "\n");
    exit(1);
}

echo "No arrow functions found.\n";
exit(0);

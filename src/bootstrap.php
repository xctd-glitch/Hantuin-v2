<?php

declare(strict_types=1);

if (PHP_VERSION_ID < 80300) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'SRP requires PHP 8.3+. Current version: ' . PHP_VERSION . "\n";
    echo "Set PHP 8.3 via cPanel (CloudLinux Selector / MultiPHP Manager)\n";
    echo "or add the correct AddHandler to .htaccess.\n";
    exit(1);
}

require_once __DIR__ . '/Config/Bootstrap.php';

SRP\Config\Bootstrap::initialize(dirname(__DIR__), __DIR__);

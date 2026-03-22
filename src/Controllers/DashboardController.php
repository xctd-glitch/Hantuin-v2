<?php

declare(strict_types=1);

namespace SRP\Controllers;

use SRP\Config\Environment;
use SRP\Middleware\SecurityHeaders;
use SRP\Middleware\Session;

class DashboardController
{
    public static function index(): void
    {
        Session::start();

        if (empty($_SESSION['srp_admin_id'])) {
            header('Location: /login.php');
            exit;
        }

        SecurityHeaders::send();

        $csrfToken = Session::getCsrfToken();

        $appUrl        = Environment::getAppUrl();
        $initialApiKey = '';
        $userRole      = $_SESSION['srp_role'] ?? 'admin';

        require __DIR__ . '/../Views/dashboard.view.php';
    }

    public static function landing(): void
    {
        $cspNonce = bin2hex(random_bytes(16));

        SecurityHeaders::send(
            "default-src 'self'; "
            . "script-src 'self' 'nonce-{$cspNonce}' https://cdn.tailwindcss.com; "
            . "style-src 'self' 'unsafe-inline'; "
            . "img-src 'self' data:; "
            . "connect-src 'self'; "
            . "frame-ancestors 'none'; "
            . "base-uri 'self';"
        );

        require __DIR__ . '/../Views/landing.view.php';
    }
}

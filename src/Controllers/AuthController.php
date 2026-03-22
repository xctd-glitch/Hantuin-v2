<?php

declare(strict_types=1);

namespace SRP\Controllers;

use SRP\Config\Environment;
use SRP\Middleware\Session;

class AuthController
{
    public static function login(): void
    {
        Session::start();

        if (!empty($_SESSION['srp_admin_id'])) {
            header('Location: /index.php');
            exit;
        }

        $csrfToken    = Session::getCsrfToken();
        $errorMessage = null;
        $adminUser    = trim(Environment::get('SRP_ADMIN_USER'));
        $adminHash    = trim(Environment::get('SRP_ADMIN_PASSWORD_HASH'));
        $adminPlain   = trim(Environment::get('SRP_ADMIN_PASSWORD'));
        $viewerUser   = trim(Environment::get('SRP_USER_USER'));
        $viewerHash   = trim(Environment::get('SRP_USER_PASSWORD_HASH'));
        $viewerPlain  = trim(Environment::get('SRP_USER_PASSWORD'));

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errorMessage = self::handleLoginAttempt(
                $csrfToken,
                $adminUser,
                $adminHash,
                $adminPlain,
                $viewerUser,
                $viewerHash,
                $viewerPlain
            );
        }

        require __DIR__ . '/../Views/login.view.php';
    }

    public static function logout(): void
    {
        Session::start();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /login.php');
            exit;
        }

        $csrfSession  = (string)($_SESSION['csrf_token'] ?? '');
        $csrfProvided = (string)($_POST['csrf_token'] ?? '');

        if ($csrfSession === '' || $csrfProvided === '' || !hash_equals($csrfSession, $csrfProvided)) {
            http_response_code(400);
            exit('Invalid session token.');
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie((string)session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => Session::isSecure(),
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        }

        session_destroy();
        header('Location: /login.php');
        exit;
    }

    private static function handleLoginAttempt(
        string $csrfToken,
        string $adminUser,
        string $adminHash,
        string $adminPlain,
        string $viewerUser,
        string $viewerHash,
        string $viewerPlain
    ): string {
        $providedToken = (string)($_POST['csrf_token'] ?? '');
        if ($csrfToken === '' || !hash_equals($csrfToken, $providedToken)) {
            return 'Invalid session token. Please refresh the page and try again.';
        }

        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $remember = isset($_POST['remember']) && $_POST['remember'] === '1';

        // Try admin credentials first
        $role = '';
        if ($adminUser !== '' && ($adminHash !== '' || $adminPlain !== '') && hash_equals($adminUser, $username)) {
            $passwordValid = false;
            if ($adminHash !== '') {
                $passwordValid = password_verify($password, $adminHash);
            } elseif ($adminPlain !== '') {
                $passwordValid = hash_equals($adminPlain, $password);
            }
            if ($passwordValid) {
                $role = 'admin';
            }
        }

        // Try viewer credentials if admin didn't match
        $viewerConfigured = $viewerUser !== '' && ($viewerHash !== '' || $viewerPlain !== '');
        if ($role === '' && $viewerConfigured && hash_equals($viewerUser, $username)) {
            $passwordValid = false;
            if ($viewerHash !== '') {
                $passwordValid = password_verify($password, $viewerHash);
            } elseif ($viewerPlain !== '') {
                $passwordValid = hash_equals($viewerPlain, $password);
            }
            if ($passwordValid) {
                $role = 'user';
            }
        }

        if ($role === '') {
            if ($adminUser === '' || ($adminHash === '' && $adminPlain === '')) {
                return 'Admin credentials have not been configured yet.';
            }
            return 'Invalid credentials provided.';
        }

        session_regenerate_id(true);
        $_SESSION['srp_admin_id'] = $username;
        $_SESSION['srp_role'] = $role;

        if ($remember) {
            $params = session_get_cookie_params();
            setcookie((string)session_name(), (string)session_id(), [
                'expires'  => time() + 60 * 60 * 24 * 30,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => Session::isSecure(),
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        }

        header('Location: /index.php');
        exit;
    }
}

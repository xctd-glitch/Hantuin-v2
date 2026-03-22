<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use SRP\Middleware\SecurityHeaders;
use SRP\Middleware\Session;
use SRP\Models\EnvConfig;

// Require authentication
Session::requireAuth();
SecurityHeaders::send();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// Verify AJAX request
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit;
}

// Verify CSRF token
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!Session::validateCsrfToken($csrfToken)) {
    http_response_code(419);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Parse request body
$rawBody = (string) file_get_contents('php://input');
/** @var array<string,mixed> $input */
$input = (array) (json_decode($rawBody, true) ?? []);

// Helpers to safely extract typed values from the decoded JSON body
$inputStr = static function (string $key, string $default = '') use ($input): string {
    $v = $input[$key] ?? $default;
    return is_string($v) ? $v : $default;
};
$inputInt = static function (string $key, int $default = 0) use ($input): int {
    $v = $input[$key] ?? $default;
    if (is_int($v)) {
        return $v;
    }
    return is_numeric($v) ? (int) $v : $default;
};

$action = $inputStr('action');

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'get':
            // Get all environment configuration
            $config = EnvConfig::getAllMasked();
            echo json_encode([
                'ok' => true,
                'config' => $config
            ]);
            break;

        case 'get_groups':
            // Get configuration groups for UI
            $groups = EnvConfig::getConfigGroups();
            echo json_encode([
                'ok' => true,
                'groups' => $groups
            ]);
            break;

        case 'update':
            // Update environment configuration
            /** @var array<string,string> $newConfig */
            $newConfig = is_array($input['config'] ?? null) ? (array) $input['config'] : [];

            if (empty($newConfig)) {
                echo json_encode([
                    'ok' => false,
                    'error' => 'No configuration provided'
                ]);
                break;
            }

            $success = EnvConfig::update($newConfig);

            if ($success) {
                echo json_encode([
                    'ok' => true,
                    'message' => 'Environment configuration updated successfully'
                ]);
            } else {
                echo json_encode([
                    'ok' => false,
                    'error' => 'Failed to update environment configuration'
                ]);
            }
            break;

        case 'test_srp':
            // Verify SRP_API_KEY against the local /api/v1/status endpoint.
            // testLocalApiKey() uses CURLOPT_RESOLVE to route domain → 127.0.0.1
            // so SSL cert validates correctly and Apache vhost routing works.
            $apiKey = $inputStr('api_key');
            $result = EnvConfig::testLocalApiKey($apiKey);
            echo json_encode([
                'ok'       => $result['success'],
                'message'  => $result['message'],
                'response' => $result['response'] ?? null,
            ]);
            break;

        default:
            echo json_encode([
                'ok' => false,
                'error' => 'Invalid action'
            ]);
            break;
    }
} catch (\Throwable $e) {
    error_log('EnvConfig API error: ' . $e->getMessage());
    echo json_encode([
        'ok' => false,
        'error' => 'Internal server error'
    ]);
}

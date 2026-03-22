<?php

declare(strict_types=1);

namespace SRP\Middleware;

class SecurityHeaders
{
    /**
     * Send baseline security headers. Call early in every entry point.
     *
     * @param string $csp  Optional Content-Security-Policy value.
     *                      Pass '' to skip CSP (API endpoints).
     */
    public static function send(string $csp = ''): void
    {
        foreach (self::buildBaseline() as $name => $value) {
            header("$name: $value");
        }

        if ($csp !== '') {
            header('Content-Security-Policy: ' . $csp);
        }
    }

    /**
     * @return array<string,string>
     */
    private static function buildBaseline(): array
    {
        $headers = [
            'X-Frame-Options'        => 'DENY',
            'X-Content-Type-Options'  => 'nosniff',
            'Referrer-Policy'         => 'strict-origin-when-cross-origin',
            'Permissions-Policy'      => 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), microphone=(), payment=(), usb=()',
        ];

        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        return $headers;
    }
}

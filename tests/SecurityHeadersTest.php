<?php

declare(strict_types=1);

namespace SRP\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SRP\Middleware\SecurityHeaders;

class SecurityHeadersTest extends TestCase
{
    public function testBuildBaselineContainsRequiredHeaders(): void
    {
        $method = new ReflectionMethod(SecurityHeaders::class, 'buildBaseline');
        $method->setAccessible(true);

        $headers = $method->invoke(null);

        $this->assertArrayHasKey('X-Frame-Options', $headers);
        $this->assertSame('DENY', $headers['X-Frame-Options']);

        $this->assertArrayHasKey('X-Content-Type-Options', $headers);
        $this->assertSame('nosniff', $headers['X-Content-Type-Options']);

        $this->assertArrayHasKey('Referrer-Policy', $headers);
        $this->assertSame('strict-origin-when-cross-origin', $headers['Referrer-Policy']);

        $this->assertArrayHasKey('Permissions-Policy', $headers);
    }

    public function testBuildBaselineIncludesHstsWhenHttps(): void
    {
        $_SERVER['HTTPS'] = 'on';

        $method = new ReflectionMethod(SecurityHeaders::class, 'buildBaseline');
        $method->setAccessible(true);

        $headers = $method->invoke(null);

        $this->assertArrayHasKey('Strict-Transport-Security', $headers);
        $this->assertStringContainsString('max-age=', $headers['Strict-Transport-Security']);

        unset($_SERVER['HTTPS']);
    }

    public function testBuildBaselineExcludesHstsWhenHttp(): void
    {
        unset($_SERVER['HTTPS']);

        $method = new ReflectionMethod(SecurityHeaders::class, 'buildBaseline');
        $method->setAccessible(true);

        $headers = $method->invoke(null);

        $this->assertArrayNotHasKey('Strict-Transport-Security', $headers);
    }
}

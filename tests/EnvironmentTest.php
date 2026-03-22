<?php

declare(strict_types=1);

namespace SRP\Tests;

use PHPUnit\Framework\TestCase;
use SRP\Config\Environment;

class EnvironmentTest extends TestCase
{
    public function testGetReturnsEnvValue(): void
    {
        $_ENV['SRP_TEST_VAR'] = 'test_value';
        putenv('SRP_TEST_VAR=test_value');

        $this->assertSame('test_value', Environment::get('SRP_TEST_VAR'));

        unset($_ENV['SRP_TEST_VAR']);
        putenv('SRP_TEST_VAR');
    }

    public function testGetReturnsDefaultWhenMissing(): void
    {
        $this->assertSame('', Environment::get('SRP_NONEXISTENT_KEY_12345'));
        $this->assertSame('fallback', Environment::get('SRP_NONEXISTENT_KEY_12345', 'fallback'));
    }

    public function testGetReturnsEmptyStringWhenNoDefault(): void
    {
        $this->assertSame('', Environment::get('SRP_MISSING_NO_DEFAULT_99'));
    }
}

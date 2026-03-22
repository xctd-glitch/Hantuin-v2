<?php

declare(strict_types=1);

namespace SRP\Tests;

use PHPUnit\Framework\TestCase;
use SRP\Models\Validator;

class ValidatorTest extends TestCase
{
    // ── isValidIp ─────────────────────────────────────────

    public function testValidIpv4(): void
    {
        $this->assertTrue(Validator::isValidIp('192.168.1.1'));
        $this->assertTrue(Validator::isValidIp('8.8.8.8'));
        $this->assertTrue(Validator::isValidIp('0.0.0.0'));
    }

    public function testValidIpv6(): void
    {
        $this->assertTrue(Validator::isValidIp('::1'));
        $this->assertTrue(Validator::isValidIp('2001:db8::1'));
    }

    public function testInvalidIp(): void
    {
        $this->assertFalse(Validator::isValidIp(''));
        $this->assertFalse(Validator::isValidIp('not-an-ip'));
        $this->assertFalse(Validator::isValidIp('999.999.999.999'));
        $this->assertFalse(Validator::isValidIp('192.168.1'));
    }

    // ── sanitizeString ────────────────────────────────────

    public function testSanitizeStringRemovesControlCharacters(): void
    {
        $this->assertSame('hello', Validator::sanitizeString("hel\x00lo"));
        $this->assertSame('hello', Validator::sanitizeString("hel\x01lo"));
        $this->assertSame('hello', Validator::sanitizeString("hel\x7Flo"));
    }

    public function testSanitizeStringPreservesWhitespace(): void
    {
        $this->assertSame("hello\tworld", Validator::sanitizeString("hello\tworld"));
    }

    public function testSanitizeStringTruncates(): void
    {
        $this->assertSame('abcde', Validator::sanitizeString('abcdefghij', 5));
    }

    public function testSanitizeStringTrims(): void
    {
        $this->assertSame('hello', Validator::sanitizeString('  hello  '));
    }

    // ── isValidCountryCode ────────────────────────────────

    public function testValidCountryCodes(): void
    {
        $this->assertTrue(Validator::isValidCountryCode('US'));
        $this->assertTrue(Validator::isValidCountryCode('ID'));
        $this->assertTrue(Validator::isValidCountryCode('GB'));
        $this->assertTrue(Validator::isValidCountryCode('XX'));
    }

    public function testValidCountryCodeCaseInsensitive(): void
    {
        $this->assertTrue(Validator::isValidCountryCode('us'));
        $this->assertTrue(Validator::isValidCountryCode('Id'));
    }

    public function testInvalidCountryCode(): void
    {
        $this->assertFalse(Validator::isValidCountryCode(''));
        $this->assertFalse(Validator::isValidCountryCode('ZZ'));
        $this->assertFalse(Validator::isValidCountryCode('USA'));
        $this->assertFalse(Validator::isValidCountryCode('1'));
    }

    // ── isCountryAllowed ──────────────────────────────────

    public function testCountryAllowedDefaultMode(): void
    {
        $this->assertTrue(Validator::isCountryAllowed('US'));
        $this->assertTrue(Validator::isCountryAllowed('US', 'all'));
        $this->assertTrue(Validator::isCountryAllowed('US', 'all', 'ID,MY'));
    }

    public function testCountryAllowedWhitelist(): void
    {
        $this->assertTrue(Validator::isCountryAllowed('US', 'whitelist', 'US,GB,CA'));
        $this->assertFalse(Validator::isCountryAllowed('ID', 'whitelist', 'US,GB,CA'));
        $this->assertFalse(Validator::isCountryAllowed('US', 'whitelist', ''));
    }

    public function testCountryAllowedBlacklist(): void
    {
        $this->assertFalse(Validator::isCountryAllowed('US', 'blacklist', 'US,GB'));
        $this->assertTrue(Validator::isCountryAllowed('ID', 'blacklist', 'US,GB'));
        $this->assertTrue(Validator::isCountryAllowed('US', 'blacklist', ''));
    }

    public function testCountryAllowedCaseInsensitive(): void
    {
        $this->assertTrue(Validator::isCountryAllowed('us', 'whitelist', 'US,GB'));
        $this->assertFalse(Validator::isCountryAllowed('us', 'blacklist', 'US,GB'));
    }
}

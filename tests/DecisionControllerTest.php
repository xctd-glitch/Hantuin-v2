<?php

declare(strict_types=1);

namespace SRP\Tests;

use PHPUnit\Framework\TestCase;
use SRP\Controllers\DecisionController;

/**
 * Tests for DecisionController::resolve() — the pure routing logic.
 *
 * Note: isSystemMuted() is time-slot based and cannot be mocked without
 * refactoring. Tests account for this by asserting on time-independent
 * behaviour or accepting multiple valid outcomes.
 */
class DecisionControllerTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['SRP_VPN_CHECK_ENABLED'] = '0';
        putenv('SRP_VPN_CHECK_ENABLED=0');

        $memo = new \ReflectionProperty(\SRP\Models\Settings::class, 'memo');
        $memo->setAccessible(true);
        $memo->setValue(null, [
            'redirect_url' => 'https://example.com/offer',
            'system_on' => 1,
            'country_filter_mode' => 'all',
            'country_filter_list' => '',
            'postback_url' => '',
            'postback_token' => '',
            'updated_at' => time(),
        ]);
    }

    protected function tearDown(): void
    {
        unset($_ENV['SRP_VPN_CHECK_ENABLED']);
        putenv('SRP_VPN_CHECK_ENABLED');

        $memo = new \ReflectionProperty(\SRP\Models\Settings::class, 'memo');
        $memo->setAccessible(true);
        $memo->setValue(null, null);
    }

    private static function isCurrentlyMuted(): bool
    {
        return ((int)(time() / 60) % 5) >= 2;
    }

    // ── Input validation (time-independent) ───────────────

    public function testMissingClickIdThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('click_id is required');

        DecisionController::resolve([
            'click_id' => '',
            'country_code' => 'US',
            'user_agent' => 'mobile',
            'ip_address' => '1.2.3.4',
        ]);
    }

    public function testInvalidIpThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid IP address format');

        DecisionController::resolve([
            'click_id' => 'TEST123',
            'ip_address' => 'not-an-ip',
        ]);
    }

    public function testClickIdSanitized(): void
    {
        $result = DecisionController::resolve([
            'click_id' => 'test<script>123',
            'user_agent' => 'mobile',
            'ip_address' => '',
        ]);

        $this->assertSame('TESTSCRIPT123', $result['cid']);
    }

    // ── System off always gives B ─────────────────────────

    public function testSystemOffAlwaysB(): void
    {
        $memo = new \ReflectionProperty(\SRP\Models\Settings::class, 'memo');
        $memo->setAccessible(true);
        $memo->setValue(null, [
            'redirect_url' => 'https://example.com/offer',
            'system_on' => 0,
            'country_filter_mode' => 'all',
            'country_filter_list' => '',
            'postback_url' => '',
            'postback_token' => '',
            'updated_at' => time(),
        ]);

        $result = DecisionController::resolve([
            'click_id' => 'TEST1',
            'user_agent' => 'mobile',
            'ip_address' => '',
        ]);

        $this->assertSame('B', $result['decision']);
        $this->assertSame('system_off', $result['reason']);
    }

    // ── Device detection ──────────────────────────────────

    public function testBotAlwaysGetsB(): void
    {
        $result = DecisionController::resolve([
            'click_id' => 'TEST1',
            'user_agent' => 'Googlebot/2.1',
            'ip_address' => '',
        ]);

        $this->assertSame('B', $result['decision']);
        // Reason is 'bot' or 'muted' depending on time slot
        $this->assertContains($result['reason'], ['bot', 'muted']);
    }

    public function testDesktopAlwaysGetsB(): void
    {
        $result = DecisionController::resolve([
            'click_id' => 'TEST1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'ip_address' => '',
        ]);

        $this->assertSame('B', $result['decision']);
        $this->assertContains($result['reason'], ['not_mobile', 'muted']);
    }

    public function testMobileCanGetA(): void
    {
        if (self::isCurrentlyMuted()) {
            $this->markTestSkipped('Currently in muted time slot — mobile would get B/muted');
        }

        $result = DecisionController::resolve([
            'click_id' => 'TEST1',
            'user_agent' => 'mobile',
            'ip_address' => '',
        ]);

        $this->assertSame('A', $result['decision']);
        $this->assertSame('ok', $result['reason']);
        $this->assertSame('https://example.com/offer', $result['target']);
    }

    public function testShorthandDeviceAliasesNotDesktop(): void
    {
        foreach (['wap', 'mobile', 'tablet', 'ipad'] as $alias) {
            $result = DecisionController::resolve([
                'click_id' => 'TEST1',
                'user_agent' => $alias,
                'ip_address' => '',
            ]);
            $this->assertNotSame('not_mobile', $result['reason'], "Alias '$alias' was classified as not_mobile");
        }
    }

    // ── Country code handling ─────────────────────────────

    public function testInvalidCountryCodeReplacedWithXX(): void
    {
        $result = DecisionController::resolve([
            'click_id' => 'TEST1',
            'country_code' => 'ZZ',
            'user_agent' => 'mobile',
            'ip_address' => '',
        ]);

        $this->assertSame('XX', $result['cc']);
    }

    public function testValidCountryCodePreserved(): void
    {
        $result = DecisionController::resolve([
            'click_id' => 'TEST1',
            'country_code' => 'us',
            'user_agent' => 'mobile',
            'ip_address' => '',
        ]);

        $this->assertSame('US', $result['cc']);
    }

    // ── Country blocking ──────────────────────────────────

    public function testBlockedCountryGetsB(): void
    {
        if (self::isCurrentlyMuted()) {
            $this->markTestSkipped('Currently in muted time slot');
        }

        $memo = new \ReflectionProperty(\SRP\Models\Settings::class, 'memo');
        $memo->setAccessible(true);
        $memo->setValue(null, [
            'redirect_url' => 'https://example.com/offer',
            'system_on' => 1,
            'country_filter_mode' => 'blacklist',
            'country_filter_list' => 'US',
            'postback_url' => '',
            'postback_token' => '',
            'updated_at' => time(),
        ]);

        $result = DecisionController::resolve([
            'click_id' => 'TEST1',
            'country_code' => 'US',
            'user_agent' => 'mobile',
            'ip_address' => '',
        ]);

        $this->assertSame('B', $result['decision']);
        $this->assertSame('country_blocked', $result['reason']);
    }

    // ── No redirect URL ───────────────────────────────────

    public function testNoRedirectUrlGetsB(): void
    {
        if (self::isCurrentlyMuted()) {
            $this->markTestSkipped('Currently in muted time slot');
        }

        $memo = new \ReflectionProperty(\SRP\Models\Settings::class, 'memo');
        $memo->setAccessible(true);
        $memo->setValue(null, [
            'redirect_url' => '',
            'system_on' => 1,
            'country_filter_mode' => 'all',
            'country_filter_list' => '',
            'postback_url' => '',
            'postback_token' => '',
            'updated_at' => time(),
        ]);

        $result = DecisionController::resolve([
            'click_id' => 'TEST1',
            'user_agent' => 'mobile',
            'ip_address' => '',
        ]);

        $this->assertSame('B', $result['decision']);
        $this->assertSame('no_redirect_url', $result['reason']);
    }

    // ── Fallback URL structure ────────────────────────────

    public function testFallbackUrlContainsClickIdAndUserLp(): void
    {
        $result = DecisionController::resolve([
            'click_id' => 'TEST1',
            'user_lp' => 'LP-ONE',
            'user_agent' => 'desktop',
            'ip_address' => '',
        ]);

        $this->assertSame('B', $result['decision']);
        $this->assertStringContainsString('/_meetups/', $result['target']);
        $this->assertStringContainsString('click_id=test1', $result['target']);
        $this->assertStringContainsString('user_lp=lp-one', $result['target']);
    }

    // ── Return structure ──────────────────────────────────

    public function testResolveReturnsExpectedKeys(): void
    {
        $result = DecisionController::resolve([
            'click_id' => 'TEST1',
            'user_agent' => 'mobile',
            'ip_address' => '',
        ]);

        $this->assertArrayHasKey('decision', $result);
        $this->assertArrayHasKey('target', $result);
        $this->assertArrayHasKey('reason', $result);
        $this->assertArrayHasKey('cid', $result);
        $this->assertArrayHasKey('cc', $result);
        $this->assertArrayHasKey('ua', $result);
        $this->assertArrayHasKey('ip', $result);
        $this->assertArrayHasKey('lp', $result);
        $this->assertContains($result['decision'], ['A', 'B']);
    }
}

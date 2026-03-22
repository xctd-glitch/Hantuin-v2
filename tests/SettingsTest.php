<?php

declare(strict_types=1);

namespace SRP\Tests;

use PHPUnit\Framework\TestCase;
use SRP\Models\Settings;

class SettingsTest extends TestCase
{
    public function testGenerateTokenFormat(): void
    {
        $token = Settings::generateToken();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{48}$/', $token);
    }

    public function testGenerateTokenUniqueness(): void
    {
        $tokens = [];
        for ($i = 0; $i < 10; $i++) {
            $tokens[] = Settings::generateToken();
        }

        $this->assertCount(10, array_unique($tokens), 'Generated tokens should be unique');
    }

    public function testGetCountryFilterParsesDefaults(): void
    {
        // Reset memo cache
        $memo = new \ReflectionProperty(Settings::class, 'memo');
        $memo->setAccessible(true);

        // Set memo to simulate cached defaults
        $memo->setValue(null, [
            'redirect_url' => '',
            'system_on' => 0,
            'country_filter_mode' => 'all',
            'country_filter_list' => '',
            'postback_url' => '',
            'postback_token' => '',
            'updated_at' => 0,
        ]);

        $filter = Settings::getCountryFilter();

        $this->assertSame('all', $filter['mode']);
        $this->assertSame([], $filter['list']);

        // Cleanup
        $memo->setValue(null, null);
    }

    public function testGetCountryFilterParsesList(): void
    {
        $memo = new \ReflectionProperty(Settings::class, 'memo');
        $memo->setAccessible(true);

        $memo->setValue(null, [
            'redirect_url' => '',
            'system_on' => 1,
            'country_filter_mode' => 'whitelist',
            'country_filter_list' => 'us,id,gb',
            'postback_url' => '',
            'postback_token' => '',
            'updated_at' => 0,
        ]);

        $filter = Settings::getCountryFilter();

        $this->assertSame('whitelist', $filter['mode']);
        $this->assertSame(['US', 'ID', 'GB'], $filter['list']);

        $memo->setValue(null, null);
    }
}

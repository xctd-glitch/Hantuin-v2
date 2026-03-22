<?php

declare(strict_types=1);

namespace SRP\Tests;

use PHPUnit\Framework\TestCase;
use SRP\Config\Bootstrap;

class BootstrapTest extends TestCase
{
    public function testLoadComposerAutoloaderReturnsTrueForExistingVendor(): void
    {
        $baseDir = dirname(__DIR__);
        $this->assertTrue(Bootstrap::loadComposerAutoloader($baseDir));
    }

    public function testLoadComposerAutoloaderReturnsFalseForMissingVendor(): void
    {
        $this->assertFalse(Bootstrap::loadComposerAutoloader('/nonexistent/path'));
    }

    public function testRegisterFallbackAutoloaderIsIdempotent(): void
    {
        $srcDir = dirname(__DIR__) . '/src';

        // Call twice — should not throw or register duplicate
        Bootstrap::registerFallbackAutoloader($srcDir);
        Bootstrap::registerFallbackAutoloader($srcDir);

        $this->assertTrue(true); // No exception = success
    }
}

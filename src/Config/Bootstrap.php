<?php

declare(strict_types=1);

namespace SRP\Config;

final class Bootstrap
{
    private static bool $fallbackAutoloaderRegistered = false;

    public static function initialize(?string $baseDir = null, ?string $srcDir = null): void
    {
        $resolvedBaseDir = $baseDir ?? dirname(__DIR__, 2);
        $resolvedSrcDir = $srcDir ?? dirname(__DIR__);

        if (!self::loadComposerAutoloader($resolvedBaseDir)) {
            self::registerFallbackAutoloader($resolvedSrcDir);
        }

        Environment::load();
    }

    public static function loadComposerAutoloader(string $baseDir): bool
    {
        $autoloadPath = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        if (!is_file($autoloadPath)) {
            return false;
        }

        require_once $autoloadPath;
        return true;
    }

    public static function registerFallbackAutoloader(string $srcDir): void
    {
        if (self::$fallbackAutoloaderRegistered) {
            return;
        }

        $resolvedSrcDir = rtrim($srcDir, '/\\');

        spl_autoload_register(static function (string $class) use ($resolvedSrcDir): void {
            $prefix = 'SRP\\';
            if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
                return;
            }

            $relativeClass = substr($class, strlen($prefix));
            $file = $resolvedSrcDir
                . DIRECTORY_SEPARATOR
                . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass)
                . '.php';
            if (is_file($file)) {
                require $file;
            }
        });

        self::$fallbackAutoloaderRegistered = true;
    }
}

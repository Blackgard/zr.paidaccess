<?php

namespace Zr\PaidAccess\Tests\Support;

final class ModuleClassLoader
{
    /** @var array<string, string>|null */
    private static $map;

    public static function register(string $moduleRoot): void
    {
        if (self::$map === null) {
            self::$map = array_merge(
                require $moduleRoot . '/autoload.production.map.php',
                require $moduleRoot . '/autoload.test-extra.map.php'
            );
        }

        spl_autoload_register(static function (string $class) use ($moduleRoot): void {
            if (!isset(self::$map[$class])) {
                return;
            }

            $path = $moduleRoot . '/' . self::$map[$class];
            if (is_file($path)) {
                require_once $path;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public static function getMap(string $moduleRoot): array
    {
        if (self::$map === null) {
            self::$map = array_merge(
                require $moduleRoot . '/autoload.production.map.php',
                require $moduleRoot . '/autoload.test-extra.map.php'
            );
        }

        return self::$map;
    }
}

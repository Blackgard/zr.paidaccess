<?php

namespace Zr\PaidAccess\Gateway\Provider;

use Bitrix\Main\Loader;
use Zr\PaidAccess\Gateway\Contract\GatewayProviderInterface;

/**
 * Сканирует lib/Gateway/Providers/{папка}/{Папка}Provider.php и регистрирует провайдеры.
 */
class GatewayProviderLoader
{
    /** @var bool */
    private static $discovered = false;

    /** @var array<string, GatewayProviderInterface> */
    private static $providers = [];

    public static function registerAutoload($moduleId)
    {
        $baseDir = dirname(__DIR__) . '/Providers';
        if (!is_dir($baseDir)) {
            return;
        }

        $map = [];
        foreach (glob($baseDir . '/*', GLOB_ONLYDIR) ?: [] as $providerDir) {
            $folder = basename($providerDir);
            if ($folder === '' || $folder[0] === '_') {
                continue;
            }

            foreach (glob($providerDir . '/*.php') ?: [] as $file) {
                $classShort = basename($file, '.php');
                $class = 'Zr\\PaidAccess\\Gateway\\Providers\\' . $folder . '\\' . $classShort;
                $relative = 'lib/Gateway/Providers/' . $folder . '/' . $classShort . '.php';
                $map[$class] = $relative;
            }
        }

        if (!empty($map)) {
            Loader::registerAutoLoadClasses($moduleId, $map);
        }
    }

    /**
     * @return array<string, GatewayProviderInterface>
     */
    public static function getAll()
    {
        self::discover();

        return self::$providers;
    }

    public static function get($code)
    {
        self::discover();

        return isset(self::$providers[$code]) ? self::$providers[$code] : null;
    }

    public static function has($code)
    {
        return self::get($code) !== null;
    }

    private static function discover()
    {
        if (self::$discovered) {
            return;
        }

        self::$discovered = true;
        self::$providers = [];

        $baseDir = dirname(__DIR__) . '/Providers';
        if (!is_dir($baseDir)) {
            return;
        }

        foreach (glob($baseDir . '/*', GLOB_ONLYDIR) ?: [] as $providerDir) {
            $folder = basename($providerDir);
            if ($folder === '' || $folder[0] === '_') {
                continue;
            }

            $providerClass = 'Zr\\PaidAccess\\Gateway\\Providers\\' . $folder . '\\' . $folder . 'Provider';
            if (!class_exists($providerClass)) {
                continue;
            }

            $instance = new $providerClass();
            if (!$instance instanceof GatewayProviderInterface) {
                continue;
            }

            self::$providers[$instance->getCode()] = $instance;
        }
    }
}

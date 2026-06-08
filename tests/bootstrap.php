<?php

declare(strict_types=1);

$moduleRoot = dirname(__DIR__);

require_once $moduleRoot . '/vendor/autoload.php';
require_once $moduleRoot . '/tests/Stubs/BitrixStubs.php';

if (!function_exists('htmlspecialcharsbx')) {
    function htmlspecialcharsbx($string, $flags = ENT_COMPAT)
    {
        return htmlspecialchars((string)$string, $flags, 'UTF-8');
    }
}

\Zr\PaidAccess\Tests\Support\ModuleClassLoader::register($moduleRoot);

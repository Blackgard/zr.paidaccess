<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Tests\Support\ModuleClassLoader;

final class AutoloadMapTest extends TestCase
{
    public function testProductionAutoloadFilesExist(): void
    {
        $moduleRoot = dirname(__DIR__, 3);
        $map = require $moduleRoot . '/autoload.production.map.php';

        $this->assertNotEmpty($map);
        $this->assertArrayHasKey('Zr\PaidAccess\Install\EventInstaller', $map);
        $this->assertArrayHasKey('Zr\PaidAccess\Install\FileInstaller', $map);
        $this->assertArrayHasKey('Zr\PaidAccess\PublicUi\PaymentWidgetPresenter', $map);
        $this->assertArrayNotHasKey('Zr\PaidAccess\Install\ComponentInstaller', $map);
        $this->assertArrayNotHasKey('Zr\PaidAccess\Project\ProjectService', $map);

        $missing = [];
        foreach ($map as $class => $relativePath) {
            if (!is_file($moduleRoot . '/' . $relativePath)) {
                $missing[$class] = $relativePath;
            }
        }

        $this->assertSame([], $missing, 'Production autoload map contains missing files.');
    }

    public function testTestAutoloadMapExtendsProductionWithoutPathConflicts(): void
    {
        $moduleRoot = dirname(__DIR__, 3);
        $production = require $moduleRoot . '/autoload.production.map.php';
        $testExtra = require $moduleRoot . '/autoload.test-extra.map.php';
        $testMap = ModuleClassLoader::getMap($moduleRoot);

        foreach ($production as $class => $path) {
            $this->assertArrayHasKey($class, $testMap);
            $this->assertSame($path, $testMap[$class], 'Test autoload path must match production for ' . $class);
        }

        foreach ($testExtra as $class => $path) {
            $this->assertArrayHasKey($class, $testMap);
            $this->assertSame($path, $testMap[$class]);
            $this->assertFileExists($moduleRoot . '/' . $path);
        }
    }
}

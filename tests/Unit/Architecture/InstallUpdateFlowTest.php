<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

final class InstallUpdateFlowTest extends TestCase
{
    public function testDoUpdateRefreshesManagedFiles(): void
    {
        $moduleRoot = dirname(__DIR__, 3);
        $content = (string)file_get_contents($moduleRoot . '/install/index.php');

        $this->assertStringContainsString('use Zr\PaidAccess\Install\FileInstaller;', $content);
        $this->assertMatchesRegularExpression(
            '/public\s+function\s+DoUpdate\s*\(\)\s*\{.*FileInstaller::ensureFiles\(\);/s',
            $content
        );
    }
}

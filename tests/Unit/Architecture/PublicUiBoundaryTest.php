<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

final class PublicUiBoundaryTest extends TestCase
{
    public function testPublicUiDoesNotImportAdminLayer(): void
    {
        $moduleRoot = dirname(__DIR__, 3);
        $publicRoot = $moduleRoot . '/lib/Public';

        $violations = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($publicRoot));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $content = (string)file_get_contents($file->getPathname());
            if (preg_match('/use\s+Zr\\\\PaidAccess\\\\Admin\\\\/', $content) === 1) {
                $violations[] = str_replace($moduleRoot . '/', '', $file->getPathname());
            }
        }

        $this->assertSame([], $violations, 'PublicUi must depend on domain/read-model services, not Admin.');
    }
}

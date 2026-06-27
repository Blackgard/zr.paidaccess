<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Gateway\Providers\Tinkoff\TinkoffGateway;
use Zr\PaidAccess\PublicUi\PaymentWidgetPresenter;

final class ModuleBoundaryTest extends TestCase
{
    public function testArchitectureDocumentsExist(): void
    {
        $moduleRoot = dirname(__DIR__, 3);

        $this->assertFileExists($moduleRoot . '/docs/STRUCTURE.md');
        $this->assertFileExists($moduleRoot . '/docs/BOUNDARIES.md');
    }

    public function testTinkoffProviderImportsStayInsideProviderAndTests(): void
    {
        $moduleRoot = dirname(__DIR__, 3);
        $allowed = [
            'lib/Admin/TinkoffInitDiagnosticAdminService.php',
        ];
        $violations = [];

        foreach ($this->phpFiles($moduleRoot) as $path => $content) {
            if ($this->isInside($path, 'lib/Gateway/Providers/Tinkoff/')
                || $this->isInside($path, 'tests/')
                || in_array($path, $allowed, true)
            ) {
                continue;
            }

            if (preg_match('/use\s+Zr\\\\PaidAccess\\\\Gateway\\\\Providers\\\\Tinkoff\\\\/', $content) === 1) {
                $violations[] = $path;
            }
        }

        $this->assertSame([], $violations, 'Tinkoff provider imports must stay inside provider adapters or tests.');
    }

    public function testIblockUsageStaysInsideMigrationUtility(): void
    {
        $moduleRoot = dirname(__DIR__, 3);
        $allowed = [
            'admin/zr_paidaccess_util_document_iblock.php',
            'lib/Admin/DocumentIblockMigrationService.php',
            'lib/Utility/IblockIntrospectionService.php',
        ];
        $violations = [];

        foreach ($this->phpFiles($moduleRoot) as $path => $content) {
            if ($this->isInside($path, 'tests/') || in_array($path, $allowed, true)) {
                continue;
            }

            if (preg_match('/\\\\?CIBlock(?:Element|Property)?::/', $content) === 1) {
                $violations[] = $path;
            }
        }

        $this->assertSame([], $violations, 'CIBlock usage is allowed only in document migration utility files.');
    }

    public function testQrImageServiceIsDocumented(): void
    {
        $moduleRoot = dirname(__DIR__, 3);
        $boundaries = (string)file_get_contents($moduleRoot . '/docs/BOUNDARIES.md');
        $readme = (string)file_get_contents($moduleRoot . '/README.md');

        $this->assertStringContainsString('api.qrserver.com', $boundaries);
        $this->assertStringContainsString('QR image rendering', $boundaries);
        $this->assertStringContainsString('api.qrserver.com', $readme);
        $this->assertStringContainsString(
            PaymentWidgetPresenter::QR_IMAGE_SERVICE_URL,
            (string)file_get_contents($moduleRoot . '/lib/Public/PaymentWidgetPresenter.php')
        );
    }

    public function testTinkoffGatewayDoesNotRenderPaymentWidgetHtml(): void
    {
        $moduleRoot = dirname(__DIR__, 3);
        $source = (string)file_get_contents($moduleRoot . '/lib/Gateway/Providers/Tinkoff/TinkoffGateway.php');

        $this->assertStringNotContainsString('buildQrHtml', $source);
        $this->assertStringNotContainsString('buildRedirectHtml', $source);
        $this->assertStringNotContainsString('api.qrserver.com', $source);
    }

    public function testTinkoffGatewayDoesNotReadPaymentWidgetModeFromOptions(): void
    {
        $moduleRoot = dirname(__DIR__, 3);
        $source = (string)file_get_contents($moduleRoot . '/lib/Gateway/Providers/Tinkoff/TinkoffGateway.php');

        $this->assertStringNotContainsString('PaidAccessCore::getPaymentWidgetMode', $source);
        $this->assertStringNotContainsString('PaidAccessCore::isPaymentWidgetButtonMode', $source);
        $this->assertStringNotContainsString('PaidAccessCore::isPaymentWidgetQrMode', $source);
        $this->assertStringContainsString('paymentWidgetMode', $source);
    }

    /**
     * @return \Generator<string, string>
     */
    private function phpFiles(string $moduleRoot): \Generator
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($moduleRoot));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            $root = str_replace('\\', '/', $moduleRoot) . '/';

            yield str_replace($root, '', $path) => (string)file_get_contents($file->getPathname());
        }
    }

    private function isInside(string $path, string $directory): bool
    {
        return strncmp($path, $directory, strlen($directory)) === 0;
    }
}

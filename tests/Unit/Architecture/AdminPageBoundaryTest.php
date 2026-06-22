<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

final class AdminPageBoundaryTest extends TestCase
{
    public function testPaymentEditPageDelegatesPersistenceToAdminService(): void
    {
        $moduleRoot = dirname(__DIR__, 3);
        $source = (string)file_get_contents($moduleRoot . '/admin/zr_paidaccess_payment_edit.php');

        $this->assertStringContainsString('PaymentAdminEditService', $source);
        $this->assertStringNotContainsString('PaymentRepository::', $source);
        $this->assertStringNotContainsString('PaymentAdminService::save', $source);
    }

    public function testModuleHasNoD7ControllerSettingsUntilImplemented(): void
    {
        $moduleRoot = dirname(__DIR__, 3);

        $this->assertFileDoesNotExist($moduleRoot . '/.settings.php');
        $this->assertDirectoryDoesNotExist($moduleRoot . '/lib/controller');
    }
}

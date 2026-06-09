<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Log;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Log\ModuleEventLogService;

final class ModuleEventLogServiceTest extends TestCase
{
    public function testGlobalErrorUsesCodeOnlyAsContextKey(): void
    {
        $this->assertSame(
            'payment_page_gateway',
            ModuleEventLogService::buildAdminErrorContextKey('payment_page_gateway', null, 42)
        );
    }

    public function testPaymentScopedErrorIncludesPaymentId(): void
    {
        $this->assertSame(
            'payment_init_failed_p15',
            ModuleEventLogService::buildAdminErrorContextKey('payment_init_failed', 15, 42)
        );
    }

    public function testUserScopedErrorIncludesUserIdWhenNoPayment(): void
    {
        $this->assertSame(
            'payment_widget_exception_u7',
            ModuleEventLogService::buildAdminErrorContextKey('payment_widget_exception', null, 7)
        );
    }
}

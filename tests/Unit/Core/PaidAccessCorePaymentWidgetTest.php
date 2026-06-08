<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Core;

use Bitrix\Main\Config\Option;
use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\PaidAccessCore;

final class PaidAccessCorePaymentWidgetTest extends TestCase
{
    protected function setUp(): void
    {
        Option::reset();
    }

    public function testDefaultPaymentWidgetModeIsQr(): void
    {
        $this->assertSame(PaidAccessCore::PAYMENT_WIDGET_MODE_QR_SBP, PaidAccessCore::getPaymentWidgetMode('s1'));
        $this->assertTrue(PaidAccessCore::isPaymentWidgetQrMode('s1'));
        $this->assertFalse(PaidAccessCore::isPaymentWidgetButtonMode('s1'));
    }

    public function testPaymentWidgetButtonMode(): void
    {
        Option::set(
            PaidAccessCore::MODULE_ID,
            PaidAccessCore::OPTION_PAYMENT_WIDGET_MODE . '_s1',
            PaidAccessCore::PAYMENT_WIDGET_MODE_PAYMENT_BUTTON,
            's1'
        );

        $this->assertTrue(PaidAccessCore::isPaymentWidgetButtonMode('s1'));
        $this->assertFalse(PaidAccessCore::isPaymentWidgetQrMode('s1'));
    }

    public function testUnknownPaymentWidgetModeFallsBackToQr(): void
    {
        Option::set(
            PaidAccessCore::MODULE_ID,
            PaidAccessCore::OPTION_PAYMENT_WIDGET_MODE . '_s1',
            'unknown',
            's1'
        );

        $this->assertSame(PaidAccessCore::PAYMENT_WIDGET_MODE_QR_SBP, PaidAccessCore::getPaymentWidgetMode('s1'));
    }
}

<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Admin\PaymentAdminEditService;
use Zr\PaidAccess\Admin\PaymentAdminService;
use Zr\PaidAccess\Enum\PaymentStatus;

final class PaymentAdminEditServiceTest extends TestCase
{
    public function testBuildEmptyFormValuesUsesManualGatewayDefaults(): void
    {
        $values = PaymentAdminEditService::buildEmptyFormValues(0);

        $this->assertSame('', $values['USER_ID']);
        $this->assertSame(PaymentStatus::PENDING, $values['STATUS']);
        $this->assertSame(PaymentAdminService::MANUAL_GATEWAY_CODE, $values['GATEWAY_CODE']);
        $this->assertSame('RUB', $values['CURRENCY']);
    }

    public function testShouldProcessSaveDetectsSaveAndApply(): void
    {
        $this->assertTrue(PaymentAdminEditService::shouldProcessSave('Y', null));
        $this->assertTrue(PaymentAdminEditService::shouldProcessSave(null, 'Y'));
        $this->assertFalse(PaymentAdminEditService::shouldProcessSave(null, null));
        $this->assertFalse(PaymentAdminEditService::shouldProcessSave('', ''));
    }

    public function testExtractPostDataFromArray(): void
    {
        $data = PaymentAdminEditService::extractPostData([
            'USER_ID' => '12',
            'BILLING_PERIOD' => '2026-06',
            'AMOUNT' => '1430',
            'CURRENCY' => 'RUB',
            'STATUS' => PaymentStatus::PAID,
            'DESCRIPTION' => 'Test',
        ]);

        $this->assertSame('12', $data['USER_ID']);
        $this->assertSame('2026-06', $data['BILLING_PERIOD']);
        $this->assertSame(PaymentStatus::PAID, $data['STATUS']);
    }
}

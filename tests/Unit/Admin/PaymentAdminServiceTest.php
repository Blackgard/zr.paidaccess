<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Admin\PaymentAdminService;
use Zr\PaidAccess\Enum\PaymentStatus;

final class PaymentAdminServiceTest extends TestCase
{
    public function testEditableStatusTitlesExcludeAuthorized(): void
    {
        $titles = PaymentAdminService::getStatusTitles();

        $this->assertArrayHasKey(PaymentStatus::PENDING, $titles);
        $this->assertArrayHasKey(PaymentStatus::PAID, $titles);
        $this->assertArrayHasKey(PaymentStatus::CANCELLED, $titles);
        $this->assertArrayNotHasKey(PaymentStatus::AUTHORIZED, $titles);
    }

    public function testGetStatusTitleReturnsLegacyAuthorizedLabel(): void
    {
        $this->assertSame('Авторизован', PaymentAdminService::getStatusTitle(PaymentStatus::AUTHORIZED));
    }

    public function testValidateSaveDataRejectsAuthorizedStatus(): void
    {
        $errors = PaymentAdminService::validateSaveData([
            'USER_ID' => 0,
            'BILLING_PERIOD' => '',
            'AMOUNT' => 0,
            'STATUS' => PaymentStatus::AUTHORIZED,
        ], false, 1);

        $this->assertContains(
            'Статус «Авторизован» недоступен. Укажите «Оплачен» или другой актуальный статус',
            $errors
        );
    }

    public function testValidateSaveDataAcceptsCancelledStatus(): void
    {
        $errors = PaymentAdminService::validateSaveData([
            'USER_ID' => 0,
            'BILLING_PERIOD' => '',
            'AMOUNT' => 0,
            'STATUS' => PaymentStatus::CANCELLED,
        ], true);

        $this->assertNotContains(
            'Статус «Авторизован» недоступен. Укажите «Оплачен» или другой актуальный статус',
            $errors
        );
        $this->assertNotContains('Некорректный статус платежа', $errors);
    }
}

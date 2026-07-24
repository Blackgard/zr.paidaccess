<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\PublicUi;

use Bitrix\Main\Config\Option;
use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Enum\PaymentStatus;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\PublicUi\PaymentStatusPollService;

final class PaymentStatusPollServiceTest extends TestCase
{
    protected function setUp(): void
    {
        Option::reset();
        $_SERVER['HTTP_HOST'] = 'example.test';
        $_SERVER['HTTPS'] = 'on';
    }

    public function testUnauthorized(): void
    {
        $result = PaymentStatusPollService::buildResponse(1, 0);

        $this->assertFalse($result['ok']);
        $this->assertSame(401, $result['httpCode']);
        $this->assertSame('unauthorized', $result['error']);
    }

    public function testInvalidPaymentId(): void
    {
        $result = PaymentStatusPollService::buildResponse(0, 5);

        $this->assertFalse($result['ok']);
        $this->assertSame(400, $result['httpCode']);
    }

    public function testNotFound(): void
    {
        $result = PaymentStatusPollService::buildResponseForPayment(null, 5);

        $this->assertFalse($result['ok']);
        $this->assertSame(404, $result['httpCode']);
    }

    public function testForbiddenForOtherUser(): void
    {
        $result = PaymentStatusPollService::buildResponseForPayment([
            'USER_ID' => 10,
            'STATUS' => PaymentStatus::PENDING,
        ], 11);

        $this->assertFalse($result['ok']);
        $this->assertSame(403, $result['httpCode']);
    }

    public function testPaidResponse(): void
    {
        $result = PaymentStatusPollService::buildResponseForPayment([
            'USER_ID' => 12,
            'STATUS' => PaymentStatus::PAID,
        ], 12, 's1');

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['paid']);
        $this->assertSame(PaymentStatus::PAID, $result['status']);
        $this->assertSame(200, $result['httpCode']);
        $this->assertSame('https://example.test/', $result['redirectUrl']);
    }

    public function testPendingNotPaid(): void
    {
        $result = PaymentStatusPollService::buildResponseForPayment([
            'USER_ID' => 13,
            'STATUS' => PaymentStatus::PENDING,
        ], 13);

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['paid']);
        $this->assertSame(PaymentStatus::PENDING, $result['status']);
    }

    public function testStatusEndpointConstant(): void
    {
        $this->assertSame(
            '/local/modules/zr.paidaccess/tools/payment_status.php',
            PaymentStatusPollService::getStatusEndpointUrl()
        );
        $this->assertSame(
            PaidAccessCore::DEFAULT_PAYMENT_SUCCESS_REDIRECT_URL,
            ''
        );
    }
}

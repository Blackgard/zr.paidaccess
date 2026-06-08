<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Enum\PaymentStatus;
use Zr\PaidAccess\Payment\PaymentCancellationService;

final class PaymentCancellationServiceTest extends TestCase
{
    /**
     * @dataProvider canCancelProvider
     */
    public function testCanCancel(string $status, bool $expected): void
    {
        $this->assertSame($expected, PaymentCancellationService::canCancel([
            'STATUS' => $status,
        ]));
    }

    public function canCancelProvider(): array
    {
        return [
            'pending' => [PaymentStatus::PENDING, true],
            'failed' => [PaymentStatus::FAILED, true],
            'authorized' => [PaymentStatus::AUTHORIZED, true],
            'paid' => [PaymentStatus::PAID, true],
            'refunded' => [PaymentStatus::REFUNDED, false],
            'cancelled' => [PaymentStatus::CANCELLED, false],
        ];
    }

    public function testCanCancelRequiresStatusField(): void
    {
        $this->assertFalse(PaymentCancellationService::canCancel([]));
    }

    public function testCanCancelTreatsAuthorizedAsCancellable(): void
    {
        $this->assertTrue(PaymentCancellationService::canCancel([
            'STATUS' => PaymentStatus::AUTHORIZED,
        ]));
    }

    public function testShouldCallGatewayCancelOnlyForUnpaidTinkoffPayments(): void
    {
        $this->assertTrue(PaymentCancellationService::shouldCallGatewayCancel([
            'STATUS' => PaymentStatus::PENDING,
            'GATEWAY_CODE' => 'tinkoff',
            'GATEWAY_PAYMENT_ID' => '12345',
        ]));

        $this->assertTrue(PaymentCancellationService::shouldCallGatewayCancel([
            'STATUS' => PaymentStatus::PAID,
            'GATEWAY_CODE' => 'tinkoff',
            'GATEWAY_PAYMENT_ID' => '12345',
        ]));

        $this->assertFalse(PaymentCancellationService::shouldCallGatewayCancel([
            'STATUS' => PaymentStatus::PENDING,
            'GATEWAY_CODE' => 'manual',
            'GATEWAY_PAYMENT_ID' => '',
        ]));
    }
}

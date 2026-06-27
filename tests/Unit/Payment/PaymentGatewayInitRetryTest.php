<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Enum\PaymentStatus;
use Zr\PaidAccess\Payment\PaymentRepository;
use Zr\PaidAccess\Payment\SubscriptionPaymentService;

final class PaymentGatewayInitRetryTest extends TestCase
{
    public function testCanRetryGatewayInitAllowsCancelledWithoutGatewayPaymentId(): void
    {
        $this->assertTrue(SubscriptionPaymentService::canRetryGatewayInit([
            'STATUS' => PaymentStatus::CANCELLED,
            'GATEWAY_PAYMENT_ID' => '',
            'GATEWAY_CODE' => 'tinkoff',
        ]));
    }

    public function testCanRetryGatewayInitRejectsWhenGatewayPaymentIdPresent(): void
    {
        $this->assertFalse(SubscriptionPaymentService::canRetryGatewayInit([
            'STATUS' => PaymentStatus::CANCELLED,
            'GATEWAY_PAYMENT_ID' => '8722584496',
            'GATEWAY_CODE' => 'tinkoff',
        ]));
    }

    public function testIsReopenableForGatewayInitIncludesCancelled(): void
    {
        $this->assertTrue(PaymentRepository::isReopenableForGatewayInit([
            'STATUS' => PaymentStatus::CANCELLED,
            'GATEWAY_PAYMENT_ID' => null,
            'GATEWAY_CODE' => 'tinkoff',
        ]));
    }

    public function testPreparePaymentUsesReopenableCancelledPayments(): void
    {
        $moduleRoot = dirname(__DIR__, 3);
        $source = (string)file_get_contents($moduleRoot . '/lib/payment/SubscriptionPaymentService.php');

        $this->assertStringContainsString('findReopenableForCoveredPeriods', $source);
        $this->assertStringContainsString('reopenForGatewayInit', $source);
        $this->assertStringContainsString('retryGatewayInit', $source);
        $this->assertStringContainsString('payment_reopened_for_init', $source);
    }
}

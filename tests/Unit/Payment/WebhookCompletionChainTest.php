<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Enum\PaymentStatus;
use Zr\PaidAccess\Gateway\Dto\WebhookHandleResult;
use Zr\PaidAccess\Gateway\GatewayTestService;
use Zr\PaidAccess\Gateway\Providers\Tinkoff\TinkoffStatusMapper;

/**
 * Unit-тесты ключевых шагов runtime-цепочки webhook → completion.
 * Полная интеграция с ORM покрывается отдельно на стенде Bitrix.
 */
final class WebhookCompletionChainTest extends TestCase
{
    public function testConfirmedStatusMapsToPaidInternalStatus(): void
    {
        $this->assertTrue(TinkoffStatusMapper::isPaidStatus('CONFIRMED'));
        $this->assertSame(PaymentStatus::PAID, TinkoffStatusMapper::toInternal('CONFIRMED'));
    }

    public function testAuthorizedStatusIsIntermediateAndNotPaid(): void
    {
        $this->assertFalse(TinkoffStatusMapper::isPaidStatus('AUTHORIZED'));
        $this->assertSame(PaymentStatus::AUTHORIZED, TinkoffStatusMapper::toInternal('AUTHORIZED'));
    }

    public function testValidPaidWebhookResultSignalsCompletionPath(): void
    {
        $result = new WebhookHandleResult(
            true,
            true,
            'PA-42-2026-06',
            '12345678',
            'CONFIRMED',
            '',
            PaymentStatus::PAID
        );

        $this->assertTrue($result->valid);
        $this->assertTrue($result->paid);
        $this->assertSame(PaymentStatus::PAID, $result->internalStatus);
    }

    public function testIntermediateWebhookDoesNotSignalCompletion(): void
    {
        $result = new WebhookHandleResult(
            true,
            false,
            'PA-42-2026-06',
            '12345678',
            'AUTHORIZED',
            '',
            PaymentStatus::AUTHORIZED
        );

        $this->assertTrue($result->valid);
        $this->assertFalse($result->paid);
    }

    public function testGatewayTestPaymentOrderPrefixIsRecognized(): void
    {
        $orderId = GatewayTestService::ORDER_PREFIX . '7-99';

        $this->assertTrue(GatewayTestService::isGatewayTestPayment(['ORDER_ID' => $orderId]));
        $this->assertFalse(GatewayTestService::isGatewayTestPayment(['ORDER_ID' => 'PA-1-2026-06']));
    }
}

<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Gateway;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Enum\PaymentStatus;
use Zr\PaidAccess\Gateway\Dto\InitPaymentResult;
use Zr\PaidAccess\Gateway\Providers\Tinkoff\TinkoffGateway;

final class TinkoffStaleSessionTest extends TestCase
{
    public function testDetectsUnavailablePaymentStatusInFetchResult(): void
    {
        $gateway = $this->createGatewayWithoutCredentials();

        $result = InitPaymentResult::fail(
            'Платёж недоступен для оплаты (статус: DEADLINE_EXPIRED).',
            json_encode(['Success' => true, 'Status' => 'DEADLINE_EXPIRED'], JSON_UNESCAPED_UNICODE)
        );

        $this->assertTrue($gateway->isStalePaymentSessionFailure($result));
    }

    public function testDetectsHttpErrorInFetchResult(): void
    {
        $gateway = $this->createGatewayWithoutCredentials();

        $result = InitPaymentResult::fail(
            'HTTP error Array',
            json_encode(['Success' => false, 'Message' => 'HTTP error', 'Details' => 'timeout'], JSON_UNESCAPED_UNICODE)
        );

        $this->assertTrue($gateway->isStalePaymentSessionFailure($result));
    }

    public function testIgnoresCancelFailureForPendingPayment(): void
    {
        $gateway = $this->createGatewayWithoutCredentials();

        $this->assertTrue($gateway->isIgnorableCancelFailure([
            'Success' => false,
            'Message' => 'HTTP error',
            'Details' => 'timeout',
        ], PaymentStatus::PENDING));
    }

    public function testDoesNotIgnoreCancelFailureForPaidPayment(): void
    {
        $gateway = $this->createGatewayWithoutCredentials();

        $this->assertFalse($gateway->isIgnorableCancelFailure([
            'Success' => false,
            'Message' => 'HTTP error',
            'Details' => 'timeout',
        ], PaymentStatus::PAID));
    }

    private function createGatewayWithoutCredentials(): TinkoffGateway
    {
        return new TinkoffGateway([
            'ID' => 1,
            'PROVIDER' => 'tinkoff',
            'OPTIONS' => json_encode([
                'terminal_key' => 'test-terminal',
                'secret_key' => 'test-secret',
            ], JSON_UNESCAPED_UNICODE),
        ]);
    }
}

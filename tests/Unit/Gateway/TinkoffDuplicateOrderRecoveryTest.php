<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Gateway;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Gateway\Dto\InitPaymentRequest;
use Zr\PaidAccess\Gateway\Providers\Tinkoff\TinkoffApiClient;
use Zr\PaidAccess\Gateway\Providers\Tinkoff\TinkoffDuplicateOrderRecovery;

final class TinkoffDuplicateOrderRecoveryTest extends TestCase
{
    public function testRecoverPicksAwaitingPayment(): void
    {
        $client = $this->createMock(TinkoffApiClient::class);
        $client->method('checkOrder')->willReturn([
            'Success' => true,
            'OrderId' => 'PA-1-2026-06',
            'Payments' => [
                [
                    'PaymentId' => '100',
                    'Status' => 'REJECTED',
                ],
                [
                    'PaymentId' => '200',
                    'Status' => 'NEW',
                    'PaymentURL' => 'https://pay.example/200',
                ],
            ],
        ]);

        $result = TinkoffDuplicateOrderRecovery::recover(
            $client,
            new InitPaymentRequest('PA-1-2026-06', 1430.0, 'RUB', 'Подписка', 1)
        );

        $this->assertTrue($result->success);
        $this->assertSame('200', $result->gatewayPaymentId);
        $this->assertSame('https://pay.example/200', $result->paymentUrl);
    }

    public function testRecoverFailsWhenCheckOrderReturnsError(): void
    {
        $client = $this->createMock(TinkoffApiClient::class);
        $client->method('checkOrder')->willReturn([
            'Success' => false,
            'Message' => 'Not found',
        ]);

        $result = TinkoffDuplicateOrderRecovery::recover(
            $client,
            new InitPaymentRequest('PA-1-2026-06', 1430.0, 'RUB', 'Подписка', 1)
        );

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Not found', $result->errorMessage);
    }
}

<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Gateway;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Gateway\GatewayTestService;

final class GatewayTestServiceTest extends TestCase
{
    public function testIsGatewayTestPaymentByOrderPrefix(): void
    {
        $this->assertTrue(GatewayTestService::isGatewayTestPayment([
            'ORDER_ID' => GatewayTestService::ORDER_PREFIX . '42-test',
        ]));
        $this->assertFalse(GatewayTestService::isGatewayTestPayment([
            'ORDER_ID' => 'PA-1-2026-05',
        ]));
        $this->assertFalse(GatewayTestService::isGatewayTestPayment([]));
    }
}

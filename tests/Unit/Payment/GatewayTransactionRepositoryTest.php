<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Enum\GatewayEventType;
use Zr\PaidAccess\Payment\GatewayTransactionRepository;

final class GatewayTransactionRepositoryTest extends TestCase
{
    public function testMapApiMethodToEventType(): void
    {
        $this->assertSame(GatewayEventType::INIT, GatewayTransactionRepository::mapApiMethodToEventType('Init'));
        $this->assertSame(GatewayEventType::GET_QR, GatewayTransactionRepository::mapApiMethodToEventType('GetQr'));
        $this->assertSame(GatewayEventType::STATUS_CHECK, GatewayTransactionRepository::mapApiMethodToEventType('GetState'));
        $this->assertSame(GatewayEventType::CANCEL, GatewayTransactionRepository::mapApiMethodToEventType('Cancel'));
    }
}

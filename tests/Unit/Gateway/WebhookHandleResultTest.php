<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Gateway;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Enum\PaymentStatus;
use Zr\PaidAccess\Gateway\Dto\WebhookHandleResult;

final class WebhookHandleResultTest extends TestCase
{
    public function testCarriesInternalStatusFromGateway(): void
    {
        $result = new WebhookHandleResult(
            true,
            false,
            'PA-1-2026-05',
            '999',
            'REJECTED',
            '',
            PaymentStatus::FAILED
        );

        $this->assertTrue($result->valid);
        $this->assertFalse($result->paid);
        $this->assertSame('REJECTED', $result->gatewayStatus);
        $this->assertSame(PaymentStatus::FAILED, $result->internalStatus);
    }

    public function testInternalStatusDefaultsToEmptyString(): void
    {
        $result = new WebhookHandleResult(false, false, '', '', '', 'Invalid Token');

        $this->assertSame('', $result->internalStatus);
    }
}

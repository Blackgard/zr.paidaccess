<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Gateway;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Gateway\Providers\Tinkoff\TinkoffPaymentUrlResolver;

final class TinkoffPaymentUrlResolverTest extends TestCase
{
    public function testExtractFromArraySupportsBothKeyVariants(): void
    {
        $this->assertSame(
            'https://pay.example/1',
            TinkoffPaymentUrlResolver::extractFromArray(['PaymentURL' => 'https://pay.example/1'])
        );
        $this->assertSame(
            'https://pay.example/2',
            TinkoffPaymentUrlResolver::extractFromArray(['PaymentUrl' => 'https://pay.example/2'])
        );
    }

    public function testExtractFromPayloadParsesJsonString(): void
    {
        $url = TinkoffPaymentUrlResolver::extractFromPayload(
            '{"Success":true,"PaymentURL":"https://pay.example/form"}'
        );

        $this->assertSame('https://pay.example/form', $url);
    }

    public function testIsAwaitingPaymentForNewStatuses(): void
    {
        $this->assertTrue(TinkoffPaymentUrlResolver::isAwaitingPayment('NEW'));
        $this->assertTrue(TinkoffPaymentUrlResolver::isAwaitingPayment('form_showed'));
        $this->assertFalse(TinkoffPaymentUrlResolver::isAwaitingPayment('CONFIRMED'));
    }
}

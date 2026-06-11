<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Core;

use Bitrix\Main\Config\Option;
use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\PaidAccessCore;

final class PaidAccessCoreDuplicateOrderPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        Option::reset();
    }

    public function testDefaultPolicyIsFail(): void
    {
        $this->assertSame(
            PaidAccessCore::PAYMENT_DUPLICATE_ORDER_FAIL,
            PaidAccessCore::getPaymentDuplicateOrderPolicy('s1')
        );
    }

    public function testReadsConfiguredPolicy(): void
    {
        Option::set(
            PaidAccessCore::MODULE_ID,
            PaidAccessCore::OPTION_PAYMENT_DUPLICATE_ORDER_POLICY . '_s1',
            PaidAccessCore::PAYMENT_DUPLICATE_ORDER_REUSE,
            's1'
        );

        $this->assertSame(
            PaidAccessCore::PAYMENT_DUPLICATE_ORDER_REUSE,
            PaidAccessCore::getPaymentDuplicateOrderPolicy('s1')
        );
    }

    public function testUnknownPolicyFallsBackToFail(): void
    {
        Option::set(
            PaidAccessCore::MODULE_ID,
            PaidAccessCore::OPTION_PAYMENT_DUPLICATE_ORDER_POLICY . '_s1',
            'unknown',
            's1'
        );

        $this->assertSame(
            PaidAccessCore::PAYMENT_DUPLICATE_ORDER_FAIL,
            PaidAccessCore::getPaymentDuplicateOrderPolicy('s1')
        );
    }
}

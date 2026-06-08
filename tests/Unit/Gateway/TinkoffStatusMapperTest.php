<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Gateway;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Enum\PaymentStatus;
use Zr\PaidAccess\Gateway\Providers\Tinkoff\TinkoffStatusMapper;

final class TinkoffStatusMapperTest extends TestCase
{
    /**
     * @dataProvider internalStatusProvider
     */
    public function testToInternal(string $bankStatus, string $expected): void
    {
        $this->assertSame($expected, TinkoffStatusMapper::toInternal($bankStatus));
    }

    public function internalStatusProvider(): array
    {
        return [
            'confirmed' => ['CONFIRMED', PaymentStatus::PAID],
            'authorized' => ['AUTHORIZED', PaymentStatus::PAID],
            'rejected' => ['REJECTED', PaymentStatus::FAILED],
            'canceled' => ['CANCELED', PaymentStatus::CANCELLED],
            'reversed' => ['REVERSED', PaymentStatus::CANCELLED],
            'refunded' => ['REFUNDED', PaymentStatus::REFUNDED],
            'new' => ['NEW', PaymentStatus::PENDING],
            'lowercase' => ['confirmed', PaymentStatus::PAID],
        ];
    }

    /**
     * @dataProvider paidStatusProvider
     */
    public function testIsPaidStatus(string $bankStatus, bool $expected): void
    {
        $this->assertSame($expected, TinkoffStatusMapper::isPaidStatus($bankStatus));
    }

    public function paidStatusProvider(): array
    {
        return [
            'confirmed' => ['CONFIRMED', true],
            'authorized' => ['AUTHORIZED', true],
            'rejected' => ['REJECTED', false],
        ];
    }
}

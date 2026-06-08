<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Enum;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Enum\PaymentStatus;

final class PaymentStatusTest extends TestCase
{
    public function testIsValidAcceptsKnownStatuses(): void
    {
        foreach (PaymentStatus::ALL as $status) {
            $this->assertTrue(PaymentStatus::isValid($status));
        }
    }

    public function testIsValidRejectsUnknownStatus(): void
    {
        $this->assertFalse(PaymentStatus::isValid('unknown'));
    }

    /**
     * @dataProvider paidLikeProvider
     */
    public function testIsPaidLike(string $status, bool $expected): void
    {
        $this->assertSame($expected, PaymentStatus::isPaidLike($status));
    }

    public function paidLikeProvider(): array
    {
        return [
            'paid' => [PaymentStatus::PAID, true],
            'authorized' => [PaymentStatus::AUTHORIZED, true],
            'pending' => [PaymentStatus::PENDING, false],
            'failed' => [PaymentStatus::FAILED, false],
            'refunded' => [PaymentStatus::REFUNDED, false],
            'cancelled' => [PaymentStatus::CANCELLED, false],
        ];
    }

    /**
     * @dataProvider grantsAccessProvider
     */
    public function testGrantsAccess(string $status, bool $expected): void
    {
        $this->assertSame($expected, PaymentStatus::grantsAccess($status));
    }

    public function grantsAccessProvider(): array
    {
        return [
            'paid' => [PaymentStatus::PAID, true],
            'authorized' => [PaymentStatus::AUTHORIZED, false],
            'pending' => [PaymentStatus::PENDING, false],
            'cancelled' => [PaymentStatus::CANCELLED, false],
            'refunded' => [PaymentStatus::REFUNDED, false],
        ];
    }
}

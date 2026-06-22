<?php

namespace Zr\PaidAccess\Tests\Unit\Subscription;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Subscription\SubscriptionAmountBreakdown;

class SubscriptionAmountBreakdownTest extends TestCase
{
    public function testChargeTotalIsSumOfParts(): void
    {
        $breakdown = new SubscriptionAmountBreakdown(1000.0, 130.0, 300.0);

        $this->assertSame(1000.0, $breakdown->fundAmount);
        $this->assertSame(130.0, $breakdown->taxAmount);
        $this->assertSame(300.0, $breakdown->maintenanceAmount);
        $this->assertSame(1430.0, $breakdown->chargeTotal);
    }

    public function testToPaymentAmountFields(): void
    {
        $breakdown = new SubscriptionAmountBreakdown(1000.0, 130.0, 300.0);

        $this->assertSame([
            'AMOUNT' => 1430.0,
            'FUND_AMOUNT' => 1000.0,
            'TAX_AMOUNT' => 130.0,
            'MAINTENANCE_AMOUNT' => 300.0,
        ], $breakdown->toPaymentAmountFields());
    }

    public function testFromPaymentRowUsesSnapshot(): void
    {
        $breakdown = SubscriptionAmountBreakdown::fromPaymentRow([
            'AMOUNT' => 1430.0,
            'FUND_AMOUNT' => 1000.0,
            'TAX_AMOUNT' => 130.0,
            'MAINTENANCE_AMOUNT' => 300.0,
        ]);

        $this->assertSame(1430.0, $breakdown->chargeTotal);
        $this->assertSame(1000.0, $breakdown->fundAmount);
    }

    public function testFromPaymentRowLegacyFallback(): void
    {
        $breakdown = SubscriptionAmountBreakdown::fromPaymentRow([
            'AMOUNT' => 1000.0,
        ]);

        $this->assertSame(1000.0, $breakdown->fundAmount);
        $this->assertSame(1000.0, $breakdown->chargeTotal);
    }

    public function testResolveFundAmountFromPayment(): void
    {
        $fund = SubscriptionAmountBreakdown::resolveFundAmountFromPayment([
            'AMOUNT' => 1430.0,
            'FUND_AMOUNT' => 1000.0,
        ]);

        $this->assertSame(1000.0, $fund);
    }

    public function testForManualPaymentAmountTreatsUnknownAsFundOnly(): void
    {
        $manual = SubscriptionAmountBreakdown::forManualPaymentAmount(500.0);

        $this->assertSame(500.0, $manual->fundAmount);
        $this->assertSame(500.0, $manual->chargeTotal);
        $this->assertSame(0.0, $manual->taxAmount);
    }

    public function testMultiplyScalesAllComponents(): void
    {
        $monthly = new SubscriptionAmountBreakdown(1000.0, 130.0, 300.0);
        $total = $monthly->multiply(3);

        $this->assertSame(3000.0, $total->fundAmount);
        $this->assertSame(390.0, $total->taxAmount);
        $this->assertSame(900.0, $total->maintenanceAmount);
        $this->assertSame(4290.0, $total->chargeTotal);
    }

    public function testDivideRestoresMonthlyAmount(): void
    {
        $monthly = new SubscriptionAmountBreakdown(1000.0, 130.0, 300.0);
        $total = $monthly->multiply(4);
        $restored = $total->divide(4);

        $this->assertSame(1000.0, $restored->fundAmount);
        $this->assertSame(130.0, $restored->taxAmount);
        $this->assertSame(300.0, $restored->maintenanceAmount);
    }
}

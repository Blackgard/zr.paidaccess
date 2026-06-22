<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Payment\PaymentCoveredPeriods;

final class PaymentCoveredPeriodsTest extends TestCase
{
    public function testEncodeDecodeRoundTrip(): void
    {
        $periods = ['2026-03', '2026-04', '2026-05'];
        $json = PaymentCoveredPeriods::encode($periods);

        $this->assertSame($periods, PaymentCoveredPeriods::decode($json));
    }

    public function testFromPaymentRowUsesCoveredPeriodsWhenPresent(): void
    {
        $periods = PaymentCoveredPeriods::fromPaymentRow([
            'BILLING_PERIOD' => '2026-06',
            'COVERED_PERIODS' => PaymentCoveredPeriods::encode(['2026-04', '2026-05', '2026-06']),
        ]);

        $this->assertSame(['2026-04', '2026-05', '2026-06'], $periods);
    }

    public function testFromPaymentRowFallsBackToBillingPeriod(): void
    {
        $periods = PaymentCoveredPeriods::fromPaymentRow([
            'BILLING_PERIOD' => '2026-06',
        ]);

        $this->assertSame(['2026-06'], $periods);
    }

    public function testPaymentCoversPeriod(): void
    {
        $payment = [
            'COVERED_PERIODS' => PaymentCoveredPeriods::encode(['2026-03', '2026-04']),
        ];

        $this->assertTrue(PaymentCoveredPeriods::paymentCoversPeriod($payment, '2026-03'));
        $this->assertFalse(PaymentCoveredPeriods::paymentCoversPeriod($payment, '2026-05'));
    }
}

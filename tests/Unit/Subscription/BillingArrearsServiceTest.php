<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Subscription;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Subscription\BillingArrearsService;

final class BillingArrearsServiceTest extends TestCase
{
    public function testFormatPeriodsRangeLabelForSinglePeriod(): void
    {
        $label = BillingArrearsService::formatPeriodsRangeLabel(['2026-06'], 's1');

        $this->assertSame('июнь 2026', $label);
    }

    public function testFormatPeriodsRangeLabelForMultiplePeriods(): void
    {
        $label = BillingArrearsService::formatPeriodsRangeLabel(['2026-03', '2026-04', '2026-05'], 's1');

        $this->assertSame('март 2026 — май 2026', $label);
    }
}

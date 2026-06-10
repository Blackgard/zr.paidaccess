<?php

namespace Zr\PaidAccess\Tests\Unit\Fund;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Fund\FundBalanceService;

class FundBalanceServiceTest extends TestCase
{
    public function testCalculateAvailableBalance(): void
    {
        $this->assertSame(700.0, FundBalanceService::calculateAvailableBalance(1000.0, 300.0));
        $this->assertSame(-50.0, FundBalanceService::calculateAvailableBalance(100.0, 150.0));
    }

    public function testRoundRublesToInteger(): void
    {
        $this->assertSame(100, FundBalanceService::roundRubles(99.6));
        $this->assertSame(101, FundBalanceService::roundRubles(100.5));
    }

    public function testFormatRublesWithThousandsSeparator(): void
    {
        $this->assertSame('12 500', FundBalanceService::formatRubles(12500.0));
    }
}

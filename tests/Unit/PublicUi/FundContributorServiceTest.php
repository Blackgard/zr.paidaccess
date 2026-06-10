<?php

namespace Zr\PaidAccess\Tests\Unit\PublicUi;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\PublicUi\FundContributorService;

class FundContributorServiceTest extends TestCase
{
    public function testBuildContributorDataMapsTotals(): void
    {
        $data = FundContributorService::buildContributorData(42, 7, 's1', [
            'income' => 1500.0,
            'expense' => 500.0,
            'payment_count' => 3,
        ]);

        $this->assertSame(42, $data['USER_ID']);
        $this->assertSame(7, $data['FUND_ID']);
        $this->assertSame('s1', $data['SITE_ID']);
        $this->assertSame(1500, $data['TOTAL_CONTRIBUTED']);
        $this->assertSame(500, $data['TOTAL_REFUNDED']);
        $this->assertSame(1000, $data['NET_BALANCE']);
        $this->assertSame(3, $data['PAYMENT_COUNT']);
        $this->assertSame('1 500', $data['TOTAL_CONTRIBUTED_FORMATTED']);
        $this->assertSame('500', $data['TOTAL_REFUNDED_FORMATTED']);
        $this->assertSame('1 000', $data['NET_BALANCE_FORMATTED']);
        $this->assertSame([], $data['ITEMS']);
    }

    public function testGetContributorDataReturnsZerosForGuest(): void
    {
        $data = FundContributorService::getContributorData(0, 's1');

        $this->assertSame(0, $data['USER_ID']);
        $this->assertSame(0, $data['FUND_ID']);
        $this->assertSame(0, $data['TOTAL_CONTRIBUTED']);
        $this->assertSame(0, $data['NET_BALANCE']);
        $this->assertSame(0, $data['PAYMENT_COUNT']);
    }
}

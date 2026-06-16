<?php

namespace Zr\PaidAccess\Tests\Unit\Fund;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Enum\FundExpenseAllocationMode;
use Zr\PaidAccess\Fund\FundExpenseAllocationService;

final class FundExpenseAllocationServiceTest extends TestCase
{
    public function testDistributeAmountEvenlyAcrossThreeParticipants(): void
    {
        $rows = FundExpenseAllocationService::distributeAmount(100.0, [10, 20, 30]);

        $this->assertCount(3, $rows);
        $this->assertSame(10, $rows[0]['USER_ID']);
        $this->assertSame(20, $rows[1]['USER_ID']);
        $this->assertSame(30, $rows[2]['USER_ID']);
        $this->assertEqualsWithDelta(33.34, $rows[0]['AMOUNT'], 0.001);
        $this->assertEqualsWithDelta(33.33, $rows[1]['AMOUNT'], 0.001);
        $this->assertEqualsWithDelta(33.33, $rows[2]['AMOUNT'], 0.001);
        $this->assertEqualsWithDelta(100.0, array_sum(array_column($rows, 'AMOUNT')), 0.001);
    }

    public function testSelectParticipantIdsRandomLimitsCount(): void
    {
        $all = [1, 2, 3, 4, 5];
        $selected = FundExpenseAllocationService::selectParticipantIds(
            $all,
            FundExpenseAllocationMode::RANDOM,
            2
        );

        $this->assertCount(2, $selected);
        foreach ($selected as $userId) {
            $this->assertContains($userId, $all);
        }
    }

    public function testSelectParticipantIdsEvenUsesAll(): void
    {
        $all = [1, 2, 3];
        $selected = FundExpenseAllocationService::selectParticipantIds(
            $all,
            FundExpenseAllocationMode::EVEN,
            1
        );

        $this->assertSame($all, $selected);
    }

    public function testBuildAllocationsRequiresParticipants(): void
    {
        $this->expectException(\RuntimeException::class);
        FundExpenseAllocationService::buildAllocations(1, 100.0, 's1', []);
    }

    public function testBuildAllocationsEvenMode(): void
    {
        $rows = FundExpenseAllocationService::buildAllocations(
            1,
            50.0,
            's1',
            [100, 200]
        );

        $this->assertCount(2, $rows);
        $this->assertEqualsWithDelta(50.0, array_sum(array_column($rows, 'AMOUNT')), 0.001);
    }
}

<?php

namespace Zr\PaidAccess\Tests\Unit\Fund;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Fund\FundMovementService;

class FundMovementServiceTest extends TestCase
{
    public function testValidateExpenseAmountRejectsNonPositive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        FundMovementService::validateExpenseAmount(0);
    }

    public function testValidateExpenseAmountAcceptsPositive(): void
    {
        FundMovementService::validateExpenseAmount(0.01);
        $this->assertTrue(true);
    }

    public function testValidateIncomeAmountRejectsNonPositive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        FundMovementService::validateIncomeAmount(0);
    }

    public function testValidateIncomeAmountAcceptsPositive(): void
    {
        FundMovementService::validateIncomeAmount(100);
        $this->assertTrue(true);
    }
}

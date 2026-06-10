<?php

namespace Zr\PaidAccess\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Admin\FundAdminService;
use Zr\PaidAccess\Enum\FundMovementType;

class FundAdminServiceTest extends TestCase
{
    public function testValidateFundFormRequiresFields(): void
    {
        $errors = FundAdminService::validateFundForm([], true);
        $this->assertNotEmpty($errors);
    }

    public function testValidateFundFormRejectsInvalidCode(): void
    {
        $errors = FundAdminService::validateFundForm([
            'SITE_ID' => 's1',
            'NAME' => 'Test',
            'CODE' => 'bad code!',
        ], true);

        $this->assertNotEmpty($errors);
    }

    public function testBuildMovementFilterIncludesFundId(): void
    {
        $filter = FundAdminService::buildMovementFilter(5, ['TYPE' => FundMovementType::INCOME]);
        $this->assertSame(5, $filter['=FUND_ID']);
        $this->assertSame(FundMovementType::INCOME, $filter['=TYPE']);
    }
}

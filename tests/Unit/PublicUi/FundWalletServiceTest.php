<?php

namespace Zr\PaidAccess\Tests\Unit\PublicUi;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\PublicUi\FundWalletService;

class FundWalletServiceTest extends TestCase
{
    public function testRoundRublesToInteger(): void
    {
        $this->assertSame(100, FundWalletService::roundRubles(99.6));
        $this->assertSame(100, FundWalletService::roundRubles(100.4));
        $this->assertSame(0, FundWalletService::roundRubles(0.0));
    }

    public function testFormatRublesWithThousandsSeparator(): void
    {
        $this->assertSame('1 500', FundWalletService::formatRubles(1500.0));
        $this->assertSame('99', FundWalletService::formatRubles(99.4));
    }
}

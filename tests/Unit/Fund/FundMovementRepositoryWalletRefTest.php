<?php

namespace Zr\PaidAccess\Tests\Unit\Fund;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Fund\FundMovementRepository;

class FundMovementRepositoryWalletRefTest extends TestCase
{
    public function testBuildWalletTransactionRefUsesOrderId(): void
    {
        $this->assertSame(
            'PA-1-2026-06-08',
            FundMovementRepository::buildWalletTransactionRef(5, 'PA-1-2026-06-08', 'https://example.com/doc')
        );
    }

    public function testBuildWalletTransactionRefSkipsLongUrlExternalRef(): void
    {
        $this->assertSame(
            'FM-12',
            FundMovementRepository::buildWalletTransactionRef(12, '', 'https://docs.google.com/document/d/example')
        );
    }

    public function testBuildWalletTransactionRefUsesCompactExternalRef(): void
    {
        $this->assertSame(
            'INV-2026-42',
            FundMovementRepository::buildWalletTransactionRef(3, '', 'INV-2026-42')
        );
    }
}

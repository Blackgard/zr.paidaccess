<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\PublicUi;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\PublicUi\ModeratorPaymentListService;

final class ModeratorPaymentListServiceTest extends TestCase
{
    public function testResolveUserIdsByQueryReturnsEmptyForBlank(): void
    {
        self::assertSame([], ModeratorPaymentListService::resolveUserIdsByQuery(''));
        self::assertSame([], ModeratorPaymentListService::resolveUserIdsByQuery('   '));
    }
}

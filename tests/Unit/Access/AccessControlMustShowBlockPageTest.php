<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Access;

use Bitrix\Main\Config\Option;
use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Access\AccessControl;
use Zr\PaidAccess\PaidAccessCore;

final class AccessControlMustShowBlockPageTest extends TestCase
{
    protected function setUp(): void
    {
        Option::reset();
        \CUser::reset();
    }

    public function testGuestDoesNotSeeBlockPage(): void
    {
        $this->assertFalse(AccessControl::mustShowBlockPage(0));
    }

    public function testAdminUserDoesNotSeeBlockPage(): void
    {
        \CUser::$groupsByUser[10] = [AccessControl::ADMIN_GROUP_ID, 2];

        $this->assertFalse(AccessControl::mustShowBlockPage(10));
    }

    public function testUserOutsideRestrictedGroupsDoesNotSeeBlockPage(): void
    {
        Option::set(
            PaidAccessCore::MODULE_ID,
            PaidAccessCore::OPTION_ACCESS_RESTRICTED_GROUPS . '_s1',
            '5,6',
            's1'
        );
        \CUser::$groupsByUser[11] = [3];

        $this->assertFalse(AccessControl::mustShowBlockPage(11));
    }

    public function testEmptyRestrictedGroupsConfigDoesNotBlockAnyone(): void
    {
        Option::set(
            PaidAccessCore::MODULE_ID,
            PaidAccessCore::OPTION_ACCESS_RESTRICTED_GROUPS . '_s1',
            '',
            's1'
        );
        \CUser::$groupsByUser[12] = [2];

        $this->assertFalse(AccessControl::mustShowBlockPage(12));
    }
}

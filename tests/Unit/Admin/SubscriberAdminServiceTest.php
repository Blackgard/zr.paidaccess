<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Admin\SubscriberAdminService;
use Zr\PaidAccess\Enum\SubscriptionStatus;

final class SubscriberAdminServiceTest extends TestCase
{
    public function testFormatUserNamePrefersFullName(): void
    {
        $name = SubscriberAdminService::formatUserName([
            'NAME' => 'Иван',
            'LAST_NAME' => 'Петров',
            'LOGIN' => 'ivan',
        ]);

        $this->assertSame('Иван Петров', $name);
    }

    public function testFormatUserNameFallsBackToLogin(): void
    {
        $name = SubscriberAdminService::formatUserName([
            'NAME' => '',
            'LAST_NAME' => '',
            'LOGIN' => 'ivan',
        ]);

        $this->assertSame('ivan', $name);
    }

    public function testIsPeriodEndValidWithFutureDateString(): void
    {
        $future = date('Y-m-d H:i:s', strtotime('+10 days'));

        $this->assertTrue(SubscriberAdminService::isPeriodEndValid($future));
    }

    public function testIsPeriodEndValidWithPastDateString(): void
    {
        $past = date('Y-m-d H:i:s', strtotime('-10 days'));

        $this->assertFalse(SubscriberAdminService::isPeriodEndValid($past));
    }

    public function testIsSubscriptionActiveRequiresUserIdAndPaidPayment(): void
    {
        $subscription = [
            'STATUS' => SubscriptionStatus::ACTIVE,
            'PERIOD_END' => date('Y-m-d H:i:s', strtotime('+5 days')),
        ];

        $this->assertFalse(SubscriberAdminService::isSubscriptionActive($subscription));
    }

    public function testIsSubscriptionActiveRejectsExpiredPeriod(): void
    {
        $subscription = [
            'STATUS' => SubscriptionStatus::ACTIVE,
            'PERIOD_END' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ];

        $this->assertFalse(SubscriberAdminService::isSubscriptionActive($subscription));
    }
}

<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Subscription;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Type\DateTime;
use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Subscription\BillingPolicy;

final class BillingPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        Option::reset();
    }

    public function testFormatPeriodLabelForGatewayTestPeriod(): void
    {
        $this->assertSame(
            'Тест подключения шлюза',
            BillingPolicy::formatPeriodLabel('GT', 's1')
        );
    }

    public function testFormatPeriodLabelForCalendarMonth(): void
    {
        $this->assertSame(
            'май 2026',
            BillingPolicy::formatPeriodLabel('2026-05', 's1')
        );
    }

    public function testAssertValidBillingPeriodAcceptsCalendarMonth(): void
    {
        BillingPolicy::assertValidBillingPeriod('2026-05', 's1');
        $this->assertTrue(true);
    }

    public function testAssertValidBillingPeriodRejectsWrongFormatInCalendarMode(): void
    {
        $this->expectException(\RuntimeException::class);
        BillingPolicy::assertValidBillingPeriod('2026-05-15', 's1');
    }

    public function testAssertValidBillingPeriodAcceptsPersonalFormatForRegistrationMode(): void
    {
        Option::set(
            PaidAccessCore::MODULE_ID,
            PaidAccessCore::OPTION_BILLING_PERIOD_MODE . '_s1',
            PaidAccessCore::BILLING_PERIOD_MODE_REGISTRATION,
            's1'
        );

        BillingPolicy::assertValidBillingPeriod('2026-05-15', 's1');
        $this->assertTrue(true);
    }

    public function testRegistrationModeRejectsCalendarPeriodFormat(): void
    {
        Option::set(
            PaidAccessCore::MODULE_ID,
            PaidAccessCore::OPTION_BILLING_PERIOD_MODE . '_s1',
            PaidAccessCore::BILLING_PERIOD_MODE_REGISTRATION,
            's1'
        );

        $this->expectException(\RuntimeException::class);
        BillingPolicy::assertValidBillingPeriod('2026-05', 's1');
    }

    public function testNormalizeBillingPeriodInputConvertsCalendarMonthInPersonalMode(): void
    {
        $this->setFixedAnchorDay(15);
        Option::set(
            PaidAccessCore::MODULE_ID,
            PaidAccessCore::OPTION_BILLING_PERIOD_MODE . '_s1',
            PaidAccessCore::BILLING_PERIOD_MODE_ANCHOR_MONTH,
            's1'
        );

        $normalized = BillingPolicy::normalizeBillingPeriodInput('2026-06', 1, 's1');

        $this->assertSame('2026-06-15', $normalized);
    }

    public function testGetBillingPeriodFormatHintForPersonalMode(): void
    {
        Option::set(
            PaidAccessCore::MODULE_ID,
            PaidAccessCore::OPTION_BILLING_PERIOD_MODE . '_s1',
            PaidAccessCore::BILLING_PERIOD_MODE_REGISTRATION,
            's1'
        );

        $this->assertSame('YYYY-MM-DD', BillingPolicy::getBillingPeriodFormatHint('s1'));
    }

    public function testGetDueDateForPeriodWithFixedAnchorDay(): void
    {
        $this->setFixedAnchorDay(15);

        $due = BillingPolicy::getDueDateForPeriod(1, '2026-03', 's1');

        $this->assertSame('2026-03-15', $due->format('Y-m-d'));
    }

    public function testGetDueDateClampsAnchorDayForShortMonth(): void
    {
        $this->setFixedAnchorDay(31);

        $due = BillingPolicy::getDueDateForPeriod(1, '2026-02', 's1');

        $this->assertSame('2026-02-28', $due->format('Y-m-d'));
    }

    public function testCalcSubscriptionPeriodEndAcceptsBitrixDateTime(): void
    {
        $this->setFixedAnchorDay(15);
        $paidAt = new DateTime('2026-06-10 12:00:00');

        $periodEnd = BillingPolicy::calcSubscriptionPeriodEnd($paidAt, 1, 's1');

        $this->assertInstanceOf(DateTime::class, $periodEnd);
        $this->assertNotSame('', $periodEnd->format('Y-m-d'));
    }

    public function testGetPaymentDeadlineAddsGraceDays(): void
    {
        $this->setFixedAnchorDay(10);
        Option::set(
            PaidAccessCore::MODULE_ID,
            PaidAccessCore::OPTION_BILLING_GRACE_DAYS . '_s1',
            '3',
            's1'
        );

        $deadline = BillingPolicy::getPaymentDeadline(1, '2026-04', 's1');

        $this->assertSame('2026-04-13', $deadline->format('Y-m-d'));
        $this->assertSame('23:59:59', $deadline->format('H:i:s'));
    }

    public function testGetPreviousBillingPeriodForCalendarMonth(): void
    {
        $this->assertSame('2026-02', BillingPolicy::getPreviousBillingPeriod('2026-03', 0, 's1'));
    }

    public function testCompareBillingPeriodsUsesChronologicalOrder(): void
    {
        $this->assertLessThan(0, BillingPolicy::compareBillingPeriods('2026-02', '2026-03'));
        $this->assertSame(0, BillingPolicy::compareBillingPeriods('2026-03', '2026-03'));
    }

    private function setFixedAnchorDay(int $day): void
    {
        Option::set(
            PaidAccessCore::MODULE_ID,
            PaidAccessCore::OPTION_BILLING_ANCHOR_SOURCE . '_s1',
            PaidAccessCore::BILLING_ANCHOR_SOURCE_FIXED,
            's1'
        );
        Option::set(
            PaidAccessCore::MODULE_ID,
            PaidAccessCore::OPTION_BILLING_FIXED_DAY . '_s1',
            (string)$day,
            's1'
        );
    }
}

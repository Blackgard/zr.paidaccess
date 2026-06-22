<?php

namespace Zr\PaidAccess\Subscription;

use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Payment\PaymentRepository;

/**
 * Накопленная задолженность: все пропущенные периоды подряд до текущего включительно.
 */
class BillingArrearsService
{
    /**
     * Периоды, которые должен покрыть следующий платёж (от старых к новым).
     *
     * @return string[]
     */
    public static function getUnpaidBillingPeriods(int $userId, ?string $siteId = null): array
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        $current = BillingPolicy::getCurrentBillingPeriod($userId, $siteId);
        if ($userId <= 0 || $current === '') {
            return $current !== '' ? [$current] : [];
        }

        if (!PaidAccessCore::isBillingCollectArrears($siteId)) {
            return [$current];
        }

        $firstObligation = BillingPolicy::getFirstObligationPeriod($userId, $siteId);
        $unpaid = [];
        $period = $current;
        $guard = 0;

        while ($guard < 240) {
            $guard++;

            if (PaymentRepository::hasPaidInPeriod($userId, $period)) {
                break;
            }

            array_unshift($unpaid, $period);

            if ($period === $firstObligation
                || BillingPolicy::compareBillingPeriods($period, $firstObligation) <= 0) {
                break;
            }

            $period = BillingPolicy::getPreviousBillingPeriod($period, $userId, $siteId);
        }

        if ($unpaid === []) {
            return [$current];
        }

        return $unpaid;
    }

    /**
     * @param string[] $periods
     */
    public static function formatPeriodsRangeLabel(array $periods, ?string $siteId = null): string
    {
        if ($periods === []) {
            return '';
        }

        if (count($periods) === 1) {
            return BillingPolicy::formatPeriodLabel($periods[0], $siteId);
        }

        return BillingPolicy::formatPeriodLabel($periods[0], $siteId)
            . ' — '
            . BillingPolicy::formatPeriodLabel($periods[count($periods) - 1], $siteId);
    }
}

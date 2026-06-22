<?php

namespace Zr\PaidAccess\PublicUi;

use Bitrix\Main\Type\DateTime;
use Zr\PaidAccess\Access\SubscriberAccessService;
use Zr\PaidAccess\Enum\PaymentStatus;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Payment\SubscriptionPaymentService;
use Zr\PaidAccess\Subscription\BillingPolicy;
use Zr\PaidAccess\Subscription\SubscriptionPaymentQuote;
use Zr\PaidAccess\Tables\SubscriptionTable;

class PersonalSubscriptionViewService
{
    /**
     * @return array<string, mixed>
     */
    public static function buildViewModel(int $userId, ?string $siteId = null): array
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        $userId = (int)$userId;

        $subscription = SubscriptionTable::getList([
            'filter' => ['=USER_ID' => $userId],
            'limit' => 1,
        ])->fetch() ?: null;

        $billingPeriod = SubscriptionPaymentService::getCurrentBillingPeriod($userId, $siteId);
        $payments = SubscriberAccessService::loadCurrentPeriodPaymentsByUserIds([$userId]);
        $currentPayment = $payments[$userId] ?? null;

        $accessStatus = SubscriberAccessService::resolveAccessStatus($userId, $subscription, $currentPayment);
        $periodEnd = is_array($subscription) ? ($subscription['PERIOD_END'] ?? null) : null;

        $modulePaymentId = 0;
        $showPaymentBlock = self::shouldShowPaymentBlock($accessStatus, $currentPayment);

        if ($showPaymentBlock) {
            if (is_array($currentPayment) && (string)$currentPayment['STATUS'] === PaymentStatus::PENDING) {
                $modulePaymentId = (int)$currentPayment['ID'];
            }
        }

        $dueDate = BillingPolicy::getDueDateForPeriod($userId, $billingPeriod, $siteId);
        $quote = SubscriptionPaymentQuote::forUser($userId, $siteId);
        $breakdown = $quote->totalBreakdown;
        $monthlyBreakdown = $quote->monthlyBreakdown;

        return [
            'USER_ID' => $userId,
            'ACCESS_STATUS' => $accessStatus,
            'ACCESS_LABEL' => AccessStatusPresenter::getPublicLabel($accessStatus),
            'ACCESS_BADGE_CLASS' => AccessStatusPresenter::getBadgeCssClass($accessStatus),
            'BILLING_PERIOD' => $billingPeriod,
            'BILLING_PERIOD_LABEL' => BillingPolicy::formatPeriodLabel($billingPeriod, $siteId),
            'COVERED_PERIODS' => $quote->coveredPeriods,
            'COVERED_PERIOD_LABELS' => $quote->coveredPeriodLabels,
            'COVERED_PERIODS_RANGE_LABEL' => $quote->periodsRangeLabel,
            'PERIOD_COUNT' => $quote->periodCount,
            'IS_ARREARS_PAYMENT' => $quote->isArrearsPayment,
            'PERIOD_END' => self::formatDisplayDate($periodEnd),
            'PERIOD_END_RAW' => $periodEnd,
            'DAYS_LEFT' => self::daysUntil($periodEnd),
            'DUE_DATE' => $dueDate->format('d.m.Y'),
            'DAYS_UNTIL_DUE' => self::daysUntilDate($dueDate),
            'AMOUNT' => $breakdown->fundAmount,
            'AMOUNT_FORMATTED' => number_format($breakdown->fundAmount, 0, '.', ' '),
            'CHARGE_TOTAL' => $breakdown->chargeTotal,
            'CHARGE_TOTAL_FORMATTED' => number_format($breakdown->chargeTotal, 0, '.', ' '),
            'MONTHLY_CHARGE_TOTAL' => $monthlyBreakdown->chargeTotal,
            'MONTHLY_CHARGE_TOTAL_FORMATTED' => number_format($monthlyBreakdown->chargeTotal, 0, '.', ' '),
            'TAX_AMOUNT' => $breakdown->taxAmount,
            'TAX_AMOUNT_FORMATTED' => number_format($breakdown->taxAmount, 0, '.', ' '),
            'MAINTENANCE_AMOUNT' => $breakdown->maintenanceAmount,
            'MAINTENANCE_AMOUNT_FORMATTED' => number_format($breakdown->maintenanceAmount, 0, '.', ' '),
            'SHOW_AMOUNT_BREAKDOWN' => $quote->showComponentBreakdown(),
            'SHOW_PAYMENT_BLOCK' => $showPaymentBlock,
            'MODULE_PAYMENT_ID' => $modulePaymentId,
            'CAN_INIT_PAYMENT' => BillingPolicy::canInitPayment($userId, $siteId) || $modulePaymentId > 0,
            'IS_ACTIVE' => $accessStatus === SubscriberAccessService::ACCESS_ACTIVE,
        ];
    }

    /**
     * @param array<string, mixed>|null $currentPayment
     */
    protected static function shouldShowPaymentBlock(string $accessStatus, ?array $currentPayment): bool
    {
        if ($accessStatus === SubscriberAccessService::ACCESS_ACTIVE) {
            return false;
        }

        if ($accessStatus === SubscriberAccessService::ACCESS_EXEMPT || $accessStatus === SubscriberAccessService::ACCESS_ADMIN) {
            return false;
        }

        if (is_array($currentPayment) && PaymentStatus::isPaidLike((string)$currentPayment['STATUS'])) {
            return false;
        }

        return true;
    }

    /**
     * @param \Bitrix\Main\Type\DateTime|\DateTimeInterface|string|null $value
     */
    protected static function daysUntil($value): ?int
    {
        $ts = self::resolveTimestamp($value);
        if ($ts === null) {
            return null;
        }

        $today = strtotime(date('Y-m-d'));
        $endDay = strtotime(date('Y-m-d', $ts));

        return (int)round(($endDay - $today) / 86400);
    }

    /**
     * @param \DateTimeInterface|null $value
     */
    protected static function daysUntilDate(?\DateTimeInterface $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return self::daysUntil($value);
    }

    /**
     * @param \Bitrix\Main\Type\DateTime|\DateTimeInterface|string|null $value
     */
    protected static function resolveTimestamp($value): ?int
    {
        if ($value instanceof DateTime) {
            return $value->getTimestamp();
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }
        if (is_string($value) && $value !== '') {
            $ts = strtotime($value);

            return $ts !== false ? $ts : null;
        }

        return null;
    }

    public static function preparePaymentForUser(int $userId, ?string $siteId = null): int
    {
        return SubscriptionPaymentService::preparePayment($userId, $siteId);
    }

    /**
     * @param \Bitrix\Main\Type\DateTime|\DateTimeInterface|string|null $value
     */
    protected static function formatDisplayDate($value): string
    {
        if (empty($value)) {
            return '';
        }

        if ($value instanceof DateTime) {
            return $value->format('d.m.Y');
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d.m.Y');
        }

        $ts = strtotime((string)$value);

        return $ts !== false ? date('d.m.Y', $ts) : (string)$value;
    }
}

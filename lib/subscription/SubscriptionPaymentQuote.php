<?php

namespace Zr\PaidAccess\Subscription;

use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Payment\PaymentCoveredPeriods;
use Zr\PaidAccess\Payment\PaymentRepository;

/**
 * Сумма и детализация платежа (в т.ч. за несколько пропущенных периодов).
 */
final class SubscriptionPaymentQuote
{
    /** @var string[] */
    public $coveredPeriods;

    /** @var string[] */
    public $coveredPeriodLabels;

    /** @var int */
    public $periodCount;

    /** @var SubscriptionAmountBreakdown */
    public $monthlyBreakdown;

    /** @var SubscriptionAmountBreakdown */
    public $totalBreakdown;

    /** @var bool */
    public $isArrearsPayment;

    /** @var string */
    public $periodsRangeLabel;

    /**
     * @param string[] $coveredPeriods
     */
    public function __construct(
        array $coveredPeriods,
        SubscriptionAmountBreakdown $monthlyBreakdown,
        SubscriptionAmountBreakdown $totalBreakdown,
        ?string $siteId = null
    ) {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        $this->coveredPeriods = $coveredPeriods;
        $this->periodCount = max(1, count($coveredPeriods));
        $this->monthlyBreakdown = $monthlyBreakdown;
        $this->totalBreakdown = $totalBreakdown;
        $this->isArrearsPayment = $this->periodCount > 1;
        $this->coveredPeriodLabels = array_map(
            static function (string $period) use ($siteId): string {
                return BillingPolicy::formatPeriodLabel($period, $siteId);
            },
            $coveredPeriods
        );
        $this->periodsRangeLabel = BillingArrearsService::formatPeriodsRangeLabel($coveredPeriods, $siteId);
    }

    public static function forUser(int $userId, ?string $siteId = null): self
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        $periods = BillingArrearsService::getUnpaidBillingPeriods($userId, $siteId);
        $monthly = SubscriptionAmountBreakdown::fromSite($siteId);

        return new self($periods, $monthly, $monthly->multiply(count($periods)), $siteId);
    }

    /**
     * @param array<string, mixed> $payment
     */
    public static function fromPaymentRow(array $payment, ?string $siteId = null): self
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        $periods = PaymentCoveredPeriods::fromPaymentRow($payment);
        if ($periods === []) {
            $billingPeriod = trim((string)($payment['BILLING_PERIOD'] ?? ''));
            if ($billingPeriod !== '') {
                $periods = [$billingPeriod];
            }
        }

        $total = SubscriptionAmountBreakdown::fromPaymentRow($payment);
        $count = max(1, count($periods));
        $monthly = $total->divide($count);

        return new self($periods, $monthly, $total, $siteId);
    }

    public static function fromPaymentId(int $paymentId, ?string $siteId = null): ?self
    {
        $payment = PaymentRepository::getById($paymentId);
        if ($payment === null) {
            return null;
        }

        return self::fromPaymentRow($payment, $siteId);
    }

    public function showComponentBreakdown(): bool
    {
        return $this->monthlyBreakdown->chargeTotal > $this->monthlyBreakdown->fundAmount
            || $this->isArrearsPayment;
    }
}

<?php

namespace Zr\PaidAccess\Subscription;

use Zr\PaidAccess\PaidAccessCore;

/**
 * Разбивка суммы подписки: налог + ФОТ + фондовый взнос = итого к оплате.
 */
final class SubscriptionAmountBreakdown
{
    /** @var float */
    public $fundAmount;

    /** @var float */
    public $taxAmount;

    /** @var float */
    public $maintenanceAmount;

    /** @var float */
    public $chargeTotal;

    public function __construct(float $fundAmount, float $taxAmount, float $maintenanceAmount)
    {
        $this->fundAmount = max(0.0, $fundAmount);
        $this->taxAmount = max(0.0, $taxAmount);
        $this->maintenanceAmount = max(0.0, $maintenanceAmount);
        $this->chargeTotal = $this->fundAmount + $this->taxAmount + $this->maintenanceAmount;
    }

    public static function fromSite(?string $siteId = null): self
    {
        return new self(
            PaidAccessCore::getSubscriptionFundAmount($siteId),
            PaidAccessCore::getSubscriptionTaxAmount($siteId),
            PaidAccessCore::getSubscriptionMaintenanceAmount($siteId)
        );
    }

    /**
     * @param array<string, mixed> $payment
     */
    public static function fromPaymentRow(array $payment): self
    {
        $fund = (float)($payment['FUND_AMOUNT'] ?? 0);
        $tax = (float)($payment['TAX_AMOUNT'] ?? 0);
        $maintenance = (float)($payment['MAINTENANCE_AMOUNT'] ?? 0);

        if ($fund > 0 || $tax > 0 || $maintenance > 0) {
            return new self($fund, $tax, $maintenance);
        }

        return new self((float)($payment['AMOUNT'] ?? 0), 0.0, 0.0);
    }

    /**
     * @param array<string, mixed> $payment
     */
    public static function resolveFundAmountFromPayment(array $payment): float
    {
        return self::fromPaymentRow($payment)->fundAmount;
    }

    /**
     * @return array<string, float>
     */
    public function toPaymentAmountFields(): array
    {
        return [
            'AMOUNT' => $this->chargeTotal,
            'FUND_AMOUNT' => $this->fundAmount,
            'TAX_AMOUNT' => $this->taxAmount,
            'MAINTENANCE_AMOUNT' => $this->maintenanceAmount,
        ];
    }

    /**
     * Если админ указал полную сумму к оплате — snapshot из настроек; иначе всё в фонд.
     */
    public static function forManualPaymentAmount(float $amount, ?string $siteId = null): self
    {
        if ($amount <= 0) {
            return new self(0.0, 0.0, 0.0);
        }

        $configured = self::fromSite($siteId);
        if (abs($amount - $configured->chargeTotal) < 0.01) {
            return $configured;
        }

        return new self($amount, 0.0, 0.0);
    }
}

<?php

namespace Zr\PaidAccess\Payment;

/**
 * Список расчётных периодов, покрываемых одним платежом (JSON в COVERED_PERIODS).
 */
final class PaymentCoveredPeriods
{
    /**
     * @param string[] $periods
     */
    public static function encode(array $periods): string
    {
        $periods = array_values(array_unique(array_filter(array_map('strval', $periods))));

        return json_encode($periods, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return string[]
     */
    public static function decode(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        $periods = [];
        foreach ($decoded as $period) {
            $period = trim((string)$period);
            if ($period !== '') {
                $periods[] = $period;
            }
        }

        return array_values(array_unique($periods));
    }

    /**
     * @param array<string, mixed> $payment
     * @return string[]
     */
    public static function fromPaymentRow(array $payment): array
    {
        $periods = self::decode((string)($payment['COVERED_PERIODS'] ?? ''));
        if ($periods !== []) {
            return $periods;
        }

        $billingPeriod = trim((string)($payment['BILLING_PERIOD'] ?? ''));
        if ($billingPeriod !== '') {
            return [$billingPeriod];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $payment
     */
    public static function paymentCoversPeriod(array $payment, string $billingPeriod): bool
    {
        $billingPeriod = trim($billingPeriod);
        if ($billingPeriod === '') {
            return false;
        }

        return in_array($billingPeriod, self::fromPaymentRow($payment), true);
    }
}

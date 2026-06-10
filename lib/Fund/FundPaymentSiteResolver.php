<?php

namespace Zr\PaidAccess\Fund;

use Zr\PaidAccess\Gateway\GatewayRepository;
use Zr\PaidAccess\PaidAccessCore;

/**
 * Определяет SITE_ID для привязки платежа к фонду.
 */
class FundPaymentSiteResolver
{
    /**
     * @param array<string, mixed> $payment
     */
    public static function resolveForPayment(array $payment, ?string $fallbackSiteId = null): string
    {
        $gatewayId = (int)($payment['GATEWAY_ID'] ?? 0);
        if ($gatewayId > 0) {
            $gateway = GatewayRepository::getById($gatewayId);
            $gatewaySiteId = trim((string)($gateway['SITE_ID'] ?? ''));
            if ($gatewaySiteId !== '') {
                return PaidAccessCore::normalizeSiteId($gatewaySiteId);
            }
        }

        return PaidAccessCore::normalizeSiteId($fallbackSiteId);
    }
}

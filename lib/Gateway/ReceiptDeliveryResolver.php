<?php

namespace Zr\PaidAccess\Gateway;

use Zr\PaidAccess\Gateway\Contract\GatewayReceiptCapableInterface;
use Zr\PaidAccess\Gateway\Dto\ReceiptDeliveryInfo;
use Zr\PaidAccess\Gateway\Provider\GatewayProviderRegistry;

class ReceiptDeliveryResolver
{
    /**
     * @param array<string, mixed> $gatewayOptions
     */
    public static function resolve(string $providerCode, array $gatewayOptions, ?string $customerEmail = null): ReceiptDeliveryInfo
    {
        $provider = GatewayProviderRegistry::getProvider($providerCode);
        if ($provider instanceof GatewayReceiptCapableInterface) {
            return $provider->getReceiptDeliveryInfo($gatewayOptions, $customerEmail);
        }

        return ReceiptDeliveryInfo::none($providerCode);
    }
}

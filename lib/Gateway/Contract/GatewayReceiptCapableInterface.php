<?php

namespace Zr\PaidAccess\Gateway\Contract;

use Zr\PaidAccess\Gateway\Dto\ReceiptDeliveryInfo;

/**
 * Провайдер умеет описать политику фискального чека (ОФД / email банка).
 *
 * Реализуют Tinkoff и другие шлюзы с онлайн-кассой. Модуль сам не выбивает фискальные чеки.
 */
interface GatewayReceiptCapableInterface
{
    /**
     * @param array<string, mixed> $gatewayOptions JSON OPTIONS из zr_paidaccess_gateway
     */
    public function getReceiptDeliveryInfo(array $gatewayOptions, ?string $customerEmail = null): ReceiptDeliveryInfo;
}

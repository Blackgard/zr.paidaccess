<?php

namespace Zr\PaidAccess\Gateway\Contract;

interface GatewayCancellableInterface
{
    /**
     * @return array<string, mixed>
     */
    public function cancelPayment(string $gatewayPaymentId, int $modulePaymentId, ?float $amountRub = null): array;
}

<?php

namespace Zr\PaidAccess\Gateway\Contract;

use Zr\PaidAccess\Gateway\Dto\InitPaymentResult;

/**
 * Шлюз умеет определить, что сессия оплаты в банке уже недоступна и нужен новый Init.
 */
interface StaleSessionRecoverableGatewayInterface
{
    public function isStalePaymentSessionFailure(InitPaymentResult $result): bool;

    /**
     * @param array<string, mixed> $gatewayResponse
     */
    public function isIgnorableCancelFailure(array $gatewayResponse, string $internalPaymentStatus): bool;
}

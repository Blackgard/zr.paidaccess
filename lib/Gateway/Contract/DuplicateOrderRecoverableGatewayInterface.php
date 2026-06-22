<?php

namespace Zr\PaidAccess\Gateway\Contract;

use Zr\PaidAccess\Gateway\Dto\InitPaymentRequest;
use Zr\PaidAccess\Gateway\Dto\InitPaymentResult;

interface DuplicateOrderRecoverableGatewayInterface
{
    public function isDuplicateOrderError(InitPaymentResult $result): bool;

    public function recoverDuplicateOrder(InitPaymentRequest $request): InitPaymentResult;
}

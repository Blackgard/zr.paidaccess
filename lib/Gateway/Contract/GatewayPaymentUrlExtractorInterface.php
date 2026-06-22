<?php

namespace Zr\PaidAccess\Gateway\Contract;

interface GatewayPaymentUrlExtractorInterface
{
    /**
     * @param mixed $payload
     */
    public function extractPaymentUrl($payload): string;
}

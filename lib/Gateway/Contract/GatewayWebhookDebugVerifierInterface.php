<?php

namespace Zr\PaidAccess\Gateway\Contract;

interface GatewayWebhookDebugVerifierInterface
{
    /**
     * @param array<string, mixed> $payload
     * @return array{
     *     configuredTerminalKey: string,
     *     payloadTerminalKey: string,
     *     tokenFields: string[],
     *     expectedToken: string,
     *     receivedToken: string
     * }
     */
    public function getWebhookDebugInfo(array $payload): array;
}

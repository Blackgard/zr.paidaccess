<?php

namespace Zr\PaidAccess\Gateway\Contract;

/**
 * Провайдер платёжного шлюза: метаданные для админки + фабрика PaymentGateway.
 *
 * Реализация: lib/Gateway/Providers/{Name}/{Name}Provider.php
 * Подключается автоматически через GatewayProviderLoader.
 */
interface GatewayProviderInterface
{
    public function getCode(): string;

    public function getTitle(): string;

    /**
     * Схема полей OPTIONS в админке.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAdminFields();

    /**
     * @return array<string, mixed>
     */
    public function getDefaultOptions();

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function normalizeOptions(array $options, bool $isTest): array;

    /**
     * @param array<string, mixed> $gatewayRow строка zr_paidaccess_gateway
     */
    public function createGateway(array $gatewayRow): PaymentGatewayInterface;

    /**
     * @param array<string, mixed> $options
     * @return string[] сообщения об ошибках
     */
    public function validateOptions(array $options);

    public function getWebhookOkContentType(): string;

    public function getWebhookOkBody(): string;
}

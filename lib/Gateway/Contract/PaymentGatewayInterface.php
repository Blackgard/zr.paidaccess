<?php

namespace Zr\PaidAccess\Gateway\Contract;

use Zr\PaidAccess\Gateway\Dto\InitPaymentRequest;
use Zr\PaidAccess\Gateway\Dto\InitPaymentResult;
use Zr\PaidAccess\Gateway\Dto\WebhookHandleResult;

/**
 * Контракт платёжного шлюза модуля zr.paidaccess.
 *
 * Новый банк = новый провайдер в GatewayProviderRegistry и запись в zr_paidaccess_gateway.
 */
interface PaymentGatewayInterface
{
    public function getCode(): string;

    /**
     * Создание платежа в банке (Init / аналог).
     */
    public function initPayment(InitPaymentRequest $request): InitPaymentResult;

    /**
     * QR СБП или другой способ отображения (GetQr / аналог).
     * $gatewayPaymentId — ID платежа в системе банка.
     */
    public function fetchPaymentForm(string $gatewayPaymentId, InitPaymentRequest $request): InitPaymentResult;

    /**
     * Разбор и проверка webhook от банка.
     *
     * Должен заполнить WebhookHandleResult::internalStatus нормализованным статусом модуля.
     *
     * @param array<string, mixed> $payload сырой POST (JSON decoded)
     */
    public function handleWebhook(array $payload): WebhookHandleResult;
}

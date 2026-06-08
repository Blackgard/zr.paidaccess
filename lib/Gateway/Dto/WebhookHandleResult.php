<?php

namespace Zr\PaidAccess\Gateway\Dto;

/**
 * Результат обработки webhook.
 *
 * internalStatus — нормализованный статус модуля (PaymentStatus::*),
 * задаётся реализацией шлюза при разборе callback.
 */
class WebhookHandleResult
{
    /** @var bool */
    public $valid;

    /** @var bool */
    public $paid;

    /** @var string */
    public $orderId;

    /** @var string */
    public $gatewayPaymentId;

    /** @var string Сырой статус банка */
    public $gatewayStatus;

    /** @var string Нормализованный статус модуля (PaymentStatus::*) */
    public $internalStatus;

    /** @var string */
    public $errorMessage;

    public function __construct(
        $valid,
        $paid,
        $orderId = '',
        $gatewayPaymentId = '',
        $gatewayStatus = '',
        $errorMessage = '',
        $internalStatus = ''
    ) {
        $this->valid = (bool)$valid;
        $this->paid = (bool)$paid;
        $this->orderId = (string)$orderId;
        $this->gatewayPaymentId = (string)$gatewayPaymentId;
        $this->gatewayStatus = (string)$gatewayStatus;
        $this->errorMessage = (string)$errorMessage;
        $this->internalStatus = (string)$internalStatus;
    }
}

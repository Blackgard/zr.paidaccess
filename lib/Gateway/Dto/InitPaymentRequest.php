<?php

namespace Zr\PaidAccess\Gateway\Dto;

/**
 * Запрос на создание платежа в шлюзе.
 */
class InitPaymentRequest
{
    /** @var string */
    public $orderId;

    /** @var float */
    public $amount;

    /** @var string */
    public $currency;

    /** @var string */
    public $description;

    /** @var int */
    public $userId;

    /** @var string|null */
    public $email;

    /** @var string|null */
    public $phone;

    /** @var string Сохранённый PaymentURL (если Init уже выполнялся) */
    public $paymentUrl = '';

    public function __construct(
        $orderId,
        $amount,
        $currency,
        $description,
        $userId,
        $email = null,
        $phone = null
    ) {
        $this->orderId = (string)$orderId;
        $this->amount = (float)$amount;
        $this->currency = (string)$currency;
        $this->description = (string)$description;
        $this->userId = (int)$userId;
        $this->email = $email !== null ? (string)$email : null;
        $this->phone = $phone !== null ? (string)$phone : null;
    }

    public function getAmountKopecks()
    {
        return (int)round($this->amount * 100);
    }
}

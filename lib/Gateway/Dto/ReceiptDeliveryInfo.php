<?php

namespace Zr\PaidAccess\Gateway\Dto;

/**
 * Кто и как доставляет чек/квитанцию покупателю после оплаты.
 */
class ReceiptDeliveryInfo
{
    public const ISSUER_NONE = 'none';
    public const ISSUER_GATEWAY = 'gateway';

    /** @var string */
    public $providerCode;

    /** Фискальный чек (54-ФЗ) включён в настройках шлюза */
    public $fiscalReceiptEnabled;

    /** none | gateway — кто регистрирует чек в ОФД */
    public $fiscalReceiptIssuer;

    /** Шлюз отправит фискальный чек на email покупателя (при Init передан Receipt.Email) */
    public $fiscalReceiptEmailByGateway;

    /** @var string Подсказка для админки */
    public $adminHint;

    public function __construct(
        string $providerCode,
        bool $fiscalReceiptEnabled,
        string $fiscalReceiptIssuer,
        bool $fiscalReceiptEmailByGateway,
        string $adminHint = ''
    ) {
        $this->providerCode = $providerCode;
        $this->fiscalReceiptEnabled = $fiscalReceiptEnabled;
        $this->fiscalReceiptIssuer = $fiscalReceiptIssuer;
        $this->fiscalReceiptEmailByGateway = $fiscalReceiptEmailByGateway;
        $this->adminHint = $adminHint;
    }

    public static function none(string $providerCode = ''): self
    {
        return new self($providerCode, false, self::ISSUER_NONE, false);
    }
}

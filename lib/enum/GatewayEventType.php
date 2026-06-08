<?php

namespace Zr\PaidAccess\Enum;

/**
 * Тип обращения к платёжному шлюзу (лог транзакций).
 */
final class GatewayEventType
{
    public const INIT = 'init';
    public const GET_QR = 'get_qr';
    public const WEBHOOK = 'webhook';
    public const STATUS_CHECK = 'status_check';
    public const REFUND = 'refund';
    public const CANCEL = 'cancel';
    public const ADMIN_MANUAL = 'admin_manual';
    public const RECEIPT_EMAIL = 'receipt_email';
    public const RECEIPT_FISCAL_GATEWAY = 'receipt_fiscal_gateway';

    public const ALL = [
        self::INIT,
        self::GET_QR,
        self::WEBHOOK,
        self::STATUS_CHECK,
        self::REFUND,
        self::CANCEL,
        self::ADMIN_MANUAL,
        self::RECEIPT_EMAIL,
        self::RECEIPT_FISCAL_GATEWAY,
    ];
}

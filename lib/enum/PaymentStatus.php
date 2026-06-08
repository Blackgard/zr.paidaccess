<?php

namespace Zr\PaidAccess\Enum;

/**
 * Статус платежа в модуле (внутренняя модель).
 */
final class PaymentStatus
{
    public const PENDING = 'pending';
    public const PAID = 'paid';
    public const AUTHORIZED = 'authorized';
    public const REFUNDED = 'refunded';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';

    public const ALL = [
        self::PENDING,
        self::PAID,
        self::AUTHORIZED,
        self::REFUNDED,
        self::FAILED,
        self::CANCELLED,
    ];

    public static function isValid(string $status): bool
    {
        return in_array($status, self::ALL, true);
    }

    /**
     * Статусы, при которых подписка считается оплаченной за период (F6).
     */
    public static function isPaidLike(string $status): bool
    {
        return in_array($status, [self::PAID, self::AUTHORIZED], true);
    }

    /**
     * Статус даёт доступ на сайте (только полная оплата).
     */
    public static function grantsAccess(string $status): bool
    {
        return $status === self::PAID;
    }
}

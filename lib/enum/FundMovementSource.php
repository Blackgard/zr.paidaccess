<?php

namespace Zr\PaidAccess\Enum;

final class FundMovementSource
{
    public const PAYMENT = 'payment';
    public const REFUND = 'refund';
    public const ADMIN = 'admin';
    public const SYSTEM = 'system';

    public const ALL = [
        self::PAYMENT,
        self::REFUND,
        self::ADMIN,
        self::SYSTEM,
    ];

    public static function isValid(string $source): bool
    {
        return in_array($source, self::ALL, true);
    }
}

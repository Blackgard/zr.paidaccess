<?php

namespace Zr\PaidAccess\Enum;

final class FundMovementType
{
    public const INCOME = 'income';
    public const EXPENSE = 'expense';

    public const ALL = [
        self::INCOME,
        self::EXPENSE,
    ];

    public static function isValid(string $type): bool
    {
        return in_array($type, self::ALL, true);
    }
}

<?php

namespace Zr\PaidAccess\Enum;

/**
 * Режим распределения суммы списания с фонда между участниками.
 */
final class FundExpenseAllocationMode
{
    public const EVEN = 'even';
    public const RANDOM = 'random';

    public const ALL = [
        self::EVEN,
        self::RANDOM,
    ];

    public static function isValid(string $mode): bool
    {
        return in_array($mode, self::ALL, true);
    }
}

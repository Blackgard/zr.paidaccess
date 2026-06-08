<?php

namespace Zr\PaidAccess\Enum;

/**
 * Состояние подписки пользователя (агрегат, не платёж).
 */
final class SubscriptionStatus
{
    public const ACTIVE = 'active';
    public const DEBT = 'debt';
    public const EXPIRED = 'expired';

    public const ALL = [
        self::ACTIVE,
        self::DEBT,
        self::EXPIRED,
    ];
}

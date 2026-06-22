<?php

/**
 * @link https://alexandr-zr.ru/
 * @author Alexandr Drachenin <alexdrachenin98@gmail.com>
 * @copyright Copyright (c) 2020-2026 ZR studio (https://alexandr-zr.ru/)
 */

namespace Zr\PaidAccess\Access;

use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Payment\PaymentRepository;
use Zr\PaidAccess\Payment\SubscriptionPaymentService;
use Zr\PaidAccess\Subscription\SubscriptionService;

/**
 * Проверки доступа и подписки (без чтения опций напрямую — только через PaidAccessCore).
 */
class AccessControl
{
    /** ID группы администраторов Bitrix — никогда не блокируется */
    public const ADMIN_GROUP_ID = 1;

    /**
     * Нужно ли показать страницу блокировки для пользователя.
     */
    public static function mustShowBlockPage(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        if (self::isAdminUser($userId)) {
            return false;
        }

        if (!self::isUserInRestrictedGroups($userId)) {
            return false;
        }

        if (self::hasPaidSubscription($userId)) {
            return false;
        }

        return true;
    }

    /**
     * Активная оплаченная подписка.
     */
    public static function hasPaidSubscription(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        if (SubscriptionService::hasActiveSubscription($userId)) {
            return true;
        }

        return PaymentRepository::hasPaidInPeriod(
            $userId,
            SubscriptionPaymentService::getCurrentBillingPeriod($userId, PaidAccessCore::normalizeSiteId()),
            null,
            true
        );
    }

    public static function isAdminUser(int $userId): bool
    {
        return in_array(self::ADMIN_GROUP_ID, \CUser::GetUserGroup($userId));
    }

    public static function isUserInRestrictedGroups(int $userId): bool
    {
        $restrictedGroupIds = PaidAccessCore::getAccessRestrictedGroupIds();

        if ($restrictedGroupIds === []) {
            return false;
        }

        $userGroupIds = array_map('intval', \CUser::GetUserGroup($userId));

        return count(array_intersect($userGroupIds, $restrictedGroupIds)) > 0;
    }
}

<?php

namespace Zr\PaidAccess\Subscription;

use Bitrix\Main\Type\DateTime;
use Zr\PaidAccess\Access\AccessControl;
use Zr\PaidAccess\Enum\SubscriptionStatus;
use Zr\PaidAccess\Notification\SubscriptionNotificationService;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Tables\SubscriptionTable;

/**
 * F7: напоминание об окончании оплаченного периода подписки.
 */
class SubscriptionReminderService
{
    public static function processExpiringReminders(?string $siteId = null): int
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        if (!PaidAccessCore::isModuleActive($siteId) || !PaidAccessCore::isMailNotifySubscriptionExpiringEnabled($siteId)) {
            return 0;
        }

        $daysBefore = PaidAccessCore::getMailSubscriptionExpiringDays($siteId);
        if ($daysBefore <= 0) {
            return 0;
        }

        $groupIds = PaidAccessCore::getAccessRestrictedGroupIds($siteId);
        if ($groupIds === []) {
            return 0;
        }

        $sent = 0;
        $now = new DateTime();
        $maxDate = new DateTime();
        $maxDate->add('+' . $daysBefore . ' days');

        $subscriptions = SubscriptionTable::getList([
            'filter' => [
                '=STATUS' => SubscriptionStatus::ACTIVE,
                '>PERIOD_END' => $now,
                '<=PERIOD_END' => $maxDate,
            ],
            'select' => ['ID', 'USER_ID', 'PERIOD_END', 'STATUS'],
        ]);

        while ($row = $subscriptions->fetch()) {
            $userId = (int)$row['USER_ID'];
            if ($userId <= 0 || AccessControl::isAdminUser($userId)) {
                continue;
            }
            if (!AccessControl::isUserInRestrictedGroups($userId)) {
                continue;
            }

            SubscriptionNotificationService::onSubscriptionExpiring($userId, $row['PERIOD_END'], $siteId);
            $sent++;
        }

        return $sent;
    }
}

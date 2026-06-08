<?php

namespace Zr\PaidAccess\Subscription;

use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UserTable;
use Zr\PaidAccess\Access\AccessControl;
use Zr\PaidAccess\Enum\SubscriptionStatus;
use Zr\PaidAccess\Notification\SubscriptionNotificationService;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Tables\SubscriptionTable;

/**
 * F7: статус debt / active по billing day и оплате за текущий период.
 */
class BillingDebtService
{
    public static function syncUserDebtStatus(int $userId): void
    {
        if ($userId <= 0 || !PaidAccessCore::isModuleActive()) {
            return;
        }

        if (AccessControl::isAdminUser($userId)) {
            return;
        }

        if (!AccessControl::isUserInRestrictedGroups($userId)) {
            return;
        }

        SubscriptionService::reconcileUserSubscription($userId);

        if (AccessControl::hasPaidSubscription($userId)) {
            self::updateStatus($userId, SubscriptionStatus::ACTIVE);
            return;
        }

        if (AccessControl::mustShowBlockPage($userId)) {
            self::updateStatus($userId, SubscriptionStatus::DEBT);
            return;
        }

        $row = self::getSubscriptionRow($userId);
        if ($row && ($row['STATUS'] ?? '') === SubscriptionStatus::ACTIVE) {
            self::updateStatus($userId, SubscriptionStatus::EXPIRED);
        }
    }

    /**
     * Агент: обход пользователей из контролируемых групп.
     */
    public static function processRestrictedUsers(): int
    {
        if (!PaidAccessCore::isModuleActive()) {
            return 0;
        }

        $groupIds = PaidAccessCore::getAccessRestrictedGroupIds();
        if ($groupIds === []) {
            return 0;
        }

        $processed = 0;
        $result = UserTable::getList([
            'select' => ['ID'],
            'filter' => [
                '=ACTIVE' => 'Y',
                '@GROUPS.GROUP_ID' => $groupIds,
            ],
        ]);

        while ($user = $result->fetch()) {
            $userId = (int)$user['ID'];
            self::syncUserDebtStatus($userId);
            $processed++;
        }

        return $processed;
    }

    public static function isUserInDebt(int $userId): bool
    {
        $row = self::getSubscriptionRow($userId);

        return is_array($row) && ($row['STATUS'] ?? '') === SubscriptionStatus::DEBT;
    }

    protected static function updateStatus(int $userId, string $status): void
    {
        SubscriptionService::ensureRegisteredUser($userId);

        $row = self::getSubscriptionRow($userId);
        if (!$row || ($row['STATUS'] ?? '') === $status) {
            return;
        }

        $previousStatus = (string)($row['STATUS'] ?? '');

        SubscriptionTable::update((int)$row['ID'], [
            'STATUS' => $status,
            'DATE_UPDATE' => new DateTime(),
        ]);

        if ($status === SubscriptionStatus::DEBT && $previousStatus !== SubscriptionStatus::DEBT) {
            SubscriptionNotificationService::onSubscriptionDebt($userId);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected static function getSubscriptionRow(int $userId): ?array
    {
        $row = SubscriptionTable::getList([
            'filter' => ['=USER_ID' => $userId],
            'limit' => 1,
        ])->fetch();

        return is_array($row) ? $row : null;
    }
}

<?php

namespace Zr\PaidAccess\PublicUi;

use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UserTable;
use Zr\PaidAccess\Access\AccessControl;
use Zr\PaidAccess\Admin\SubscriberAdminService;
use Zr\PaidAccess\Enum\PaymentStatus;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Payment\SubscriptionPaymentService;
use Zr\PaidAccess\Subscription\BillingPolicy;
use Zr\PaidAccess\Tables\PaymentTable;

class MemberListService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getMembers(bool $showTotalPaid = false, ?string $siteId = null): array
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        $groupIds = PaidAccessCore::getAccessRestrictedGroupIds($siteId);
        if ($groupIds === []) {
            return [];
        }

        $filter = [
            '=ACTIVE' => 'Y',
            '@GROUPS.GROUP_ID' => $groupIds,
        ];

        $users = [];
        $userIds = [];
        $result = UserTable::getList([
            'filter' => $filter,
            'select' => ['ID', 'LOGIN', 'NAME', 'LAST_NAME', 'EMAIL'],
            'order' => ['LAST_NAME' => 'ASC', 'NAME' => 'ASC'],
        ]);

        while ($row = $result->fetch()) {
            $userId = (int)$row['ID'];
            if (AccessControl::isAdminUser($userId)) {
                continue;
            }

            $users[$userId] = $row;
            $userIds[] = $userId;
        }

        if ($userIds === []) {
            return [];
        }

        $subscriptions = SubscriberAdminService::loadSubscriptionsByUserIds($userIds);
        $payments = SubscriberAdminService::loadCurrentPeriodPaymentsByUserIds($userIds);
        $lastPaidPayments = SubscriberAdminService::loadLastPaidPaymentsByUserIds($userIds);
        $totalsPaid = $showTotalPaid ? self::loadTotalPaidByUserIds($userIds) : [];

        $items = [];
        foreach ($users as $userId => $user) {
            $subscription = $subscriptions[$userId] ?? null;
            $payment = $payments[$userId] ?? null;
            $lastPaidPayment = $lastPaidPayments[$userId] ?? null;
            $accessStatus = SubscriberAdminService::resolveAccessStatus($userId, $subscription, $payment);
            $billingPeriod = SubscriptionPaymentService::getCurrentBillingPeriod($userId, $siteId);

            $items[] = [
                'USER_ID' => $userId,
                'NAME' => SubscriberAdminService::formatUserName($user),
                'ACCESS_STATUS' => $accessStatus,
                'ACCESS_LABEL' => AccessStatusPresenter::getPublicLabel($accessStatus),
                'ROW_CSS_CLASS' => AccessStatusPresenter::getRowCssClass($accessStatus),
                'BADGE_CSS_CLASS' => AccessStatusPresenter::getBadgeCssClass($accessStatus),
                'SORT_PRIORITY' => AccessStatusPresenter::getSortPriority($accessStatus),
                'BILLING_PERIOD_LABEL' => BillingPolicy::formatPeriodLabel($billingPeriod, $siteId),
                'PERIOD_END' => self::formatDisplayDate(
                    SubscriberAdminService::resolveDisplayPeriodEnd(
                        $userId,
                        $subscription,
                        $payment,
                        $lastPaidPayment,
                        $siteId
                    )
                ),
                'LAST_PAID_DATE' => self::formatDisplayDate(
                    is_array($lastPaidPayment) ? ($lastPaidPayment['DATE_PAID'] ?? null) : null
                ),
                'TOTAL_PAID' => $showTotalPaid ? ($totalsPaid[$userId] ?? 0.0) : null,
                'TOTAL_PAID_FORMATTED' => $showTotalPaid
                    ? number_format((float)($totalsPaid[$userId] ?? 0), 0, '.', ' ')
                    : null,
            ];
        }

        usort($items, static function (array $a, array $b): int {
            $cmp = ($a['SORT_PRIORITY'] ?? 0) <=> ($b['SORT_PRIORITY'] ?? 0);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcasecmp((string)$a['NAME'], (string)$b['NAME']);
        });

        return $items;
    }

    /**
     * @param int[] $userIds
     * @return array<int, float>
     */
    protected static function loadTotalPaidByUserIds(array $userIds): array
    {
        $map = array_fill_keys($userIds, 0.0);
        $result = PaymentTable::getList([
            'filter' => [
                '@USER_ID' => $userIds,
                '@STATUS' => [PaymentStatus::PAID, PaymentStatus::AUTHORIZED],
            ],
            'select' => ['USER_ID', 'AMOUNT'],
        ]);

        while ($row = $result->fetch()) {
            $userId = (int)$row['USER_ID'];
            $map[$userId] = ($map[$userId] ?? 0) + (float)$row['AMOUNT'];
        }

        return $map;
    }

    /**
     * @param \Bitrix\Main\Type\DateTime|\DateTimeInterface|string|null $value
     */
    protected static function formatDisplayDate($value): string
    {
        if (empty($value)) {
            return '';
        }

        if ($value instanceof DateTime) {
            return $value->format('d.m.Y');
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d.m.Y');
        }

        $ts = strtotime((string)$value);

        return $ts !== false ? date('d.m.Y', $ts) : (string)$value;
    }
}

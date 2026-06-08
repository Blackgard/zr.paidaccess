<?php

namespace Zr\PaidAccess\Admin;

use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UserTable;
use Zr\PaidAccess\Access\AccessControl;
use Zr\PaidAccess\Enum\PaymentStatus;
use Zr\PaidAccess\Enum\SubscriptionStatus;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Payment\PaymentRepository;
use Zr\PaidAccess\Payment\SubscriptionPaymentService;
use Zr\PaidAccess\Subscription\BillingPolicy;
use Zr\PaidAccess\Tables\PaymentTable;
use Zr\PaidAccess\Tables\SubscriptionTable;

class SubscriberAdminService
{
    public const ACCESS_ACTIVE = 'active';
    public const ACCESS_PENDING = 'pending';
    public const ACCESS_UNPAID = 'unpaid';
    public const ACCESS_DEBT = 'debt';
    public const ACCESS_FAILED = 'failed';
    public const ACCESS_EXPIRED = 'expired';
    public const ACCESS_EXEMPT = 'exempt';
    public const ACCESS_ADMIN = 'admin';

    /**
     * @return array<string, string>
     */
    public static function getAccessStatusTitles()
    {
        return [
            self::ACCESS_ACTIVE => 'Доступ активен',
            self::ACCESS_PENDING => 'Ожидает оплаты',
            self::ACCESS_UNPAID => 'Не оплачено',
            self::ACCESS_DEBT => 'Долг (debt)',
            self::ACCESS_FAILED => 'Ошибка оплаты',
            self::ACCESS_EXPIRED => 'Подписка истекла',
            self::ACCESS_EXEMPT => 'Без проверки',
            self::ACCESS_ADMIN => 'Администратор',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getAccessStatusStyles()
    {
        return [
            self::ACCESS_ACTIVE => StatusBadgeRenderer::STYLE_COMPLETED,
            self::ACCESS_PENDING => StatusBadgeRenderer::STYLE_PROGRESS,
            self::ACCESS_UNPAID => StatusBadgeRenderer::STYLE_WARNING,
            self::ACCESS_DEBT => StatusBadgeRenderer::STYLE_DANGER,
            self::ACCESS_FAILED => StatusBadgeRenderer::STYLE_DANGER,
            self::ACCESS_EXPIRED => StatusBadgeRenderer::STYLE_DANGER,
            self::ACCESS_EXEMPT => StatusBadgeRenderer::STYLE_MUTED,
            self::ACCESS_ADMIN => StatusBadgeRenderer::STYLE_INFO,
        ];
    }

    /**
     * @param array<string, mixed> $filterData
     * @return array<string, mixed>
     */
    public static function buildUserFilter(array $filterData)
    {
        $filter = ['=ACTIVE' => 'Y'];

        if (!empty($filterData['USER_ID'])) {
            $filter['=ID'] = (int)$filterData['USER_ID'];
        }

        if (!empty($filterData['LOGIN'])) {
            $filter['%LOGIN'] = $filterData['LOGIN'];
        }

        if (!empty($filterData['EMAIL'])) {
            $filter['%EMAIL'] = $filterData['EMAIL'];
        }

        if (!empty($filterData['NAME'])) {
            $filter[] = [
                'LOGIC' => 'OR',
                ['%NAME' => $filterData['NAME']],
                ['%LAST_NAME' => $filterData['NAME']],
            ];
        }

        $restrictedGroupIds = PaidAccessCore::getAccessRestrictedGroupIds();
        $scope = $filterData['SCOPE'] ?? 'all';

        if ($scope === 'restricted' && $restrictedGroupIds !== []) {
            $filter['@GROUPS.GROUP_ID'] = $restrictedGroupIds;
        }

        return $filter;
    }

    /**
     * @param int[] $userIds
     * @return array<int, array<string, mixed>>
     */
    public static function loadSubscriptionsByUserIds(array $userIds)
    {
        $userIds = array_values(array_filter(array_map('intval', $userIds)));
        if ($userIds === []) {
            return [];
        }

        $map = [];
        $result = SubscriptionTable::getList([
            'filter' => ['@USER_ID' => $userIds],
        ]);
        while ($row = $result->fetch()) {
            $map[(int)$row['USER_ID']] = $row;
        }

        return $map;
    }

    /**
     * @param int[] $userIds
     * @return array<int, array<string, mixed>>
     */
    public static function loadCurrentPeriodPaymentsByUserIds(array $userIds, $billingPeriod = null)
    {
        $userIds = array_values(array_filter(array_map('intval', $userIds)));
        if ($userIds === []) {
            return [];
        }

        $map = [];

        if ($billingPeriod === null
            && PaidAccessCore::isPersonalBillingPeriodMode()
        ) {
            foreach ($userIds as $userId) {
                $period = SubscriptionPaymentService::getCurrentBillingPeriod($userId);
                $row = PaymentTable::getList([
                    'filter' => [
                        '=USER_ID' => $userId,
                        '=BILLING_PERIOD' => $period,
                    ],
                    'order' => ['ID' => 'DESC'],
                    'limit' => 1,
                ])->fetch();

                if (is_array($row)) {
                    $map[$userId] = $row;
                }
            }

            return $map;
        }

        if ($billingPeriod === null) {
            $billingPeriod = SubscriptionPaymentService::getCurrentBillingPeriod();
        }

        $result = PaymentTable::getList([
            'filter' => [
                '@USER_ID' => $userIds,
                '=BILLING_PERIOD' => $billingPeriod,
            ],
            'order' => ['ID' => 'DESC'],
        ]);

        while ($row = $result->fetch()) {
            $uid = (int)$row['USER_ID'];
            if (!isset($map[$uid])) {
                $map[$uid] = $row;
            }
        }

        return $map;
    }

    /**
     * @param array<string, mixed>|null $subscription
     * @param array<string, mixed>|null $currentPayment
     */
    public static function resolveAccessStatus($userId, $subscription, $currentPayment)
    {
        $userId = (int)$userId;

        if (AccessControl::isAdminUser($userId)) {
            return self::ACCESS_ADMIN;
        }

        if (!AccessControl::isUserInRestrictedGroups($userId)) {
            return self::ACCESS_EXEMPT;
        }

        if (self::isSubscriptionActive($subscription)) {
            return self::ACCESS_ACTIVE;
        }

        if (is_array($currentPayment)) {
            $paymentStatus = (string)$currentPayment['STATUS'];
            if (PaymentStatus::isPaidLike($paymentStatus)) {
                return self::ACCESS_ACTIVE;
            }
            if ($paymentStatus === PaymentStatus::PENDING) {
                return self::ACCESS_PENDING;
            }
            if (in_array($paymentStatus, [PaymentStatus::FAILED, PaymentStatus::CANCELLED], true)) {
                return self::ACCESS_FAILED;
            }
        }

        if (is_array($subscription) && ($subscription['STATUS'] ?? '') === SubscriptionStatus::DEBT) {
            return self::ACCESS_DEBT;
        }

        if (is_array($subscription) && ($subscription['STATUS'] ?? '') === SubscriptionStatus::EXPIRED) {
            return self::ACCESS_EXPIRED;
        }

        if (is_array($subscription) && !empty($subscription['PERIOD_END']) && !self::isPeriodEndValid($subscription['PERIOD_END'])) {
            return self::ACCESS_EXPIRED;
        }

        return self::ACCESS_UNPAID;
    }

    /**
     * @param array<string, mixed>|null $subscription
     */
    public static function isSubscriptionActive($subscription)
    {
        if (!is_array($subscription) || ($subscription['STATUS'] ?? '') !== SubscriptionStatus::ACTIVE) {
            return false;
        }

        if (!self::isPeriodEndValid($subscription['PERIOD_END'] ?? null)) {
            return false;
        }

        $userId = (int)($subscription['USER_ID'] ?? 0);

        return $userId > 0 && PaymentRepository::findLatestAccessGrantingPayment($userId) !== null;
    }

    /**
     * @param mixed $periodEnd
     */
    public static function isPeriodEndValid($periodEnd)
    {
        if (empty($periodEnd)) {
            return false;
        }

        if ($periodEnd instanceof DateTime) {
            return $periodEnd->getTimestamp() >= time();
        }

        return strtotime((string)$periodEnd) >= time();
    }

    /**
     * @param int[] $userIds
     * @return array<int, array<string, mixed>>
     */
    public static function loadLastPaidPaymentsByUserIds(array $userIds): array
    {
        $userIds = array_values(array_filter(array_map('intval', $userIds)));
        if ($userIds === []) {
            return [];
        }

        $map = [];
        $result = PaymentTable::getList([
            'filter' => [
                '@USER_ID' => $userIds,
                '@STATUS' => [PaymentStatus::PAID, PaymentStatus::AUTHORIZED],
            ],
            'select' => ['USER_ID', 'DATE_PAID', 'STATUS'],
            'order' => ['DATE_PAID' => 'DESC'],
        ]);

        while ($row = $result->fetch()) {
            $userId = (int)$row['USER_ID'];
            if (!isset($map[$userId])) {
                $map[$userId] = $row;
            }
        }

        return $map;
    }

    /**
     * Дата окончания доступа: из подписки или расчёт по дате оплаты.
     *
     * @param array<string, mixed>|null $subscription
     * @param array<string, mixed>|null $currentPayment
     * @param array<string, mixed>|null $lastPaidPayment
     * @return DateTime|\DateTimeInterface|string|null
     */
    public static function resolveDisplayPeriodEnd(
        int $userId,
        ?array $subscription,
        ?array $currentPayment,
        ?array $lastPaidPayment = null,
        ?string $siteId = null
    ) {
        if (is_array($subscription) && !empty($subscription['PERIOD_END'])) {
            return $subscription['PERIOD_END'];
        }

        $paidAt = null;
        if (is_array($currentPayment) && PaymentStatus::isPaidLike((string)$currentPayment['STATUS'])) {
            $paidAt = $currentPayment['DATE_PAID'] ?? null;
        } elseif (is_array($lastPaidPayment)) {
            $paidAt = $lastPaidPayment['DATE_PAID'] ?? null;
        }

        $paidAtDateTime = self::toDateTime($paidAt);
        if ($paidAtDateTime !== null) {
            return BillingPolicy::calcSubscriptionPeriodEnd($paidAtDateTime, $userId, $siteId);
        }

        return null;
    }

    public static function formatPeriodEnd($periodEnd)
    {
        if (empty($periodEnd)) {
            return '';
        }

        if ($periodEnd instanceof DateTime) {
            return $periodEnd->format('d.m.Y H:i:s');
        }

        if ($periodEnd instanceof \DateTimeInterface) {
            return $periodEnd->format('d.m.Y H:i:s');
        }

        $ts = strtotime((string)$periodEnd);

        return $ts !== false ? date('d.m.Y H:i:s', $ts) : (string)$periodEnd;
    }

    /**
     * @param \Bitrix\Main\Type\DateTime|\DateTimeInterface|string|null $value
     */
    protected static function toDateTime($value): ?DateTime
    {
        if ($value instanceof DateTime) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return DateTime::createFromTimestamp($value->getTimestamp());
        }

        if (is_string($value) && $value !== '') {
            try {
                return new DateTime($value);
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    public static function formatUserName(array $userRow)
    {
        $name = trim(($userRow['NAME'] ?? '') . ' ' . ($userRow['LAST_NAME'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        return (string)($userRow['LOGIN'] ?? '');
    }

    public static function getCreatePaymentUrl($userId, $languageId)
    {
        return 'zr_paidaccess_payment_edit.php?lang=' . urlencode($languageId) . '&USER_ID=' . (int)$userId;
    }
}

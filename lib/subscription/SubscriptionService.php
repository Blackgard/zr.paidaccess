<?php

namespace Zr\PaidAccess\Subscription;

use Bitrix\Main\Type\DateTime;
use Zr\PaidAccess\Enum\SubscriptionStatus;
use Zr\PaidAccess\Payment\PaymentRepository;
use Zr\PaidAccess\Subscription\BillingPolicy;
use Zr\PaidAccess\Tables\SubscriptionTable;

class SubscriptionService
{
    public static function activateFromPayment(int $userId, int $paymentId, DateTime $paidAt): void
    {
        $billingDay = BillingPolicy::resolveBillingDay($userId);
        $periodEnd = BillingPolicy::calcSubscriptionPeriodEnd($paidAt, $userId);

        $existing = SubscriptionTable::getList([
            'filter' => ['=USER_ID' => $userId],
            'limit' => 1,
        ])->fetch();

        $fields = [
            'USER_ID' => $userId,
            'STATUS' => SubscriptionStatus::ACTIVE,
            'BILLING_DAY' => $billingDay,
            'PERIOD_END' => $periodEnd,
            'LAST_PAYMENT_ID' => $paymentId,
            'DATE_UPDATE' => new DateTime(),
        ];

        if ($existing) {
            SubscriptionTable::update((int)$existing['ID'], $fields);
        } else {
            $fields['DATE_CREATE'] = new DateTime();
            SubscriptionTable::add($fields);
        }
    }

    public static function hasActiveSubscription(int $userId): bool
    {
        $row = SubscriptionTable::getList([
            'filter' => ['=USER_ID' => $userId],
            'limit' => 1,
        ])->fetch();

        if (!$row || $row['STATUS'] !== SubscriptionStatus::ACTIVE) {
            return false;
        }

        if (empty($row['PERIOD_END'])) {
            return false;
        }

        $periodEnd = $row['PERIOD_END'];
        $periodEndValid = $periodEnd instanceof DateTime
            ? $periodEnd->getTimestamp() >= time()
            : strtotime((string)$periodEnd) >= time();

        if (!$periodEndValid) {
            return false;
        }

        return PaymentRepository::findLatestAccessGrantingPayment($userId) !== null;
    }

    /**
     * Запись подписки при регистрации (статус debt до первой оплаты).
     */
    public static function ensureRegisteredUser(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $existing = SubscriptionTable::getList([
            'filter' => ['=USER_ID' => $userId],
            'limit' => 1,
        ])->fetch();

        if ($existing) {
            return;
        }

        $now = new DateTime();

        SubscriptionTable::add([
            'USER_ID' => $userId,
            'STATUS' => SubscriptionStatus::DEBT,
            'BILLING_DAY' => BillingPolicy::resolveBillingDay($userId),
            'DATE_CREATE' => $now,
            'DATE_UPDATE' => $now,
        ]);
    }

    /**
     * Сверка подписки с фактическими оплаченными платежами пользователя.
     * Вызывается при удалении/отмене платежа и агентом BillingDebtAgent.
     */
    public static function reconcileUserSubscription(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $latestPaid = PaymentRepository::findLatestAccessGrantingPayment($userId);
        if ($latestPaid !== null) {
            $paidAt = $latestPaid['DATE_PAID'] ?? null;
            if ($paidAt instanceof DateTime) {
                self::activateFromPayment($userId, (int)$latestPaid['ID'], $paidAt);
            } elseif (!empty($paidAt)) {
                self::activateFromPayment($userId, (int)$latestPaid['ID'], new DateTime($paidAt));
            }

            return;
        }

        $subscription = SubscriptionTable::getList([
            'filter' => ['=USER_ID' => $userId],
            'limit' => 1,
        ])->fetch();

        if (!$subscription || ($subscription['STATUS'] ?? '') !== SubscriptionStatus::ACTIVE) {
            return;
        }

        SubscriptionTable::update((int)$subscription['ID'], [
            'STATUS' => SubscriptionStatus::DEBT,
            'PERIOD_END' => null,
            'LAST_PAYMENT_ID' => null,
            'DATE_UPDATE' => new DateTime(),
        ]);
    }

    /**
     * После снятия статуса «Оплачен» в админке: долг или пересчёт по другому платежу.
     */
    public static function syncAfterPaymentAccessRevoked(int $paymentId, int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        self::reconcileUserSubscription($userId);
    }
}

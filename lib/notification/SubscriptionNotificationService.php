<?php

namespace Zr\PaidAccess\Notification;

use Bitrix\Main\Mail\Event;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UserTable;
use Zr\PaidAccess\Enum\NotificationType;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Payment\PaymentRepository;
use Zr\PaidAccess\Subscription\BillingPolicy;

/**
 * Письма по статусам подписки и оплаты (F7).
 */
class SubscriptionNotificationService
{
    public static function onPaymentFailed(int $paymentId, string $reason = '', ?string $siteId = null): void
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        if (!PaidAccessCore::isMailNotifyPaymentFailedEnabled($siteId)) {
            return;
        }

        $payment = PaymentRepository::getById($paymentId);
        if (!$payment) {
            return;
        }

        $userId = (int)$payment['USER_ID'];
        $billingPeriod = (string)($payment['BILLING_PERIOD'] ?? '');
        $contextKey = 'failed_' . $paymentId;

        if (NotificationLogRepository::wasSent($userId, NotificationType::PAYMENT_FAILED, $contextKey)) {
            return;
        }

        $email = self::resolveCustomerEmail($userId);
        if ($email === '') {
            return;
        }

        $fields = self::buildPaymentFields($payment, $userId, $email, $siteId);
        $fields['FAIL_REASON'] = $reason !== '' ? $reason : 'Ошибка при создании или обработке платежа';

        self::send(PaidAccessCore::MAIL_EVENT_PAYMENT_FAILED, $siteId, $fields);
        NotificationLogRepository::markSent($userId, NotificationType::PAYMENT_FAILED, $contextKey);
    }

    public static function onSubscriptionDebt(int $userId, ?string $siteId = null): void
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        if (!PaidAccessCore::isMailNotifySubscriptionDebtEnabled($siteId)) {
            return;
        }

        $billingPeriod = BillingPolicy::getCurrentBillingPeriod($userId, $siteId);
        $contextKey = 'debt_' . $billingPeriod;

        if (NotificationLogRepository::wasSent($userId, NotificationType::SUBSCRIPTION_DEBT, $contextKey)) {
            return;
        }

        $email = self::resolveCustomerEmail($userId);
        if ($email === '') {
            return;
        }

        $userFields = self::buildUserFields($userId, $email);
        $amount = number_format(PaidAccessCore::getSubscriptionAmount($siteId), 2, '.', ' ');
        $periodLabel = BillingPolicy::formatPeriodLabel($billingPeriod, $siteId);
        $dueDate = BillingPolicy::getDueDateForPeriod($userId, $billingPeriod, $siteId)->format('d.m.Y');

        self::send(PaidAccessCore::MAIL_EVENT_SUBSCRIPTION_DEBT, $siteId, array_merge($userFields, [
            'BILLING_PERIOD' => $billingPeriod,
            'BILLING_PERIOD_LABEL' => $periodLabel,
            'AMOUNT' => $amount,
            'CURRENCY' => 'RUB',
            'DUE_DATE' => $dueDate,
            'GRACE_DAYS' => (string)PaidAccessCore::getBillingGraceDays($siteId),
        ]));

        NotificationLogRepository::markSent($userId, NotificationType::SUBSCRIPTION_DEBT, $contextKey);
    }

    public static function onSubscriptionExpiring(int $userId, $periodEnd, ?string $siteId = null): void
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        if (!PaidAccessCore::isMailNotifySubscriptionExpiringEnabled($siteId)) {
            return;
        }

        $periodEndStr = self::formatDateTime($periodEnd);
        if ($periodEndStr === '') {
            return;
        }

        $contextKey = 'expiring_' . $periodEndStr;
        if (NotificationLogRepository::wasSent($userId, NotificationType::SUBSCRIPTION_EXPIRING, $contextKey)) {
            return;
        }

        $email = self::resolveCustomerEmail($userId);
        if ($email === '') {
            return;
        }

        $userFields = self::buildUserFields($userId, $email);
        $daysLeft = self::daysUntil($periodEnd);

        self::send(PaidAccessCore::MAIL_EVENT_SUBSCRIPTION_EXPIRING, $siteId, array_merge($userFields, [
            'PERIOD_END' => $periodEndStr,
            'DAYS_LEFT' => (string)max(0, $daysLeft),
            'AMOUNT' => number_format(PaidAccessCore::getSubscriptionAmount($siteId), 2, '.', ' '),
            'CURRENCY' => 'RUB',
        ]));

        NotificationLogRepository::markSent($userId, NotificationType::SUBSCRIPTION_EXPIRING, $contextKey);
    }

    /**
     * @param array<string, mixed> $payment
     * @return array<string, string>
     */
    protected static function buildPaymentFields(array $payment, int $userId, string $email, string $siteId): array
    {
        $userFields = self::buildUserFields($userId, $email);
        $billingPeriod = (string)($payment['BILLING_PERIOD'] ?? '');

        return array_merge($userFields, [
            'PAYMENT_ID' => (string)($payment['ID'] ?? ''),
            'ORDER_ID' => (string)($payment['ORDER_ID'] ?? ''),
            'AMOUNT' => number_format((float)($payment['AMOUNT'] ?? 0), 2, '.', ' '),
            'CURRENCY' => (string)($payment['CURRENCY'] ?? 'RUB'),
            'BILLING_PERIOD' => $billingPeriod,
            'BILLING_PERIOD_LABEL' => BillingPolicy::formatPeriodLabel($billingPeriod, $siteId),
            'DESCRIPTION' => (string)($payment['DESCRIPTION'] ?? ''),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected static function buildUserFields(int $userId, string $email): array
    {
        $user = UserTable::getByPrimary($userId, [
            'select' => ['ID', 'LOGIN', 'NAME', 'LAST_NAME', 'EMAIL'],
        ])->fetch();

        $userName = trim((string)(($user['NAME'] ?? '') . ' ' . ($user['LAST_NAME'] ?? '')));
        if ($userName === '') {
            $userName = (string)($user['LOGIN'] ?? 'Участник');
        }

        return [
            'EMAIL' => $email,
            'USER_ID' => (string)$userId,
            'USER_NAME' => $userName,
        ];
    }

    protected static function send(string $eventName, string $siteId, array $fields): void
    {
        Event::send([
            'EVENT_NAME' => $eventName,
            'LID' => $siteId,
            'C_FIELDS' => PaidAccessCore::enrichMailFields($fields, $siteId),
        ]);
    }

    protected static function resolveCustomerEmail(int $userId): string
    {
        $user = UserTable::getByPrimary($userId, ['select' => ['EMAIL']])->fetch();

        return trim((string)($user['EMAIL'] ?? ''));
    }

  /**
     * @param \Bitrix\Main\Type\DateTime|\DateTimeInterface|string|null $value
     */
    protected static function formatDateTime($value): string
    {
        if ($value instanceof DateTime) {
            return $value->format('d.m.Y');
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d.m.Y');
        }
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return '';
    }

    /**
     * @param \Bitrix\Main\Type\DateTime|\DateTimeInterface|string|null $value
     */
    protected static function daysUntil($value): int
    {
        $ts = null;
        if ($value instanceof DateTime) {
            $ts = $value->getTimestamp();
        } elseif ($value instanceof \DateTimeInterface) {
            $ts = $value->getTimestamp();
        }

        if ($ts === null) {
            return 0;
        }

        $today = strtotime(date('Y-m-d'));
        $endDay = strtotime(date('Y-m-d', $ts));

        return (int)round(($endDay - $today) / 86400);
    }
}

<?php

namespace Zr\PaidAccess\Admin;

use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UserTable;
use Zr\PaidAccess\Enum\GatewayEventType;
use Zr\PaidAccess\Enum\PaymentStatus;
use Zr\PaidAccess\Fund\FundMovementService;
use Zr\PaidAccess\Log\AuditLogService;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Payment\GatewayTransactionRepository;
use Zr\PaidAccess\Payment\PaymentCancellationService;
use Zr\PaidAccess\Payment\PaymentCompletionService;
use Zr\PaidAccess\Payment\PaymentRepository;
use Zr\PaidAccess\Subscription\BillingPolicy;
use Zr\PaidAccess\Subscription\SubscriptionAmountBreakdown;
use Zr\PaidAccess\Subscription\SubscriptionService;
use Zr\PaidAccess\Tables\PaymentTable;

/**
 * Админка: список платежей, ручное создание и подтверждение оплаты.
 */
class PaymentAdminService
{
    public const MANUAL_GATEWAY_CODE = 'manual';

    /**
     * Статусы для выбора в админке (форма, фильтр).
     *
     * @return array<string, string>
     */
    public static function getStatusTitles()
    {
        return [
            PaymentStatus::PENDING => 'Ожидает оплаты',
            PaymentStatus::PAID => 'Оплачен',
            PaymentStatus::FAILED => 'Ошибка',
            PaymentStatus::REFUNDED => 'Возврат',
            PaymentStatus::CANCELLED => 'Отмена',
        ];
    }

    public static function getStatusTitle($status)
    {
        $titles = self::getStatusTitles();
        if (isset($titles[$status])) {
            return $titles[$status];
        }

        if ($status === PaymentStatus::AUTHORIZED) {
            return 'Авторизован';
        }

        return (string)$status;
    }

    /**
     * @param array<string, mixed> $filterData
     * @return array<string, mixed>
     */
    public static function buildGridFilter(array $filterData)
    {
        $filter = [];

        if (!empty($filterData['USER_ID'])) {
            $filter['=USER_ID'] = (int)$filterData['USER_ID'];
        }

        if (!empty($filterData['STATUS'])) {
            $filter['=STATUS'] = $filterData['STATUS'];
        }

        if (!empty($filterData['BILLING_PERIOD'])) {
            $filter['=BILLING_PERIOD'] = $filterData['BILLING_PERIOD'];
        }

        if (!empty($filterData['ORDER_ID'])) {
            $filter['%ORDER_ID'] = $filterData['ORDER_ID'];
        }

        if (!empty($filterData['GATEWAY_CODE'])) {
            $filter['=GATEWAY_CODE'] = $filterData['GATEWAY_CODE'];
        }

        if (!empty($filterData['ID_from']) || !empty($filterData['ID_to'])) {
            if (!empty($filterData['ID_from']) && !empty($filterData['ID_to'])) {
                $filter['><ID'] = [(int)$filterData['ID_from'], (int)$filterData['ID_to']];
            } elseif (!empty($filterData['ID_from'])) {
                $filter['>=ID'] = (int)$filterData['ID_from'];
            } else {
                $filter['<=ID'] = (int)$filterData['ID_to'];
            }
        }

        if (!empty($filterData['DATE_CREATE_from'])) {
            $filter['>=DATE_CREATE'] = $filterData['DATE_CREATE_from'];
        }

        if (!empty($filterData['DATE_CREATE_to'])) {
            $filter['<=DATE_CREATE'] = $filterData['DATE_CREATE_to'];
        }

        return $filter;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getUserPreview($userId)
    {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return null;
        }

        return UserTable::getByPrimary($userId, [
            'select' => ['ID', 'LOGIN', 'NAME', 'LAST_NAME', 'EMAIL'],
        ])->fetch() ?: null;
    }

    public static function formatUserLabel(array $userRow)
    {
        $name = trim(($userRow['NAME'] ?? '') . ' ' . ($userRow['LAST_NAME'] ?? ''));
        $login = (string)($userRow['LOGIN'] ?? '');
        $email = (string)($userRow['EMAIL'] ?? '');

        $label = '[' . (int)$userRow['ID'] . ']';
        if ($name !== '') {
            $label .= ' ' . $name;
        } elseif ($login !== '') {
            $label .= ' ' . $login;
        }
        if ($email !== '') {
            $label .= ' (' . $email . ')';
        }

        return $label;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function validateSaveData(array $data, $isNew, ?int $paymentId = null)
    {
        $errors = [];

        $userId = (int)($data['USER_ID'] ?? 0);
        if ($userId <= 0) {
            $errors[] = 'Укажите ID пользователя';
        } elseif (!self::getUserPreview($userId)) {
            $errors[] = 'Пользователь #' . $userId . ' не найден';
        }

        $period = BillingPolicy::normalizeBillingPeriodInput(
            trim((string)($data['BILLING_PERIOD'] ?? '')),
            $userId
        );
        if ($period === '') {
            $errors[] = 'Укажите расчётный период';
        } else {
            try {
                BillingPolicy::assertValidBillingPeriod($period);
            } catch (\RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }

        $status = (string)($data['STATUS'] ?? '');
        if (PaymentStatus::isPaidLike($status)
            && PaidAccessCore::isBillingEnforceOnePayment()
            && $userId > 0
            && $period !== ''
        ) {
            $excludeId = $isNew ? null : (int)($data['ID'] ?? $paymentId ?? 0);
            if (PaymentRepository::hasPaidInPeriod($userId, $period, $excludeId > 0 ? $excludeId : null)) {
                $errors[] = 'За период ' . BillingPolicy::formatPeriodLabel($period) . ' у пользователя уже есть успешная оплата';
            }
        }

        $amount = (float)str_replace(',', '.', (string)($data['AMOUNT'] ?? '0'));
        if ($amount <= 0) {
            $errors[] = 'Сумма должна быть больше нуля';
        }

        if (!PaymentStatus::isValid($status)) {
            $errors[] = 'Некорректный статус платежа';
        } elseif ($status === PaymentStatus::AUTHORIZED) {
            $errors[] = 'Статус «Авторизован» недоступен. Укажите «Оплачен» или другой актуальный статус';
        }

        if ($status === PaymentStatus::CANCELLED && !$isNew) {
            $checkId = (int)($paymentId ?? $data['ID'] ?? 0);
            if ($checkId > 0) {
                $existingPayment = PaymentRepository::getById($checkId);
                if ($existingPayment
                    && (string)$existingPayment['STATUS'] !== PaymentStatus::CANCELLED
                    && !PaymentCancellationService::canCancel($existingPayment)
                ) {
                    $errors[] = 'Платёж нельзя отменить в текущем статусе';
                }
            }
        }

        return $errors;
    }

    /**
     * Создание или обновление платежа из админки.
     *
     * @param array<string, mixed> $data
     */
    public static function save(array $data, $paymentId = null)
    {
        $paymentId = $paymentId !== null ? (int)$paymentId : 0;
        $isNew = $paymentId <= 0;

        $errors = self::validateSaveData($data, $isNew, $isNew ? null : $paymentId);
        if ($errors !== []) {
            throw new \RuntimeException(implode('; ', $errors));
        }

        $userId = (int)$data['USER_ID'];
        $billingPeriod = BillingPolicy::normalizeBillingPeriodInput(
            trim((string)$data['BILLING_PERIOD']),
            $userId
        );
        $amount = (float)str_replace(',', '.', (string)$data['AMOUNT']);
        $status = (string)$data['STATUS'];
        $description = trim((string)($data['DESCRIPTION'] ?? ''));
        $currency = trim((string)($data['CURRENCY'] ?? 'RUB'));
        if ($currency === '') {
            $currency = 'RUB';
        }

        $existing = $isNew ? null : PaymentRepository::getById($paymentId);
        if (!$isNew && !$existing) {
            throw new \RuntimeException('Платёж не найден');
        }

        $wasPaidLike = $existing && PaymentStatus::isPaidLike((string)$existing['STATUS']);
        $willBePaidLike = PaymentStatus::isPaidLike($status);
        $wasGrantingAccess = $existing && PaymentStatus::grantsAccess((string)$existing['STATUS']);
        $willGrantAccess = PaymentStatus::grantsAccess($status);

        $amountBreakdown = SubscriptionAmountBreakdown::forManualPaymentAmount($amount);

        $fields = array_merge([
            'USER_ID' => $userId,
            'BILLING_PERIOD' => $billingPeriod,
            'CURRENCY' => $currency,
            'DESCRIPTION' => $description !== '' ? $description : 'Ручной платёж (админка)',
        ], $amountBreakdown->toPaymentAmountFields());

        if ($isNew) {
            $fields['GATEWAY_CODE'] = self::MANUAL_GATEWAY_CODE;
            $fields['GATEWAY_ID'] = 0;
            $fields['STATUS'] = $willGrantAccess ? PaymentStatus::PENDING : $status;
            $tempOrderId = 'PA-TMP-' . $userId . '-' . time();
            $fields['ORDER_ID'] = $tempOrderId;
            $paymentId = PaymentRepository::create($fields);
            $orderId = 'PA-' . $paymentId . '-' . $billingPeriod;
            PaymentRepository::update($paymentId, ['ORDER_ID' => $orderId]);

            if ($willGrantAccess) {
                PaymentCompletionService::completePayment($paymentId, '', 'ADMIN_MANUAL');
                GatewayTransactionRepository::log(
                    $paymentId,
                    self::MANUAL_GATEWAY_CODE,
                    GatewayEventType::ADMIN_MANUAL,
                    json_encode(['source' => 'admin', 'action' => 'create_paid'], JSON_UNESCAPED_UNICODE),
                    null,
                    true,
                    'ADMIN_MANUAL',
                    PaymentStatus::PAID,
                    null
                );
            }

            $created = PaymentRepository::getById($paymentId);
            AuditLogService::log(
                'payment',
                $paymentId,
                'create',
                null,
                AuditLogService::encodeSnapshot($created),
                'Создание платежа в админке'
            );

            return $paymentId;
        }

        if ($status === PaymentStatus::CANCELLED
            && (!$existing || (string)$existing['STATUS'] !== PaymentStatus::CANCELLED)
        ) {
            PaymentCancellationService::cancel($paymentId);
        } elseif ($willGrantAccess && !$wasGrantingAccess) {
            // Статус и DATE_PAID выставляет completePayment — иначе он считает платёж уже оплаченным.
            PaymentRepository::update($paymentId, $fields);
            PaymentCompletionService::completePayment($paymentId, '', 'ADMIN_MANUAL');
            GatewayTransactionRepository::log(
                $paymentId,
                self::MANUAL_GATEWAY_CODE,
                GatewayEventType::ADMIN_MANUAL,
                json_encode(['source' => 'admin', 'action' => 'mark_paid'], JSON_UNESCAPED_UNICODE),
                null,
                true,
                'ADMIN_MANUAL',
                PaymentStatus::PAID,
                null
            );
        } else {
            $fields['STATUS'] = $status;

            if (!PaymentStatus::isPaidLike($status)) {
                $fields['DATE_PAID'] = null;
            } elseif ($willGrantAccess && empty($existing['DATE_PAID'])) {
                $fields['DATE_PAID'] = new DateTime();
            }

            PaymentRepository::update($paymentId, $fields);

            if ($willGrantAccess && $wasGrantingAccess) {
                PaymentCompletionService::completePayment($paymentId, '', 'ADMIN_MANUAL');
            } elseif ($wasGrantingAccess && !$willGrantAccess) {
                SubscriptionService::syncAfterPaymentAccessRevoked($paymentId, $userId);
            }
        }

        if ($status === PaymentStatus::FAILED && (!$existing || (string)$existing['STATUS'] !== PaymentStatus::FAILED)) {
            \Zr\PaidAccess\Notification\SubscriptionNotificationService::onPaymentFailed(
                $paymentId,
                'Статус изменён вручную в админке'
            );
        }

        if ($status === PaymentStatus::REFUNDED
            || ($wasGrantingAccess && !$willGrantAccess && $status !== PaymentStatus::CANCELLED)
        ) {
            FundMovementService::tryRecordPaymentRefund($paymentId);
        }

        $updated = PaymentRepository::getById($paymentId);
        AuditLogService::log(
            'payment',
            $paymentId,
            'update',
            AuditLogService::encodeSnapshot($existing),
            AuditLogService::encodeSnapshot($updated),
            'Изменение платежа в админке'
        );

        return $paymentId;
    }

    public static function delete($paymentId)
    {
        $paymentId = (int)$paymentId;
        if ($paymentId <= 0) {
            return;
        }

        $existing = PaymentRepository::getById($paymentId);
        $userId = is_array($existing) ? (int)$existing['USER_ID'] : 0;

        $result = PaymentTable::delete($paymentId);
        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        if ($userId > 0) {
            SubscriptionService::reconcileUserSubscription($userId);
        }

        AuditLogService::log(
            'payment',
            $paymentId,
            'delete',
            AuditLogService::encodeSnapshot($existing),
            null,
            'Удаление платежа в админке'
        );
    }

    public static function getDefaultBillingPeriod($userId = 0, $siteId = null)
    {
        return BillingPolicy::getCurrentBillingPeriod((int)$userId, $siteId);
    }

    public static function getDefaultAmount($siteId = null)
    {
        return PaidAccessCore::getSubscriptionChargeTotal($siteId);
    }
}

<?php

namespace Zr\PaidAccess\PublicUi;

use Bitrix\Main\Type\DateTime;
use Zr\PaidAccess\Payment\PaymentManagementService;
use Zr\PaidAccess\Payment\PaymentRepository;

/**
 * Редактирование статуса платежа модератором (view-model + save).
 */
class ModeratorPaymentEditService
{
    /**
     * @return array<string, mixed>
     */
    public static function buildViewModel(int $paymentId): array
    {
        $paymentId = max(0, $paymentId);
        if ($paymentId <= 0) {
            throw new \InvalidArgumentException('Не указан платёж');
        }

        $payment = PaymentRepository::getById($paymentId);
        if ($payment === null) {
            throw new \RuntimeException('Платёж не найден');
        }

        $user = PaymentManagementService::getUserPreview((int)$payment['USER_ID']);
        $userLabel = $user !== null ? PaymentManagementService::formatUserLabel($user) : '—';

        return [
            'PAYMENT' => $payment,
            'PAYMENT_ID' => $paymentId,
            'USER_LABEL' => $userLabel,
            'DATE_CREATE_LABEL' => self::formatDateTime($payment['DATE_CREATE'] ?? null),
            'DATE_PAID_LABEL' => self::formatDateTime($payment['DATE_PAID'] ?? null),
            'AMOUNT_FORMATTED' => number_format((float)($payment['AMOUNT'] ?? 0), 2, '.', ' '),
            'STATUS_OPTIONS' => PaymentManagementService::getStatusTitles(),
        ];
    }

    public static function saveStatus(int $paymentId, string $status): void
    {
        $paymentId = max(0, $paymentId);
        if ($paymentId <= 0) {
            throw new \InvalidArgumentException('Не указан платёж');
        }

        $payment = PaymentRepository::getById($paymentId);
        if ($payment === null) {
            throw new \RuntimeException('Платёж не найден');
        }

        PaymentManagementService::save([
            'ID' => $paymentId,
            'USER_ID' => (int)$payment['USER_ID'],
            'BILLING_PERIOD' => (string)$payment['BILLING_PERIOD'],
            'AMOUNT' => (float)$payment['AMOUNT'],
            'CURRENCY' => (string)($payment['CURRENCY'] ?? 'RUB'),
            'DESCRIPTION' => (string)($payment['DESCRIPTION'] ?? ''),
            'STATUS' => trim($status),
        ], $paymentId);
    }

    /**
     * @param mixed $value
     */
    private static function formatDateTime($value): string
    {
        if ($value instanceof DateTime) {
            return $value->format('d.m.Y H:i:s');
        }

        if ($value === null || $value === '') {
            return '—';
        }

        return (string)$value;
    }
}

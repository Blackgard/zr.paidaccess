<?php

namespace Zr\PaidAccess\Payment;

use Bitrix\Main\Type\DateTime;
use Zr\PaidAccess\Enum\PaymentStatus;
use Zr\PaidAccess\Tables\PaymentTable;

class PaymentRepository
{
    public static function create(array $fields): int
    {
        $now = new DateTime();
        $data = array_merge([
            'STATUS' => PaymentStatus::PENDING,
            'DATE_CREATE' => $now,
            'DATE_UPDATE' => $now,
        ], $fields);

        $result = PaymentTable::add($data);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return (int)$result->getId();
    }

    public static function update(int $id, array $fields): void
    {
        $fields['DATE_UPDATE'] = new DateTime();
        $result = PaymentTable::update($id, $fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }

    public static function getById(int $id): ?array
    {
        $row = PaymentTable::getByPrimary($id)->fetch();

        return is_array($row) ? $row : null;
    }

    public static function getByOrderId(string $orderId): ?array
    {
        $row = PaymentTable::getList([
            'filter' => ['=ORDER_ID' => $orderId],
            'limit' => 1,
        ])->fetch();

        return is_array($row) ? $row : null;
    }

    public static function findPendingForPeriod(int $userId, string $billingPeriod): ?array
    {
        $row = PaymentTable::getList([
            'filter' => [
                '=USER_ID' => $userId,
                '=BILLING_PERIOD' => $billingPeriod,
                '=STATUS' => PaymentStatus::PENDING,
            ],
            'order' => ['ID' => 'DESC'],
            'limit' => 1,
        ])->fetch();

        return is_array($row) ? $row : null;
    }

    public static function hasAnyPayment(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $row = PaymentTable::getList([
            'filter' => ['=USER_ID' => $userId],
            'limit' => 1,
        ])->fetch();

        return (bool)$row;
    }

    public static function hasPaidInPeriod(
        int $userId,
        string $billingPeriod,
        ?int $excludePaymentId = null,
        bool $accessGrantingOnly = false
    ): bool {
        $filter = [
            '=USER_ID' => $userId,
            '=BILLING_PERIOD' => $billingPeriod,
            '@STATUS' => $accessGrantingOnly
                ? [PaymentStatus::PAID]
                : [PaymentStatus::PAID, PaymentStatus::AUTHORIZED],
        ];

        if ($excludePaymentId !== null && $excludePaymentId > 0) {
            $filter['!=ID'] = $excludePaymentId;
        }

        $row = PaymentTable::getList([
            'filter' => $filter,
            'limit' => 1,
        ])->fetch();

        return (bool)$row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findLatestAccessGrantingPayment(int $userId, ?int $excludePaymentId = null): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $filter = [
            '=USER_ID' => $userId,
            '=STATUS' => PaymentStatus::PAID,
        ];

        if ($excludePaymentId !== null && $excludePaymentId > 0) {
            $filter['!=ID'] = $excludePaymentId;
        }

        $row = PaymentTable::getList([
            'filter' => $filter,
            'order' => ['DATE_PAID' => 'DESC', 'ID' => 'DESC'],
            'limit' => 1,
        ])->fetch();

        return is_array($row) ? $row : null;
    }
}

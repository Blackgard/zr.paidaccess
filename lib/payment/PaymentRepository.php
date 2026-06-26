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

    public static function findByGatewayPaymentId(string $gatewayPaymentId, ?int $gatewayId = null): ?array
    {
        $gatewayPaymentId = trim($gatewayPaymentId);
        if ($gatewayPaymentId === '') {
            return null;
        }

        $filter = ['=GATEWAY_PAYMENT_ID' => $gatewayPaymentId];
        if ($gatewayId !== null && $gatewayId > 0) {
            $filter['=GATEWAY_ID'] = $gatewayId;
        }

        $row = PaymentTable::getList([
            'filter' => $filter,
            'order' => ['ID' => 'DESC'],
            'limit' => 1,
        ])->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * Поиск платежа для webhook: orderId шлюза, затем paymentId шлюза.
     */
    public static function findForWebhook(string $orderId, string $gatewayPaymentId, ?int $gatewayId = null): ?array
    {
        $orderId = trim($orderId);
        if ($orderId !== '') {
            $byOrder = self::getByOrderId($orderId);
            if ($byOrder) {
                return $byOrder;
            }
        }

        return self::findByGatewayPaymentId($gatewayPaymentId, $gatewayId);
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

    /**
     * Pending-платёж с тем же набором покрываемых периодов.
     *
     * @param string[] $coveredPeriods
     */
    public static function findPendingForCoveredPeriods(int $userId, array $coveredPeriods): ?array
    {
        if ($userId <= 0 || $coveredPeriods === []) {
            return null;
        }

        $result = PaymentTable::getList([
            'filter' => [
                '=USER_ID' => $userId,
                '=STATUS' => PaymentStatus::PENDING,
            ],
            'order' => ['ID' => 'DESC'],
        ]);

        while ($row = $result->fetch()) {
            if (!is_array($row)) {
                continue;
            }

            if (self::coveredPeriodsEqual(PaymentCoveredPeriods::fromPaymentRow($row), $coveredPeriods)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Последний неуспешный платёж за период (для повторного открытия в preparePayment).
     */
    public static function findFailedForPeriod(int $userId, string $billingPeriod): ?array
    {
        $row = PaymentTable::getList([
            'filter' => [
                '=USER_ID' => $userId,
                '=BILLING_PERIOD' => $billingPeriod,
                '=STATUS' => PaymentStatus::FAILED,
            ],
            'order' => ['ID' => 'DESC'],
            'limit' => 1,
        ])->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param string[] $coveredPeriods
     */
    public static function findFailedForCoveredPeriods(int $userId, array $coveredPeriods): ?array
    {
        if ($userId <= 0 || $coveredPeriods === []) {
            return null;
        }

        $result = PaymentTable::getList([
            'filter' => [
                '=USER_ID' => $userId,
                '=STATUS' => PaymentStatus::FAILED,
            ],
            'order' => ['ID' => 'DESC'],
        ]);

        while ($row = $result->fetch()) {
            if (!is_array($row)) {
                continue;
            }

            if (self::coveredPeriodsEqual(PaymentCoveredPeriods::fromPaymentRow($row), $coveredPeriods)) {
                return $row;
            }
        }

        return null;
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

        if ($row) {
            return true;
        }

        return self::hasPaidInCoveredPeriods($userId, $billingPeriod, $excludePaymentId, $accessGrantingOnly);
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

    /**
     * @param string[] $periods
     * @param string[] $expected
     */
    public static function coveredPeriodsEqual(array $periods, array $expected): bool
    {
        return PaymentCoveredPeriods::encode($periods) === PaymentCoveredPeriods::encode($expected);
    }

    protected static function hasPaidInCoveredPeriods(
        int $userId,
        string $billingPeriod,
        ?int $excludePaymentId = null,
        bool $accessGrantingOnly = false
    ): bool {
        $statuses = $accessGrantingOnly
            ? [PaymentStatus::PAID]
            : [PaymentStatus::PAID, PaymentStatus::AUTHORIZED];

        $filter = [
            '=USER_ID' => $userId,
            '@STATUS' => $statuses,
        ];

        if ($excludePaymentId !== null && $excludePaymentId > 0) {
            $filter['!=ID'] = $excludePaymentId;
        }

        $result = PaymentTable::getList([
            'filter' => $filter,
            'select' => ['ID', 'BILLING_PERIOD', 'COVERED_PERIODS'],
        ]);

        while ($row = $result->fetch()) {
            if (!is_array($row)) {
                continue;
            }

            if (PaymentCoveredPeriods::paymentCoversPeriod($row, $billingPeriod)) {
                return true;
            }
        }

        return false;
    }
}

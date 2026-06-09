<?php

namespace Zr\PaidAccess\Gateway\Providers\Tinkoff;

use Zr\PaidAccess\Enum\PaymentStatus;

class TinkoffStatusMapper
{
    public static function toInternal($tinkoffStatus)
    {
        switch (strtoupper($tinkoffStatus)) {
            case 'CONFIRMED':
                return PaymentStatus::PAID;
            case 'AUTHORIZED':
                return PaymentStatus::AUTHORIZED;
            case 'REJECTED':
                return PaymentStatus::FAILED;
            case 'CANCELED':
            case 'REVERSED':
                return PaymentStatus::CANCELLED;
            case 'REFUNDED':
                return PaymentStatus::REFUNDED;
            default:
                return PaymentStatus::PENDING;
        }
    }

    /**
     * Завершать оплату и открывать доступ только по CONFIRMED (одностадийная оплата).
     * AUTHORIZED — промежуточный статус, на него отвечаем OK без смены доступа.
     */
    public static function isPaidStatus($tinkoffStatus)
    {
        return strtoupper((string)$tinkoffStatus) === 'CONFIRMED';
    }

    public static function isIntermediateStatus($tinkoffStatus): bool
    {
        return strtoupper((string)$tinkoffStatus) === 'AUTHORIZED';
    }
}

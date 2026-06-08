<?php

namespace Zr\PaidAccess\Gateway\Providers\Tinkoff;

use Zr\PaidAccess\Enum\PaymentStatus;

class TinkoffStatusMapper
{
    public static function toInternal($tinkoffStatus)
    {
        switch (strtoupper($tinkoffStatus)) {
            case 'CONFIRMED':
            case 'AUTHORIZED':
                return PaymentStatus::PAID;
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

    public static function isPaidStatus($tinkoffStatus)
    {
        return in_array(strtoupper($tinkoffStatus), ['CONFIRMED', 'AUTHORIZED'], true);
    }
}

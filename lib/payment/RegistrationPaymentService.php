<?php

namespace Zr\PaidAccess\Payment;

use Zr\PaidAccess\Access\AccessControl;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Subscription\SubscriptionService;
use Zr\PaidAccess\Tools\Logger;

/**
 * F1: первый платёж и Init в банке после регистрации / первого входа.
 */
class RegistrationPaymentService
{
    public static function onUserRegistered(int $userId): void
    {
        if ($userId <= 0 || !PaidAccessCore::isModuleActive()) {
            return;
        }

        if (!AccessControl::isUserInRestrictedGroups($userId)) {
            return;
        }

        SubscriptionService::ensureRegisteredUser($userId);
        self::ensureInitialPayment($userId);
    }

    /**
     * Резервный сценарий: подтверждение email → первый вход, если при регистрации платёж не создался.
     */
    public static function onUserLogin(int $userId): void
    {
        if ($userId <= 0 || !PaidAccessCore::isModuleActive()) {
            return;
        }

        if (!AccessControl::isUserInRestrictedGroups($userId)) {
            return;
        }

        if (PaymentRepository::hasAnyPayment($userId)) {
            return;
        }

        SubscriptionService::ensureRegisteredUser($userId);
        self::ensureInitialPayment($userId);
    }

    protected static function ensureInitialPayment(int $userId): void
    {
        try {
            SubscriptionPaymentService::preparePayment($userId);
            Logger::info('Registration payment prepared', ['userId' => $userId]);
        } catch (\Throwable $e) {
            Logger::warning('Registration payment not prepared', [
                'userId' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

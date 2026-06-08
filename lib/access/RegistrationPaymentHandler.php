<?php

namespace Zr\PaidAccess\Access;

use Bitrix\Main\Loader;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Payment\RegistrationPaymentService;

/**
 * F1: обработчики событий Bitrix после регистрации и входа.
 */
class RegistrationPaymentHandler
{
    /**
     * @param array<string, mixed> $arFields
     */
    public static function onAfterUserRegister(array $arFields): void
    {
        if (!self::isModuleReady()) {
            return;
        }

        $userId = (int)($arFields['USER_ID'] ?? $arFields['ID'] ?? 0);
        RegistrationPaymentService::onUserRegistered($userId);
    }

    /**
     * @param array<string, mixed> $arParams
     */
    public static function onAfterUserLogin(array $arParams): void
    {
        if (!self::isModuleReady()) {
            return;
        }

        $userId = (int)($arParams['USER_ID'] ?? 0);
        RegistrationPaymentService::onUserLogin($userId);
    }

    protected static function isModuleReady(): bool
    {
        if (!Loader::includeModule(PaidAccessCore::MODULE_ID)) {
            return false;
        }

        return PaidAccessCore::isModuleActive();
    }
}

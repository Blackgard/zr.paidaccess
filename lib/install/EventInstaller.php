<?php

namespace Zr\PaidAccess\Install;

use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;
use Zr\PaidAccess\Access\RegistrationPaymentHandler;
use Zr\PaidAccess\PaidAccessCore;

/**
 * Регистрация обработчиков F1/F7 на уже установленных копиях модуля (без переустановки).
 */
class EventInstaller
{
    private const OPTION_FLAG = 'EVENT_HANDLERS_F1_F7';

    public static function ensureEvents(): void
    {
        if (Option::get(PaidAccessCore::MODULE_ID, self::OPTION_FLAG, 'N') === 'Y') {
            return;
        }

        $eventManager = EventManager::getInstance();
        $moduleId = PaidAccessCore::MODULE_ID;

        $eventManager->registerEventHandler(
            'main',
            'OnAfterUserRegister',
            $moduleId,
            RegistrationPaymentHandler::class,
            'onAfterUserRegister'
        );
        $eventManager->registerEventHandler(
            'main',
            'OnAfterUserLogin',
            $moduleId,
            RegistrationPaymentHandler::class,
            'onAfterUserLogin'
        );

        Option::set($moduleId, self::OPTION_FLAG, 'Y');
    }
}

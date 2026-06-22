<?php

namespace Zr\PaidAccess\Install;

use Bitrix\Main\EventManager;
use Zr\PaidAccess\Access\AccessBlockHandler;
use Zr\PaidAccess\Access\RegistrationPaymentHandler;

/**
 * Единая точка регистрации событий модуля.
 */
class EventInstaller
{
    public static function ensureEvents(): void
    {
        $eventManager = EventManager::getInstance();

        foreach (self::getHandlers() as $handler) {
            [$fromModuleId, $event, $moduleId, $class, $method] = $handler;

            $eventManager->unRegisterEventHandler(
                $fromModuleId,
                $event,
                $moduleId,
                $class,
                $method
            );
            $eventManager->registerEventHandler(
                $fromModuleId,
                $event,
                $moduleId,
                $class,
                $method
            );
        }
    }

    public static function uninstallEvents(): void
    {
        $eventManager = EventManager::getInstance();

        foreach (self::getHandlers() as $handler) {
            [$fromModuleId, $event, $moduleId, $class, $method] = $handler;

            $eventManager->unRegisterEventHandler(
                $fromModuleId,
                $event,
                $moduleId,
                $class,
                $method
            );
        }
    }

    /**
     * @return array<int, array{string, string, string, class-string, string}>
     */
    private static function getHandlers(): array
    {
        return [
            ['main', 'OnBeforeProlog', 'zr.paidaccess', AccessBlockHandler::class, 'onBeforeProlog'],
            ['main', 'OnAfterUserRegister', 'zr.paidaccess', RegistrationPaymentHandler::class, 'onAfterUserRegister'],
            ['main', 'OnAfterUserLogin', 'zr.paidaccess', RegistrationPaymentHandler::class, 'onAfterUserLogin'],
        ];
    }
}

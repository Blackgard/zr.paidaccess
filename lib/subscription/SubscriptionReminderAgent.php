<?php

namespace Zr\PaidAccess\Subscription;

use Bitrix\Main\Loader;
use Zr\PaidAccess\Log\ModuleEventLogService;
use Zr\PaidAccess\PaidAccessCore;

/**
 * F7: ежедневная рассылка напоминаний об окончании подписки.
 */
class SubscriptionReminderAgent
{
    public static function run(): string
    {
        if (Loader::includeModule(PaidAccessCore::MODULE_ID) && PaidAccessCore::isModuleActive()) {
            $count = SubscriptionReminderService::processExpiringReminders();
            ModuleEventLogService::info(
                'subscription_reminder_agent',
                'SubscriptionReminderAgent processed reminders',
                ['count' => $count]
            );
        }

        return '\\' . __CLASS__ . '::run();';
    }
}

<?php

namespace Zr\PaidAccess\Subscription;

use Bitrix\Main\Loader;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Tools\Logger;

/**
 * F7: ежедневная синхронизация статуса debt (cron / агент Bitrix).
 */
class BillingDebtAgent
{
    public static function run(): string
    {
        if (Loader::includeModule(PaidAccessCore::MODULE_ID) && PaidAccessCore::isModuleActive()) {
            $count = BillingDebtService::processRestrictedUsers();
            Logger::info('BillingDebtAgent processed users', ['count' => $count]);
        }

        return '\\' . __CLASS__ . '::run();';
    }
}

<?php

/**
 * @link https://alexandr-zr.ru/
 * @author Alexandr Drachenin <alexdrachenin98@gmail.com>
 * @copyright Copyright (c) 2020-2026 ZR studio (https://alexandr-zr.ru/)
 */

namespace Zr\PaidAccess\Access;

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Subscription\BillingDebtService;

/**
 * Обработчик OnBeforeProlog: показ шаблона блокировки.
 */
class AccessBlockHandler
{
    public static function onBeforeProlog(): void
    {
        if (!Loader::includeModule(PaidAccessCore::MODULE_ID)) {
            return;
        }

        if (!PaidAccessCore::isModuleActive()) {
            return;
        }

        if (self::shouldSkipRequest()) {
            return;
        }

        global $USER;

        if (!is_object($USER) || !$USER->IsAuthorized()) {
            return;
        }

        $userId = (int)$USER->GetID();
        BillingDebtService::syncUserDebtStatus($userId);

        if (!AccessControl::mustShowBlockPage($userId)) {
            return;
        }

        self::renderBlockPage();
    }

    protected static function shouldSkipRequest(): bool
    {
        if (defined('ADMIN_SECTION') && ADMIN_SECTION === true) {
            return true;
        }

        if (defined('BX_CRONTAB') && BX_CRONTAB === true) {
            return true;
        }

        if (php_sapi_name() === 'cli') {
            return true;
        }

        $request = Context::getCurrent()->getRequest();

        if ($request->isAjaxRequest()) {
            return true;
        }

        return false;
    }

    protected static function renderBlockPage(): void
    {
        $templatePath = AccessTemplate::getTemplatePath();

        if (!is_file($templatePath)) {
            return;
        }

        $APPLICATION = $GLOBALS['APPLICATION'] ?? null;

        if (is_object($APPLICATION)) {
            $APPLICATION->RestartBuffer();
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        /** @noinspection PhpIncludeInspection */
        include $templatePath;

        die();
    }
}

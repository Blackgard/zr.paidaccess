<?php

namespace Zr\PaidAccess\Admin;

use Bitrix\Main\Web\Json;

/**
 * JSON-ответ для AJAX-действий в админке модуля.
 */
class AdminJsonResponse
{
    /**
     * @param array<string, mixed> $data
     */
    public static function send(array $data): void
    {
        global $APPLICATION;

        $APPLICATION->RestartBuffer();
        header('Content-Type: application/json; charset=UTF-8');
        echo Json::encode($data);
        \CMain::finalActions();
        die;
    }
}

<?php

/**
 * @link https://alexandr-zr.ru/
 * @author Alexandr Drachenin <alexdrachenin98@gmail.com>
 * @copyright Copyright (c) 2020-2026 ZR studio (https://alexandr-zr.ru/)
 */

namespace Zr\PaidAccess\Options;

use Bitrix\Main\GroupTable;
use Zr\PaidAccess\Access\AccessControl;
use Zr\PaidAccess\Access\AccessTemplate;
use Zr\PaidAccess\PaidAccessCore;

/**
 * Данные для формы настроек модуля (не бизнес-логика доступа на сайте).
 */
class ModuleOptionsProvider
{
    /**
     * @return array<int, string> id => "[id] name"
     */
    public static function getSelectableUserGroups(): array
    {
        $groups = [];

        $result = GroupTable::getList([
            'select' => ['ID', 'NAME', 'C_SORT'],
            'order' => ['C_SORT' => 'ASC', 'ID' => 'ASC'],
            'filter' => ['!=ID' => AccessControl::ADMIN_GROUP_ID],
        ]);

        while ($row = $result->fetch()) {
            $groups[(int)$row['ID']] = '[' . $row['ID'] . '] ' . $row['NAME'];
        }

        return $groups;
    }

    /**
     * @return array<string, string>
     */
    public static function getAvailableBlockTemplates(): array
    {
        return AccessTemplate::getAvailableTemplates();
    }

    /**
     * @return array<string, string>
     */
    public static function getBillingPeriodModeOptions(): array
    {
        return [
            PaidAccessCore::BILLING_PERIOD_MODE_CALENDAR_MONTH => 'Календарный месяц (YYYY-MM)',
            PaidAccessCore::BILLING_PERIOD_MODE_REGISTRATION => 'От даты регистрации (YYYY-MM-DD)',
            PaidAccessCore::BILLING_PERIOD_MODE_ANCHOR_MONTH => 'Персональный период от дня оплаты (YYYY-MM-DD)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getBillingAnchorSourceOptions(): array
    {
        return [
            PaidAccessCore::BILLING_ANCHOR_SOURCE_REGISTRATION => 'День регистрации пользователя',
            PaidAccessCore::BILLING_ANCHOR_SOURCE_FIXED => 'Фиксированный день (из настройки ниже)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getPaymentWidgetModeOptions(): array
    {
        return [
            PaidAccessCore::PAYMENT_WIDGET_MODE_QR_SBP => 'QR-код СБП',
            PaidAccessCore::PAYMENT_WIDGET_MODE_PAYMENT_BUTTON => 'Кнопка «Перейти к оплате» (форма T-Bank)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getBillingShortMonthPolicyOptions(): array
    {
        return [
            PaidAccessCore::BILLING_SHORT_MONTH_LAST_DAY => 'Последний день месяца (28/29/30)',
            PaidAccessCore::BILLING_SHORT_MONTH_PREVIOUS => 'Предпоследний день месяца',
        ];
    }
}

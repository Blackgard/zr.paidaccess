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
    public static function getPaymentDuplicateOrderPolicyOptions(): array
    {
        return [
            PaidAccessCore::PAYMENT_DUPLICATE_ORDER_FAIL => 'Перевести платёж в статус «Ошибка»',
            PaidAccessCore::PAYMENT_DUPLICATE_ORDER_IGNORE => 'Оставить «Ожидает оплаты» (только журнал и сообщение)',
            PaidAccessCore::PAYMENT_DUPLICATE_ORDER_REUSE => 'Привязать существующий платёж в T-Bank (CheckOrder)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getFundExpenseAllocationModeOptions(): array
    {
        return [
            PaidAccessCore::FUND_EXPENSE_ALLOCATION_MODE_EVEN => 'Равномерно на всех участников фонда',
            PaidAccessCore::FUND_EXPENSE_ALLOCATION_MODE_RANDOM => 'Случайно на N участников',
        ];
    }

    /**
     * Реестр пользовательских текстов на сайте (вкладка «Тексты на сайте»).
     * Новые сообщения добавляются сюда и в PaidAccessCore::OPTION_* + get*().
     *
     * @return array<string, array{
     *     TITLE: string,
     *     TYPE: string,
     *     DEFAULT: string,
     *     ROWS?: int,
     *     COLS?: int,
     *     WIDTH?: int,
     *     NOTE?: string,
     *     PLANNED?: bool
     * }>
     */
    public static function getUserMessageOptionDefinitions(): array
    {
        return [
            PaidAccessCore::OPTION_PAYMENT_PAGE_ERROR_TEXT => [
                'TITLE' => 'Страница оплаты: текст при ошибке',
                'TYPE' => 'textarea',
                'DEFAULT' => PaidAccessCore::DEFAULT_PAYMENT_PAGE_ERROR_TEXT,
                'ROWS' => 6,
                'COLS' => 120,
                'NOTE' => 'Показывается при сбое создания платежа или получения QR. Технические детали пишутся только в журнал модуля.',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function getUserMessageOptionCodes(): array
    {
        return array_keys(self::getUserMessageOptionDefinitions());
    }

    /**
     * Поля вкладки «Тексты на сайте» для options.php.
     *
     * @return array<string, mixed>
     */
    public static function buildUserMessageOptions(): array
    {
        $options = [
            'TITLE_USER_MESSAGES' => [
                'TYPE' => 'title',
                'TEXT' => 'Сообщения для посетителей',
            ],
            'NOTE_USER_MESSAGES_INTRO' => [
                'TYPE' => 'note',
                'TEXT' => 'Здесь настраиваются тексты, которые видит пользователь на сайте (не в письмах и не в админке). '
                    . 'Плейсхолдеры вроде {SITE_NAME} поддерживаются там, где указано в подсказке к полю.',
            ],
        ];

        foreach (self::getUserMessageOptionDefinitions() as $code => $definition) {
            if (!empty($definition['PLANNED'])) {
                continue;
            }

            $field = [
                'TITLE' => $definition['TITLE'],
                'TYPE' => $definition['TYPE'],
                'DEFAULT' => $definition['DEFAULT'],
            ];

            if (isset($definition['ROWS'], $definition['COLS'])) {
                $field['ROWS'] = $definition['ROWS'];
                $field['COLS'] = $definition['COLS'];
            }

            if (isset($definition['WIDTH'])) {
                $field['WIDTH'] = $definition['WIDTH'];
            }

            $options[$code] = $field;

            if (!empty($definition['NOTE'])) {
                $options['NOTE_' . $code] = [
                    'TYPE' => 'note',
                    'TEXT' => (string)$definition['NOTE'],
                ];
            }
        }

        $options['NOTE_USER_MESSAGES_PLANNED'] = [
            'TYPE' => 'note',
            'TEXT' => 'Планируется вынести сюда: тексты блока подписки в личном кабинете, страницы блокировки доступа, '
                . 'подсказки в кошельке фонда. Добавление поля — запись в getUserMessageOptionDefinitions() и геттер в PaidAccessCore.',
        ];

        return $options;
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

<?php

namespace Zr\PaidAccess\Options;

use Zr\PaidAccess\PaidAccessCore;

/**
 * Вкладки и поля формы настроек модуля (options.php).
 */
final class ModuleOptionsStructure
{
    /**
     * @return array<string, array{ID: string, TITLE: string, OPTIONS: array<string, mixed>}>
     */
    public static function getGroups(): array
    {
        return [
            self::groupGeneral(),
            self::groupBilling(),
            self::groupAccess(),
            self::groupPaymentTariff(),
            self::groupPaymentFlow(),
            self::groupUserMessages(),
            self::groupUserNotifications(),
            self::groupLogging(),
        ];
    }

    /**
     * @return array{ID: string, TITLE: string, OPTIONS: array<string, mixed>}
     */
    private static function groupGeneral(): array
    {
        return [
            'ID' => 'general',
            'TITLE' => 'Общие',
            'OPTIONS' => [
                'TITLE_MODULE_ACTIVE_BLOCK' => [
                    'TYPE' => 'title',
                    'TEXT' => 'Работа модуля',
                ],
                'MODULE_ACTIVE' => [
                    'TITLE' => 'Модуль активен',
                    'TYPE' => 'checkbox',
                    'DEFAULT' => 'Y',
                ],
            ],
        ];
    }

    /**
     * @return array{ID: string, TITLE: string, OPTIONS: array<string, mixed>}
     */
    private static function groupBilling(): array
    {
        return [
            'ID' => 'billing',
            'TITLE' => 'Календарь оплаты',
            'OPTIONS' => [
                'TITLE_BILLING_BLOCK' => [
                    'TYPE' => 'title',
                    'TEXT' => 'Расчётный период и срок оплаты',
                ],
                'BILLING_PERIOD_MODE' => [
                    'TITLE' => 'Тип расчётного периода',
                    'TYPE' => 'selectbox',
                    'VALUES' => ModuleOptionsProvider::getBillingPeriodModeOptions(),
                    'DEFAULT' => PaidAccessCore::DEFAULT_BILLING_PERIOD_MODE,
                ],
                'BILLING_ANCHOR_SOURCE' => [
                    'TITLE' => 'День срока оплаты в периоде',
                    'TYPE' => 'selectbox',
                    'VALUES' => ModuleOptionsProvider::getBillingAnchorSourceOptions(),
                    'DEFAULT' => PaidAccessCore::DEFAULT_BILLING_ANCHOR_SOURCE,
                ],
                'BILLING_FIXED_DAY' => [
                    'TITLE' => 'Фиксированный день оплаты (1–31)',
                    'TYPE' => 'text',
                    'DEFAULT' => PaidAccessCore::DEFAULT_BILLING_FIXED_DAY,
                    'WIDTH' => 5,
                ],
                'BILLING_SHORT_MONTH_POLICY' => [
                    'TITLE' => 'Если день оплаты 29–31, а в месяце меньше дней',
                    'TYPE' => 'selectbox',
                    'VALUES' => ModuleOptionsProvider::getBillingShortMonthPolicyOptions(),
                    'DEFAULT' => PaidAccessCore::DEFAULT_BILLING_SHORT_MONTH_POLICY,
                ],
                'BILLING_ENFORCE_ONE_PAYMENT' => [
                    'TITLE' => 'Не более одной успешной оплаты за период',
                    'TYPE' => 'checkbox',
                    'DEFAULT' => PaidAccessCore::DEFAULT_BILLING_ENFORCE_ONE_PAYMENT,
                ],
                'BILLING_GRACE_DAYS' => [
                    'TITLE' => 'Льготные дни после срока оплаты',
                    'TYPE' => 'text',
                    'DEFAULT' => PaidAccessCore::DEFAULT_BILLING_GRACE_DAYS,
                    'WIDTH' => 5,
                ],
                'NOTE_BILLING_PERIOD' => [
                    'TYPE' => 'note',
                    'TEXT' => 'Календарный месяц: период хранится как YYYY-MM. Персональный период: от дня оплаты до следующего такого дня, ключ YYYY-MM-DD (дата начала). '
                        . 'Срок оплаты — день в периоде по правилу выше; после него (плюс льготные дни) пользователь считается должником.',
                ],
            ],
        ];
    }

    /**
     * @return array{ID: string, TITLE: string, OPTIONS: array<string, mixed>}
     */
    private static function groupAccess(): array
    {
        return [
            'ID' => 'access',
            'TITLE' => 'Доступ к сайту',
            'OPTIONS' => [
                'TITLE_ACCESS_BLOCK' => [
                    'TYPE' => 'title',
                    'TEXT' => 'Блокировка при неоплаченной подписке',
                ],
                'ACCESS_RESTRICTED_GROUPS' => [
                    'TITLE' => 'Группы пользователей для проверки подписки',
                    'TYPE' => 'multiselectbox',
                    'VALUES' => ModuleOptionsProvider::getSelectableUserGroups(),
                    'DEFAULT' => PaidAccessCore::DEFAULT_ACCESS_RESTRICTED_GROUPS,
                ],
                'ACCESS_BLOCK_TEMPLATE' => [
                    'TITLE' => 'Шаблон страницы блокировки',
                    'TYPE' => 'selectbox',
                    'VALUES' => ModuleOptionsProvider::getAvailableBlockTemplates(),
                    'DEFAULT' => PaidAccessCore::DEFAULT_BLOCK_TEMPLATE,
                ],
                'TITLE_DOCUMENT_CONSENT_BLOCK' => [
                    'TYPE' => 'title',
                    'TEXT' => 'Обязательные документы',
                ],
                'DOCUMENT_CONSENT_ENABLED' => [
                    'TITLE' => 'Проверять согласие с обязательными документами',
                    'TYPE' => 'checkbox',
                    'DEFAULT' => PaidAccessCore::DEFAULT_DOCUMENT_CONSENT_ENABLED,
                ],
                'DOCUMENT_CONSENT_BLOCK_TEMPLATE' => [
                    'TITLE' => 'Шаблон страницы согласия с документами',
                    'TYPE' => 'selectbox',
                    'VALUES' => ModuleOptionsProvider::getAvailableDocumentConsentTemplates(),
                    'DEFAULT' => PaidAccessCore::DEFAULT_DOCUMENT_CONSENT_BLOCK_TEMPLATE,
                ],
                'NOTE_DOCUMENT_CONSENT' => [
                    'TYPE' => 'note',
                    'TEXT' => 'Проверка выполняется для тех же групп, что и подписка. '
                        . 'Список документов — в разделе «Документы» админки модуля. '
                        . 'При публикации новой версии пользователь должен подтвердить её повторно.',
                ],
                'NOTE_ACCESS_TEMPLATES' => [
                    'TYPE' => 'note',
                    'TEXT' => 'Шаблоны: /local/php_interface/zr.paidaccess/template_*.php. '
                        . 'Тексты страницы блокировки будут вынесены на вкладку «Тексты на сайте».',
                ],
            ],
        ];
    }

    /**
     * @return array{ID: string, TITLE: string, OPTIONS: array<string, mixed>}
     */
    private static function groupPaymentTariff(): array
    {
        return [
            'ID' => 'payment_tariff',
            'TITLE' => 'Тариф и счёт',
            'OPTIONS' => [
                'TITLE_SUBSCRIPTION_AMOUNTS' => [
                    'TYPE' => 'title',
                    'TEXT' => 'Суммы подписки',
                ],
                'SUBSCRIPTION_FUND_AMOUNT' => [
                    'TITLE' => 'Фондовый взнос (руб.)',
                    'TYPE' => 'text',
                    'DEFAULT' => PaidAccessCore::DEFAULT_SUBSCRIPTION_FUND_AMOUNT,
                    'WIDTH' => 10,
                ],
                'SUBSCRIPTION_TAX_AMOUNT' => [
                    'TITLE' => 'Налоги (руб.)',
                    'TYPE' => 'text',
                    'DEFAULT' => PaidAccessCore::DEFAULT_SUBSCRIPTION_TAX_AMOUNT,
                    'WIDTH' => 10,
                ],
                'SUBSCRIPTION_MAINTENANCE_AMOUNT' => [
                    'TITLE' => 'Содержание сайта / ФОТ (руб.)',
                    'TYPE' => 'text',
                    'DEFAULT' => PaidAccessCore::DEFAULT_SUBSCRIPTION_MAINTENANCE_AMOUNT,
                    'WIDTH' => 10,
                ],
                'NOTE_SUBSCRIPTION_AMOUNT_BREAKDOWN' => [
                    'TYPE' => 'note',
                    'TEXT' => 'Клиенту выставляется счёт на сумму: фондовый взнос + налоги + ФОТ. '
                        . 'В учётный фонд и в интерфейсе модуля попадает только фондовый взнос. '
                        . 'Пример: 1000 + 130 + 300 = 1430 ₽ к оплате.',
                ],
                'TITLE_FUND_EXPENSE_ALLOCATION' => [
                    'TYPE' => 'title',
                    'TEXT' => 'Списания с учредительного фонда',
                ],
                'FUND_EXPENSE_ALLOCATION_MODE' => [
                    'TITLE' => 'Распределение суммы списания между участниками',
                    'TYPE' => 'selectbox',
                    'VALUES' => ModuleOptionsProvider::getFundExpenseAllocationModeOptions(),
                    'DEFAULT' => PaidAccessCore::DEFAULT_FUND_EXPENSE_ALLOCATION_MODE,
                ],
                'FUND_EXPENSE_RANDOM_PARTICIPANT_COUNT' => [
                    'TITLE' => 'Участников при случайном распределении (N)',
                    'TYPE' => 'text',
                    'DEFAULT' => PaidAccessCore::DEFAULT_FUND_EXPENSE_RANDOM_PARTICIPANT_COUNT,
                    'WIDTH' => 5,
                ],
                'NOTE_FUND_EXPENSE_ALLOCATION' => [
                    'TYPE' => 'note',
                    'TEXT' => 'При ручном списании с фонда в админке сумма делится между участниками с положительным вкладом. '
                        . '«Равномерно» — на всех; «Случайно» — на N выбранных участников. '
                        . 'Доли фиксируются в журнале движений фонда (только админка).',
                ],
                'TITLE_PAYMENT_BANK' => [
                    'TYPE' => 'title',
                    'TEXT' => 'Данные для банка',
                ],
                'PAYMENT_DESCRIPTION' => [
                    'TITLE' => 'Назначение платежа в банке',
                    'TYPE' => 'text',
                    'DEFAULT' => PaidAccessCore::DEFAULT_PAYMENT_DESCRIPTION,
                    'WIDTH' => 60,
                ],
                'NOTE_PAYMENT_DESCRIPTION' => [
                    'TYPE' => 'note',
                    'TEXT' => 'Используется при создании платежа (Init в T-Bank) и в фискальном чеке. '
                        . 'Плейсхолдер {SITE_NAME} заменяется на название сайта из настроек Bitrix.',
                ],
            ],
        ];
    }

    /**
     * @return array{ID: string, TITLE: string, OPTIONS: array<string, mixed>}
     */
    private static function groupPaymentFlow(): array
    {
        return [
            'ID' => 'payment_flow',
            'TITLE' => 'Процесс оплаты',
            'OPTIONS' => [
                'TITLE_GATEWAYS_BLOCK' => [
                    'TYPE' => 'title',
                    'TEXT' => 'Платёжные шлюзы',
                ],
                'NOTE_GATEWAYS_ADMIN' => [
                    'TYPE' => 'statichtml',
                    'VALUE' => 'Платёжные шлюзы настраиваются в разделе '
                        . '<a href="/bitrix/admin/zr_paidaccess_gateways.php">Настройки → Платёжный доступ → Платёжные шлюзы</a>. '
                        . 'По умолчанию таблица пустая — создайте шлюз и отметьте «Использовать по умолчанию».',
                ],
                'TITLE_PAYMENT_UI' => [
                    'TYPE' => 'title',
                    'TEXT' => 'Страница оплаты на сайте',
                ],
                'PAYMENT_WIDGET_MODE' => [
                    'TITLE' => 'Способ оплаты',
                    'TYPE' => 'selectbox',
                    'VALUES' => ModuleOptionsProvider::getPaymentWidgetModeOptions(),
                    'DEFAULT' => PaidAccessCore::DEFAULT_PAYMENT_WIDGET_MODE,
                ],
                'NOTE_PAYMENT_WIDGET_MODE' => [
                    'TYPE' => 'note',
                    'TEXT' => 'QR СБП — пользователь сканирует код в приложении банка. '
                        . 'Кнопка T-Bank — ссылка на классическую платёжную форму банка. '
                        . 'Автоперенаправление на форму банка настраивается в карточке шлюза T-Bank.',
                ],
                'PAYMENT_DUPLICATE_ORDER_POLICY' => [
                    'TITLE' => 'Дубликат order_id в T-Bank (ошибка 8)',
                    'TYPE' => 'selectbox',
                    'VALUES' => ModuleOptionsProvider::getPaymentDuplicateOrderPolicyOptions(),
                    'DEFAULT' => PaidAccessCore::DEFAULT_PAYMENT_DUPLICATE_ORDER_POLICY,
                ],
                'NOTE_PAYMENT_DUPLICATE_ORDER_POLICY' => [
                    'TYPE' => 'note',
                    'TEXT' => 'Если банк отвечает «Заказ с таким order_id уже существует» при Init. '
                        . '«Ошибка» — платёж в модуле переводится в failed. '
                        . '«Ожидает оплаты» — статус не меняется, пользователь видит сообщение об ошибке. '
                        . '«Привязать» — модуль запрашивает CheckOrder и подставляет PaymentId существующего платежа.',
                ],
                'TITLE_GATEWAY_TEST' => [
                    'TYPE' => 'title',
                    'TEXT' => 'Тестирование шлюза',
                ],
                'GATEWAY_TEST_AMOUNT' => [
                    'TITLE' => 'Сумма тестового платежа (руб.)',
                    'TYPE' => 'text',
                    'DEFAULT' => PaidAccessCore::DEFAULT_GATEWAY_TEST_AMOUNT,
                    'WIDTH' => 5,
                ],
                'NOTE_GATEWAY_TEST' => [
                    'TYPE' => 'note',
                    'TEXT' => 'Используется на вкладке «Тестирование» в карточке шлюза для проверки подключения эквайринга в T-Bank.',
                ],
            ],
        ];
    }

    /**
     * @return array{ID: string, TITLE: string, OPTIONS: array<string, mixed>}
     */
    private static function groupUserMessages(): array
    {
        return [
            'ID' => 'user_messages',
            'TITLE' => 'Тексты на сайте',
            'OPTIONS' => ModuleOptionsProvider::buildUserMessageOptions(),
        ];
    }

    /**
     * @return array{ID: string, TITLE: string, OPTIONS: array<string, mixed>}
     */
    private static function groupUserNotifications(): array
    {
        return [
            'ID' => 'notifications',
            'TITLE' => 'Email пользователям',
            'OPTIONS' => [
                'TITLE_USER_MAIL_BLOCK' => [
                    'TYPE' => 'title',
                    'TEXT' => 'Какие письма отправлять',
                ],
                'PAYMENT_EMAIL_NOTIFY' => [
                    'TITLE' => 'Подтверждение после успешной оплаты',
                    'TYPE' => 'checkbox',
                    'DEFAULT' => PaidAccessCore::DEFAULT_PAYMENT_EMAIL_NOTIFY,
                ],
                'MAIL_NOTIFY_PAYMENT_FAILED' => [
                    'TITLE' => 'Ошибка создания или обработки платежа',
                    'TYPE' => 'checkbox',
                    'DEFAULT' => PaidAccessCore::DEFAULT_MAIL_NOTIFY_PAYMENT_FAILED,
                ],
                'MAIL_NOTIFY_SUBSCRIPTION_DEBT' => [
                    'TITLE' => 'Просрочка оплаты (статус «долг»)',
                    'TYPE' => 'checkbox',
                    'DEFAULT' => PaidAccessCore::DEFAULT_MAIL_NOTIFY_SUBSCRIPTION_DEBT,
                ],
                'MAIL_NOTIFY_SUBSCRIPTION_EXPIRING' => [
                    'TITLE' => 'Напоминание об окончании оплаченного периода',
                    'TYPE' => 'checkbox',
                    'DEFAULT' => PaidAccessCore::DEFAULT_MAIL_NOTIFY_SUBSCRIPTION_EXPIRING,
                ],
                'MAIL_SUBSCRIPTION_EXPIRING_DAYS' => [
                    'TITLE' => 'За сколько дней до окончания периода напоминать',
                    'TYPE' => 'text',
                    'DEFAULT' => PaidAccessCore::DEFAULT_MAIL_SUBSCRIPTION_EXPIRING_DAYS,
                    'WIDTH' => 5,
                ],
                'TITLE_MAIL_TEMPLATES_BLOCK' => [
                    'TYPE' => 'title',
                    'TEXT' => 'Шаблоны писем',
                ],
                'NOTE_MAIL_TEMPLATES' => [
                    'TYPE' => 'note',
                    'TEXT' => 'Тексты писем редактируются в почтовых событиях Bitrix: '
                        . PaidAccessCore::MAIL_EVENT_PAYMENT_PAID . ', '
                        . PaidAccessCore::MAIL_EVENT_PAYMENT_FAILED . ', '
                        . PaidAccessCore::MAIL_EVENT_SUBSCRIPTION_DEBT . ', '
                        . PaidAccessCore::MAIL_EVENT_SUBSCRIPTION_EXPIRING . '. '
                        . 'Включение и отключение — переключателями выше.',
                ],
                'NOTE_RECEIPT_FISCAL' => [
                    'TYPE' => 'note',
                    'TEXT' => 'Фискальный чек (54-ФЗ) выбивает платёжный шлюз (T-Bank), если в настройках шлюза '
                        . 'включена передача чека и подключена онлайн-касса в личном кабинете банка. '
                        . 'Письмо с сайта — подтверждение оплаты подписки, не заменяет фискальный документ.',
                ],
            ],
        ];
    }

    /**
     * @return array{ID: string, TITLE: string, OPTIONS: array<string, mixed>}
     */
    private static function groupLogging(): array
    {
        return [
            'ID' => 'logging',
            'TITLE' => 'Журнал и админ',
            'OPTIONS' => [
                'TITLE_LOG_BLOCK' => [
                    'TYPE' => 'title',
                    'TEXT' => 'Файловое логирование',
                ],
                'LOGGING_ACTIVE' => [
                    'TITLE' => 'Логирование включено',
                    'TYPE' => 'checkbox',
                    'DEFAULT' => 'N',
                ],
                'LOG_LEVEL' => [
                    'TITLE' => 'Уровень логирования',
                    'TYPE' => 'selectbox',
                    'VALUES' => ['debug' => 'Debug', 'info' => 'Info', 'warning' => 'Warning', 'error' => 'Error'],
                    'DEFAULT' => 'error',
                ],
                'LOG_PATH' => [
                    'TITLE' => 'Путь к лог-файлу',
                    'TYPE' => 'text',
                    'DEFAULT' => PaidAccessCore::DEFAULT_LOG_PATH,
                ],
                'NOTE_LOG_PATH' => [
                    'TYPE' => 'note',
                    'TEXT' => 'Единый файл: события модуля, ошибки оплаты и HTTP-запросы к T-Bank (Init/GetQr/GetState). '
                        . 'Секреты и Token маскируются. При HTTP 403 на test API добавьте IP в whitelist через openapi@tbank.ru '
                        . 'или отключите тестовый режим шлюза.',
                ],
                'TITLE_ERROR_NOTIFY_BLOCK' => [
                    'TYPE' => 'title',
                    'TEXT' => 'Оповещение администратора',
                ],
                'ERROR_NOTIFY_ENABLED' => [
                    'TITLE' => 'Отправлять письма при ошибках модуля',
                    'TYPE' => 'checkbox',
                    'DEFAULT' => PaidAccessCore::DEFAULT_ERROR_NOTIFY_ENABLED,
                ],
                'ERROR_NOTIFY_EMAIL' => [
                    'TITLE' => 'Email администратора (через запятую)',
                    'TYPE' => 'text',
                    'DEFAULT' => PaidAccessCore::DEFAULT_ERROR_NOTIFY_EMAIL,
                    'WIDTH' => 40,
                ],
                'NOTE_ERROR_NOTIFY' => [
                    'TYPE' => 'note',
                    'TEXT' => 'Каждая уникальная ошибка отправляется на email один раз. Повторные срабатывания пишутся только в журнал. '
                        . '<a href="/bitrix/admin/zr_paidaccess_logs.php">Настройки → Платёжный доступ → Журнал</a>. '
                        . 'Почтовое событие: ' . PaidAccessCore::MAIL_EVENT_ADMIN_ERROR . '.',
                ],
            ],
        ];
    }
}

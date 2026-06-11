<?php

use Bitrix\Main\HttpApplication;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Zr\PaidAccess\Options\ModuleOptionsProvider;
use Zr\PaidAccess\PaidAccessCore;

$module_id = 'zr.paidaccess';
$prefix = 'ZR_PAIDACCESS_';

global $APPLICATION;
if ($APPLICATION->GetGroupRight($module_id) == 'D') {
    $APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED'));
}

Loc::loadMessages(__FILE__);

Loader::includeModule($module_id);

$siteLid = SITE_ID == 'ru' ? 's1' : SITE_ID;
$allOptions = \Bitrix\Main\Config\Option::getForModule($module_id);

$request = HttpApplication::getInstance()->getContext()->getRequest();

$arStructureOptions = [
    '_BASE_SETTINGS' => [
        'ID' => 'base',
        'TITLE' => 'Настройки модуля',
        'OPTIONS' => [
            'TITLE_MODULE_ACTIVE_BLOCK' => [
                "TYPE" => "title",
                'TEXT' => "Настройки работы модуля"
            ],
            'MODULE_ACTIVE' => [
                'TITLE' => 'Модуль активен',
                'TYPE' => 'checkbox',
                'DEFAULT' => 'Y',
            ],
        ],
    ],
    '_PAYMENT_SETTINGS' => [
        'ID' => 'payment',
        'TITLE' => 'Оплата подписки',
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
            'PAYMENT_WIDGET_MODE' => [
                'TITLE' => 'Способ оплаты на сайте',
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
            'PAYMENT_PAGE_ERROR_TEXT' => [
                'TITLE' => 'Текст ошибки на странице оплаты (для пользователя)',
                'TYPE' => 'textarea',
                'DEFAULT' => PaidAccessCore::DEFAULT_PAYMENT_PAGE_ERROR_TEXT,
                'ROWS' => 10,
                'COLS' => 120
            ],
            'NOTE_PAYMENT_PAGE_ERROR' => [
                'TYPE' => 'note',
                'TEXT' => 'Показывается при сбое создания платежа или получения QR. Технические детали пишутся только в журнал модуля.',
            ],
            'TITLE_RECEIPT_BLOCK' => [
                'TYPE' => 'title',
                'TEXT' => 'Уведомления и чеки',
            ],
            'PAYMENT_EMAIL_NOTIFY' => [
                'TITLE' => 'Отправлять информационное письмо после оплаты',
                'TYPE' => 'checkbox',
                'DEFAULT' => PaidAccessCore::DEFAULT_PAYMENT_EMAIL_NOTIFY,
            ],
            'MAIL_NOTIFY_PAYMENT_FAILED' => [
                'TITLE' => 'Письмо при ошибке создания/обработки платежа',
                'TYPE' => 'checkbox',
                'DEFAULT' => PaidAccessCore::DEFAULT_MAIL_NOTIFY_PAYMENT_FAILED,
            ],
            'MAIL_NOTIFY_SUBSCRIPTION_DEBT' => [
                'TITLE' => 'Письмо при просрочке оплаты (статус «долг»)',
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
            'NOTE_RECEIPT_FISCAL' => [
                'TYPE' => 'note',
                'TEXT' => 'Фискальный чек (54-ФЗ) выбивает платёжный шлюз (T-Bank), если в настройках шлюза '
                    . 'включена передача чека и подключена онлайн-касса в личном кабинете банка. '
                    . 'Письмо с сайта — подтверждение оплаты подписки, не заменяет фискальный документ. '
                    . 'Шаблон письма: почтовое событие ' . PaidAccessCore::MAIL_EVENT_PAYMENT_PAID . '.',
            ],
            'GATEWAY_TEST_AMOUNT' => [
                'TITLE' => 'Сумма тестового платежа шлюза (руб.)',
                'TYPE' => 'text',
                'DEFAULT' => PaidAccessCore::DEFAULT_GATEWAY_TEST_AMOUNT,
                'WIDTH' => 5,
            ],
            'NOTE_GATEWAY_TEST' => [
                'TYPE' => 'note',
                'TEXT' => 'Используется на вкладке «Тестирование» тестового шлюза для проверки подключения эквайринга в T-Bank.',
            ],
        ],
    ],
    '_BILLING_SETTINGS' => [
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
    ],
    '_ACCESS_SETTINGS' => [
        'ID' => 'access',
        'TITLE' => 'Настройка доступов',
        'OPTIONS' => [
            'TITLE_ACCESS_BLOCK' => [
                'TYPE' => 'title',
                'TEXT' => 'Блокировка сайта при неоплаченной подписке',
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
            'NOTE_ACCESS_TEMPLATES' => [
                'TYPE' => 'note',
                'TEXT' => 'Шаблоны: /local/php_interface/zr.paidaccess/template_*.php',
            ],
        ],
    ],
    '_LOGS_SETTINGS' => [
        'ID' => 'logs',
        'TITLE' => 'Настройки логирования',
        'OPTIONS' => [
            'TITLE_LOG_BLOCK' => [
                "TYPE" => "title",
                'TEXT' => "Логирование"
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
                'TEXT' => 'Уведомления об ошибках',
            ],
            'ERROR_NOTIFY_ENABLED' => [
                'TITLE' => 'Отправлять письма администратору при ошибках',
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
    ]
];

$aTabs = [];
$rsSites = CSite::GetList($by = 'sort', $order = 'asc', ['ACTIVE' => 'Y']);
while ($arSite = $rsSites->Fetch()) {
    foreach ($arStructureOptions as $topCodeSetting => $arTopSettings) {
        $arOptions = [];
        if (!empty($arTopSettings['OPTIONS']) && is_array($arTopSettings['OPTIONS']) && count($arTopSettings['OPTIONS']) > 0) {
            foreach ($arTopSettings['OPTIONS'] as $codeOption => $arOption) {
                $option = [
                    !empty($codeOption) ? $codeOption .'_'. $arSite['LID'] : '',
                    $arOption['TITLE'],
                    $arOption['DEFAULT'] ?: ''
                ];

                $haveError = false;
                switch ($arOption['TYPE']) {
                    case "multiselectbox":
                        $option[] = [
                            'multiselectbox',
                            $arOption['VALUES'],
                        ];
                        break;
                    case "textarea":
                        $option[] = [
                            'textarea',
                            $arOption['ROWS'],
                            $arOption['COLS'],
                        ];
                        break;
                    case "statictext":
                        break;
                    case "statichtml":
                        $htmlValue = $arOption['VALUE'];
                        if (!empty($arOption['GATEWAY'])) {
                            $htmlValue = '<div class="zr-paidaccess-gateway-field" data-zr-gateway="'
                                . htmlspecialcharsbx($arOption['GATEWAY']) . '" data-zr-field="'
                                . htmlspecialcharsbx($codeOption) . '">' . $htmlValue . '</div>';
                        }
                        $option[2] = $htmlValue;
                        $option[3] = ['statichtml'];
                        break;
                    case "checkbox":
                        $option[] = [
                            'checkbox'
                        ];
                        break;
                    case "text":
                        $option[] = ['text', $arOption['WIDTH'] ?: 30];
                        break;
                    case "password":
                        break;
                    case "selectbox":
                        $option[] = ["selectbox", $arOption['VALUES']];
                        break;
                    case "file":
                        $option[] = ["file"];
                        break;
                    case "note":
                        $option = ["note" => $arOption['TEXT']];
                        break;
                    case "title":
                        if (!empty($arOption['GATEWAY'])) {
                            $option = '<span class="zr-paidaccess-gateway-field" data-zr-gateway="'
                                . htmlspecialcharsbx($arOption['GATEWAY']) . '" data-zr-field="'
                                . htmlspecialcharsbx($codeOption) . '">' . $arOption['TEXT'] . '</span>';
                        } else {
                            $option = $arOption['TEXT'];
                        }
                        break;
                    default:
                        $haveError = true;
                        break;
                }

                if ($haveError) {
                    continue;
                }
                $arOptions[] = $option;
            }
        }

        $aTabs[] =
        [
            'DIV' => $arTopSettings['ID'] . "_" . $arSite['LID'],
            'TAB' => $arTopSettings['TITLE'].' ('.$arSite['LID'].')',
            'OPTIONS' => $arOptions
        ];
    }
}

// save settings
if ($request->isPost() && $request['Update'] && check_bitrix_sessid()) {
    foreach ($aTabs as $aTab) {
        foreach ($aTab['OPTIONS'] as $arOption) {
            if (!is_array($arOption)) {
                continue;
            }
            if ($arOption['note']) {
                continue;
            }
            __AdmSettingsSaveOption($module_id, $arOption);
        }
    }
}

// Show form
$tabControl = new CAdminTabControl('tabControl', $aTabs);
?>

<?$tabControl->Begin();?>
<form method="POST" action="<?=$APPLICATION->GetCurPage()?>?mid=<?=htmlspecialcharsbx($request['mid'])?>&lang=<?=$request['lang']?>" name="zr_reviewhl_settings">
    <?=bitrix_sessid_post()?>
    <?foreach ($aTabs as $aTab) {
        if ($aTab['OPTIONS']) {
            $tabControl->BeginNextTab();
            __AdmSettingsDrawList($module_id, $aTab['OPTIONS']);
        }
    }?>
    <?php $tabControl->Buttons(); ?>
    <input type="submit" name="Update" value="<?=Loc::getMessage('MAIN_SAVE')?>">
    <input type="reset" name="reset" value="<?=Loc::getMessage('MAIN_RESET')?>">
</form>
<?$tabControl->End();?>

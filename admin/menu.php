<?php

global $APPLICATION;

if ($APPLICATION->GetGroupRight('zr.paidaccess') <= 'D') {
    return false;
}

return [
    [
        'parent_menu' => 'global_menu_services',
        'section' => 'zr_paidaccess',
        'sort' => 1850,
        'text' => GetMessage('ZR_PAIDACCESS_MENU_ROOT'),
        'title' => GetMessage('ZR_PAIDACCESS_MENU_ROOT'),
        'icon' => 'zr_paidaccess_menu_icon',
        'page_icon' => 'zr_paidaccess_menu_icon',
        'items_id' => 'menu_zr_paidaccess',
        'items' => [
            [
                'text' => GetMessage('ZR_PAIDACCESS_MENU_SUBSCRIBERS'),
                'title' => GetMessage('ZR_PAIDACCESS_MENU_SUBSCRIBERS'),
                'url' => 'zr_paidaccess_subscribers.php?lang=' . LANGUAGE_ID,
            ],
            [
                'text' => GetMessage('ZR_PAIDACCESS_MENU_PAYMENTS'),
                'title' => GetMessage('ZR_PAIDACCESS_MENU_PAYMENTS'),
                'url' => 'zr_paidaccess_payments.php?lang=' . LANGUAGE_ID,
                'more_url' => [
                    'zr_paidaccess_payment_edit.php',
                ],
            ],
            [
                'text' => GetMessage('ZR_PAIDACCESS_MENU_FUNDS'),
                'title' => GetMessage('ZR_PAIDACCESS_MENU_FUNDS'),
                'url' => 'zr_paidaccess_funds.php?lang=' . LANGUAGE_ID,
                'more_url' => [
                    'zr_paidaccess_fund_edit.php',
                    'zr_paidaccess_fund_movement_edit.php',
                    'zr_paidaccess_fund_expense_view.php',
                ],
            ],
            [
                'text' => GetMessage('ZR_PAIDACCESS_MENU_GATEWAYS'),
                'title' => GetMessage('ZR_PAIDACCESS_MENU_GATEWAYS'),
                'url' => 'zr_paidaccess_gateways.php?lang=' . LANGUAGE_ID,
                'more_url' => [
                    'zr_paidaccess_gateway_edit.php',
                    'zr_paidaccess_gateway_import.php',
                ],
            ],
            [
                'text' => GetMessage('ZR_PAIDACCESS_MENU_OPTIONS'),
                'title' => GetMessage('ZR_PAIDACCESS_MENU_OPTIONS'),
                'url' => 'settings.php?mid=zr.paidaccess&lang=' . LANGUAGE_ID,
            ],
            [
                'text' => GetMessage('ZR_PAIDACCESS_MENU_LOGS'),
                'title' => GetMessage('ZR_PAIDACCESS_MENU_LOGS'),
                'url' => 'zr_paidaccess_logs.php?lang=' . LANGUAGE_ID,
            ],
        ],
    ],
];

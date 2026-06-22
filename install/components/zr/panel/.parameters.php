<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$arComponentParameters = [
    'GROUPS' => [],
    'PARAMETERS' => [
        'BASE_PATH' => [
            'PARENT' => 'BASE',
            'NAME' => GetMessage('ZR_PAIDACCESS_PANEL_PARAM_BASE_PATH'),
            'TYPE' => 'STRING',
            'DEFAULT' => '/panel/',
        ],
        'CONTENT_GROUP_ID' => [
            'PARENT' => 'BASE',
            'NAME' => GetMessage('ZR_PAIDACCESS_PANEL_PARAM_CONTENT_GROUP'),
            'TYPE' => 'STRING',
            'DEFAULT' => '',
        ],
        'PAGE_SIZE' => [
            'PARENT' => 'BASE',
            'NAME' => GetMessage('ZR_PAIDACCESS_PANEL_PARAM_PAGE_SIZE'),
            'TYPE' => 'STRING',
            'DEFAULT' => '20',
        ],
        'SHOW_TOTAL_AMOUNT' => [
            'PARENT' => 'BASE',
            'NAME' => GetMessage('ZR_PAIDACCESS_PANEL_PARAM_SHOW_TOTAL'),
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y',
        ],
        'CACHE_TIME' => ['DEFAULT' => 0],
    ],
];

<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$arComponentParameters = [
    'GROUPS' => [],
    'PARAMETERS' => [
        'SITE_ID' => [
            'PARENT' => 'BASE',
            'NAME' => GetMessage('ZR_PAIDACCESS_DOCUMENT_LIST_PARAM_SITE_ID'),
            'TYPE' => 'STRING',
            'DEFAULT' => '',
        ],
        'ONLY_REQUIRED' => [
            'PARENT' => 'BASE',
            'NAME' => GetMessage('ZR_PAIDACCESS_DOCUMENT_LIST_PARAM_ONLY_REQUIRED'),
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'N',
        ],
        'CODE' => [
            'PARENT' => 'BASE',
            'NAME' => GetMessage('ZR_PAIDACCESS_DOCUMENT_LIST_PARAM_CODE'),
            'TYPE' => 'STRING',
            'DEFAULT' => '',
        ],
        'DETAIL_URL' => [
            'PARENT' => 'BASE',
            'NAME' => GetMessage('ZR_PAIDACCESS_DOCUMENT_LIST_PARAM_DETAIL_URL'),
            'TYPE' => 'STRING',
            'DEFAULT' => '',
        ],
        'SHOW_HEADER' => [
            'PARENT' => 'VISUAL',
            'NAME' => GetMessage('ZR_PAIDACCESS_DOCUMENT_LIST_PARAM_SHOW_HEADER'),
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y',
        ],
        'HEADER_TITLE' => [
            'PARENT' => 'VISUAL',
            'NAME' => GetMessage('ZR_PAIDACCESS_DOCUMENT_LIST_PARAM_HEADER_TITLE'),
            'TYPE' => 'STRING',
            'DEFAULT' => GetMessage('ZR_PAIDACCESS_DOCUMENT_LIST_PARAM_HEADER_TITLE_DEFAULT'),
        ],
        'CACHE_TIME' => ['DEFAULT' => 3600],
    ],
];

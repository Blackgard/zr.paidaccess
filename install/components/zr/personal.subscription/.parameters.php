<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$arComponentParameters = [
    'GROUPS' => [],
    'PARAMETERS' => [
        'AUTO_PREPARE_PAYMENT' => [
            'PARENT' => 'BASE',
            'NAME' => GetMessage('ZR_PAIDACCESS_PERSONAL_AUTO_PREPARE'),
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'N',
        ],
        'CACHE_TIME' => ['DEFAULT' => 0],
    ],
];

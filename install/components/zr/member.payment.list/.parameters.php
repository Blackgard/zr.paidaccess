<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$arComponentParameters = [
    'GROUPS' => [],
    'PARAMETERS' => [
        'SHOW_TOTAL_AMOUNT' => [
            'PARENT' => 'BASE',
            'NAME' => GetMessage('ZR_PAIDACCESS_MEMBERS_SHOW_TOTAL'),
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'N',
        ],
        'CACHE_TIME' => ['DEFAULT' => 0],
    ],
];

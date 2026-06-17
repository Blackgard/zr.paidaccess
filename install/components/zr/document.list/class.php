<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;
use Zr\PaidAccess\PublicUi\DocumentListViewService;

class ZrDocumentListComponent extends CBitrixComponent
{
    public function executeComponent(): void
    {
        if (!Loader::includeModule('zr.paidaccess')) {
            $this->arResult['ERROR'] = GetMessage('ZR_PAIDACCESS_DOCUMENT_LIST_MODULE_ERROR');
            $this->includeComponentTemplate();

            return;
        }

        $this->arResult = DocumentListViewService::buildViewModel($this->arParams);

        $this->includeComponentTemplate();
    }
}

<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\PublicUi\MemberListService;

class ZrMemberPaymentListComponent extends CBitrixComponent
{
    public function onPrepareComponentParams($arParams): array
    {
        $arParams['SHOW_TOTAL_AMOUNT'] = (($arParams['SHOW_TOTAL_AMOUNT'] ?? 'N') === 'Y') ? 'Y' : 'N';

        return $arParams;
    }

    public function executeComponent(): void
    {
        global $USER;

        if (!Loader::includeModule('zr.paidaccess')) {
            $this->arResult['ERROR'] = GetMessage('ZR_PAIDACCESS_MEMBERS_MODULE_ERROR');
            $this->includeComponentTemplate();

            return;
        }

        if (!is_object($USER) || !$USER->IsAuthorized()) {
            $this->arResult['ERROR'] = GetMessage('ZR_PAIDACCESS_MEMBERS_AUTH_REQUIRED');
            $this->includeComponentTemplate();

            return;
        }

        $siteId = PaidAccessCore::normalizeSiteId(defined('SITE_ID') ? SITE_ID : null);
        $showTotal = $this->arParams['SHOW_TOTAL_AMOUNT'] === 'Y';

        $this->arResult['ITEMS'] = MemberListService::getMembers($showTotal, $siteId);
        $this->arResult['SHOW_TOTAL_AMOUNT'] = $showTotal ? 'Y' : 'N';

        $this->includeComponentTemplate();
    }
}

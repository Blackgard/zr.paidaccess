<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\PublicUi\PersonalSubscriptionViewService;

class ZrPersonalSubscriptionComponent extends CBitrixComponent
{
    public function onPrepareComponentParams($arParams): array
    {
        $arParams['AUTO_PREPARE_PAYMENT'] = (($arParams['AUTO_PREPARE_PAYMENT'] ?? 'N') === 'Y') ? 'Y' : 'N';

        return $arParams;
    }

    public function executeComponent(): void
    {
        global $USER;

        if (!Loader::includeModule('zr.paidaccess')) {
            $this->arResult['ERROR'] = GetMessage('ZR_PAIDACCESS_PERSONAL_MODULE_ERROR');
            $this->includeComponentTemplate();

            return;
        }

        if (!is_object($USER) || !$USER->IsAuthorized()) {
            $this->arResult['ERROR'] = GetMessage('ZR_PAIDACCESS_PERSONAL_AUTH_REQUIRED');
            $this->includeComponentTemplate();

            return;
        }

        $userId = (int)$USER->GetID();
        $siteId = PaidAccessCore::normalizeSiteId(defined('SITE_ID') ? SITE_ID : null);

        $this->arResult = PersonalSubscriptionViewService::buildViewModel($userId, $siteId);
        $this->arResult['PAYMENT_ERROR'] = '';
        $this->arResult['PAY_PREPARE_URL'] = $this->getPayPrepareUrl();

        $shouldPrepare = $this->arResult['SHOW_PAYMENT_BLOCK']
            && (int)($this->arResult['MODULE_PAYMENT_ID'] ?? 0) <= 0
            && !empty($this->arResult['CAN_INIT_PAYMENT']);

        $payRequested = $this->request->get('pay') === 'Y' && check_bitrix_sessid();
        $autoPrepare = $this->arParams['AUTO_PREPARE_PAYMENT'] === 'Y';

        if ($shouldPrepare && ($payRequested || $autoPrepare)) {
            try {
                $this->arResult['MODULE_PAYMENT_ID'] = PersonalSubscriptionViewService::preparePaymentForUser($userId, $siteId);
            } catch (\Throwable $e) {
                $this->arResult['PAYMENT_ERROR'] = PaidAccessCore::getPaymentPageErrorText($siteId);
            }
        }

        $this->includeComponentTemplate();
    }

    protected function getPayPrepareUrl(): string
    {
        global $APPLICATION;

        $query = [
            'pay' => 'Y',
            'sessid' => bitrix_sessid(),
        ];

        return $APPLICATION->GetCurPageParam(http_build_query($query), ['pay', 'sessid']);
    }
}

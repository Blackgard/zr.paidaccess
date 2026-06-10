<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;
use Zr\PaidAccess\PublicUi\FundWalletService;

class ZrFundWalletComponent extends CBitrixComponent
{
    public function executeComponent(): void
    {
        if (!Loader::includeModule('zr.paidaccess')) {
            $this->arResult['ERROR'] = GetMessage('ZR_PAIDACCESS_FUND_WALLET_MODULE_ERROR');
            $this->includeComponentTemplate();

            return;
        }

        $wallet = FundWalletService::getWalletData();

        $this->arResult['TOTAL_AMOUNT'] = (int)($wallet['TOTAL_AMOUNT'] ?? 0);
        $this->arResult['PAYER_COUNT'] = (int)($wallet['PAYER_COUNT'] ?? 0);
        $this->arResult['ITEMS'] = $wallet['ITEMS'] ?? [];

        $this->includeComponentTemplate();
    }
}

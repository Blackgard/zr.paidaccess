<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\PublicUi\DocumentConsentViewService;

class ZrDocumentConsentComponent extends CBitrixComponent
{
    public function executeComponent(): void
    {
        global $USER;

        if (!Loader::includeModule('zr.paidaccess')) {
            $this->arResult['ERROR'] = GetMessage('ZR_PAIDACCESS_DOCUMENT_CONSENT_MODULE_ERROR');
            $this->includeComponentTemplate();

            return;
        }

        if (!is_object($USER) || !$USER->IsAuthorized()) {
            $this->arResult['ERROR'] = GetMessage('ZR_PAIDACCESS_DOCUMENT_CONSENT_AUTH_REQUIRED');
            $this->includeComponentTemplate();

            return;
        }

        $userId = (int)$USER->GetID();
        $siteId = PaidAccessCore::normalizeSiteId(defined('SITE_ID') ? SITE_ID : null);

        if ($this->request->isPost() && check_bitrix_sessid()) {
            try {
                $versionIds = $this->request->getPost('version_ids');
                if (!is_array($versionIds)) {
                    $versionIds = [];
                }
                DocumentConsentViewService::acceptFromRequest($userId, $versionIds, $siteId);
                $this->arResult['SUCCESS'] = true;
            } catch (\Throwable $e) {
                $this->arResult['ERROR'] = $e->getMessage();
            }
        }

        $this->arResult = array_merge(
            DocumentConsentViewService::buildViewModel($userId, $siteId),
            $this->arResult
        );

        $this->includeComponentTemplate();
    }
}

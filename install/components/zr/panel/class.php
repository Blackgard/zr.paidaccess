<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Zr\PaidAccess\Document\RequiredDocumentService;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\PublicUi\MemberListService;
use Zr\PaidAccess\PublicUi\ModeratorPaymentEditService;
use Zr\PaidAccess\PublicUi\ModeratorPaymentListService;
use Zr\PaidAccess\PublicUi\PanelSectionRegistry;

class ZrPanelComponent extends CBitrixComponent
{
    public function onPrepareComponentParams($arParams): array
    {
        $arParams['BASE_PATH'] = trim((string)($arParams['BASE_PATH'] ?? '/panel/'));
        if ($arParams['BASE_PATH'] === '') {
            $arParams['BASE_PATH'] = '/panel/';
        }
        $arParams['CONTENT_GROUP_ID'] = (int)($arParams['CONTENT_GROUP_ID'] ?? 0);
        $arParams['PAGE_SIZE'] = max(1, min(100, (int)($arParams['PAGE_SIZE'] ?? 20)));
        $arParams['SHOW_TOTAL_AMOUNT'] = (($arParams['SHOW_TOTAL_AMOUNT'] ?? 'Y') === 'Y') ? 'Y' : 'N';

        return $arParams;
    }

    public function executeComponent(): void
    {
        global $USER, $APPLICATION;

        if (!Loader::includeModule('zr.paidaccess')) {
            $this->arResult['ERROR'] = GetMessage('ZR_PAIDACCESS_PANEL_MODULE_ERROR');
            $this->includeComponentTemplate();

            return;
        }

        if (!$this->canAccess($USER)) {
            $this->arResult['ACCESS_DENIED'] = true;
            $this->arResult['ERROR'] = GetMessage('ZR_PAIDACCESS_PANEL_ACCESS_DENIED');
            $this->includeComponentTemplate();

            return;
        }

        $request = Context::getCurrent()->getRequest();
        $requestData = array_merge($_GET, $_POST);
        $page = $this->resolvePage($request->get('page'));
        $basePath = (string)$this->arParams['BASE_PATH'];

        if ($page === 'payment'
            && $request->isPost()
            && check_bitrix_sessid()
            && $request->getPost('save') !== null
        ) {
            $paymentId = $this->resolvePaymentId($requestData);

            try {
                ModeratorPaymentEditService::saveStatus($paymentId, (string)$request->getPost('STATUS'));
                LocalRedirect(PanelSectionRegistry::buildPanelUrl($basePath, 'payments'));
            } catch (\Throwable $e) {
                $this->arResult['SAVE_ERRORS'] = [$e->getMessage()];
            }
        }

        $section = PanelSectionRegistry::getSection($page);
        $pageMeta = $section ?? PanelSectionRegistry::getSection('index');
        $APPLICATION->SetTitle($pageMeta['title'] ?? 'Панель');

        $this->arResult['PAGE'] = $page;
        $this->arResult['PAGE_META'] = $pageMeta;
        $this->arResult['MENU'] = PanelSectionRegistry::getMenuSections();
        $this->arResult['BASE_PATH'] = $basePath;
        $this->arResult['URLS'] = $this->buildUrls($basePath);
        $this->arResult['CONTENT'] = $this->buildContent($page, $requestData, $basePath);

        if (!empty($this->arResult['SAVE_ERRORS'])) {
            $this->arResult['CONTENT']['ERRORS'] = array_merge(
                $this->arResult['CONTENT']['ERRORS'] ?? [],
                $this->arResult['SAVE_ERRORS']
            );
        }

        $this->includeComponentTemplate();
    }

    /**
     * @param mixed $user
     */
    private function canAccess($user): bool
    {
        if (!is_object($user) || !method_exists($user, 'IsAuthorized') || !$user->IsAuthorized()) {
            return false;
        }

        if (method_exists($user, 'IsAdmin') && $user->IsAdmin()) {
            return true;
        }

        $groupId = (int)$this->arParams['CONTENT_GROUP_ID'];
        if ($groupId <= 0 && isset($GLOBALS['CONTENT_GROUP_ID'])) {
            $groupId = (int)$GLOBALS['CONTENT_GROUP_ID'];
        }

        if ($groupId <= 0 || !method_exists($user, 'GetUserGroupArray')) {
            return false;
        }

        return in_array($groupId, $user->GetUserGroupArray(), true);
    }

    /**
     * @param mixed $page
     */
    private function resolvePage($page): string
    {
        $page = trim((string)$page);
        if ($page === '' || $page === 'index') {
            return 'index';
        }

        return PanelSectionRegistry::isValidPage($page) ? $page : 'index';
    }

    /**
     * @param array<string, mixed> $request
     */
    private function resolvePaymentId(array $request): int
    {
        return max(0, (int)($request['CODE'] ?? $request['ID'] ?? 0));
    }

    /**
     * @return array<string, string>
     */
    private function buildUrls(string $basePath): array
    {
        $urls = [];
        foreach (PanelSectionRegistry::getMenuSections() as $code => $section) {
            $urls[$code] = PanelSectionRegistry::buildPanelUrl($basePath, $code);
        }

        $urls['payment'] = PanelSectionRegistry::buildPanelUrl($basePath, 'payment');

        return $urls;
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    private function buildContent(string $page, array $request, string $basePath): array
    {
        $siteId = PaidAccessCore::normalizeSiteId($this->arParams['SITE_ID'] ?? null);
        $section = PanelSectionRegistry::getSection($page);

        if ($section === null) {
            return ['SECTIONS' => PanelSectionRegistry::getIndexSections($basePath)];
        }

        $type = (string)($section['type'] ?? PanelSectionRegistry::TYPE_HUB);

        switch ($page) {
            case 'payments':
                return ModeratorPaymentListService::buildViewModel([
                    'PAGE_SIZE' => (int)$this->arParams['PAGE_SIZE'],
                    'EDIT_URL' => PanelSectionRegistry::buildPanelUrl($basePath, 'payment'),
                    'LIST_URL' => PanelSectionRegistry::buildPanelUrl($basePath, 'payments'),
                ], $request);

            case 'payment':
                $paymentId = $this->resolvePaymentId($request);

                try {
                    $content = ModeratorPaymentEditService::buildViewModel($paymentId);
                } catch (\Throwable $e) {
                    return ['ERROR' => $e->getMessage()];
                }
                $content['LIST_URL'] = PanelSectionRegistry::buildPanelUrl($basePath, 'payments');
                $content['FORM_ACTION'] = PanelSectionRegistry::buildPanelUrl($basePath, 'payment', ['CODE' => $paymentId]);

                return $content;

            case 'documents':
                global $APPLICATION;

                return [
                    'ITEMS' => RequiredDocumentService::getModeratorList($siteId),
                    'CAN_EDIT' => is_object($APPLICATION)
                        && $APPLICATION->GetGroupRight('zr.paidaccess') >= 'W',
                    'LEGACY_ADD_URL' => PanelSectionRegistry::legacyUrl('add_docs'),
                ];

            case 'members':
                $showTotal = $this->arParams['SHOW_TOTAL_AMOUNT'] === 'Y';

                return [
                    'ITEMS' => MemberListService::getMembers($showTotal, $siteId),
                    'SHOW_TOTAL_AMOUNT' => $showTotal ? 'Y' : 'N',
                ];

            case 'index':
                return [
                    'SECTIONS' => PanelSectionRegistry::getIndexSections($basePath),
                ];

            default:
                if ($type === PanelSectionRegistry::TYPE_NATIVE) {
                    return PanelSectionRegistry::buildHubContent($section, $basePath);
                }

                return PanelSectionRegistry::buildHubContent($section, $basePath);
        }
    }
}

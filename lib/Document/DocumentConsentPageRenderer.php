<?php

namespace Zr\PaidAccess\Document;

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Zr\PaidAccess\PaidAccessCore;

class DocumentConsentPageRenderer
{
    public static function render(): void
    {
        global $USER;

        $errorMessage = '';
        $successRedirect = false;
        $siteId = PaidAccessCore::normalizeSiteId();
        $userId = is_object($USER) && $USER->IsAuthorized() ? (int)$USER->GetID() : 0;

        if (!Loader::includeModule(PaidAccessCore::MODULE_ID) || $userId <= 0) {
            $errorMessage = 'Требуется авторизация.';
            $pendingDocuments = [];
        } else {
            $request = Context::getCurrent()->getRequest();
            if ($request->isPost() && check_bitrix_sessid()) {
                try {
                    $versionIds = $request->getPost('version_ids');
                    if (!is_array($versionIds)) {
                        $versionIds = [];
                    }
                    DocumentConsentService::acceptDocuments($userId, $versionIds, $siteId);
                    $successRedirect = true;
                } catch (\Throwable $e) {
                    $errorMessage = $e->getMessage();
                }
            }

            if ($successRedirect) {
                $backUrl = (string)$request->getPost('back_url');
                if ($backUrl === '' || !self::isSafeRedirectUrl($backUrl)) {
                    $backUrl = '/';
                }
                LocalRedirect($backUrl);
            }

            $pendingDocuments = DocumentConsentService::getPendingDocuments($userId, $siteId);
        }

        $backUrl = self::resolveBackUrl();

        include __DIR__ . '/views/document_consent_page.php';
    }

    protected static function resolveBackUrl(): string
    {
        $request = Context::getCurrent()->getRequest();
        $uri = (string)$request->getRequestUri();
        if ($uri !== '' && self::isSafeRedirectUrl($uri)) {
            return $uri;
        }

        return '/';
    }

    protected static function isSafeRedirectUrl(string $url): bool
    {
        if ($url === '' || self::startsWith($url, '//')) {
            return false;
        }

        if (self::startsWith($url, '/')) {
            return !self::startsWith($url, '/bitrix/admin');
        }

        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '') {
            return false;
        }

        return self::startsWith($url, 'http://' . $host) || self::startsWith($url, 'https://' . $host);
    }

    private static function startsWith(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

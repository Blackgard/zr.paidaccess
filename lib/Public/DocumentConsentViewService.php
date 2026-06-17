<?php

namespace Zr\PaidAccess\PublicUi;

use Zr\PaidAccess\Document\DocumentConsentService;
use Zr\PaidAccess\PaidAccessCore;

class DocumentConsentViewService
{
    /**
     * @return array<string, mixed>
     */
    public static function buildViewModel(int $userId, ?string $siteId = null): array
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        $userId = (int)$userId;

        return [
            'USER_ID' => $userId,
            'SITE_ID' => $siteId,
            'PENDING_DOCUMENTS' => DocumentConsentService::getPendingDocuments($userId, $siteId),
            'HAS_PENDING' => DocumentConsentService::hasPendingDocuments($userId, $siteId),
        ];
    }

    public static function acceptFromRequest(int $userId, array $versionIds, ?string $siteId = null): void
    {
        DocumentConsentService::acceptDocuments($userId, $versionIds, $siteId);
    }
}

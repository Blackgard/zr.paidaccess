<?php

namespace Zr\PaidAccess\Access;

use Zr\PaidAccess\Document\DocumentConsentService;
use Zr\PaidAccess\PaidAccessCore;

class DocumentConsentControl
{
    public static function mustShowConsentPage(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        if (!PaidAccessCore::isModuleActive()) {
            return false;
        }

        if (!PaidAccessCore::isDocumentConsentEnabled()) {
            return false;
        }

        if (AccessControl::isAdminUser($userId)) {
            return false;
        }

        if (!AccessControl::isUserInRestrictedGroups($userId)) {
            return false;
        }

        return DocumentConsentService::hasPendingDocuments(
            $userId,
            PaidAccessCore::normalizeSiteId()
        );
    }
}

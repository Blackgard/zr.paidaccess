<?php

namespace Zr\PaidAccess\Document;

use Zr\PaidAccess\PaidAccessCore;

class DocumentConsentService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getPendingDocuments(int $userId, ?string $siteId = null): array
    {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return [];
        }

        $required = RequiredDocumentRepository::getRequiredWithCurrentVersion($siteId);
        if ($required === []) {
            return [];
        }

        $versionIds = [];
        foreach ($required as $item) {
            $versionIds[] = (int)($item['CURRENT_VERSION']['ID'] ?? 0);
        }

        $acceptedIds = DocumentAcceptanceRepository::getAcceptedVersionIds($userId, $versionIds);

        $pending = [];
        foreach ($required as $item) {
            $version = $item['CURRENT_VERSION'] ?? null;
            if (!is_array($version)) {
                continue;
            }

            $versionId = (int)$version['ID'];
            if (self::isVersionAccepted($versionId, $acceptedIds)) {
                continue;
            }

            $pending[] = self::buildPendingItem($item, $version);
        }

        return $pending;
    }

    public static function hasPendingDocuments(int $userId, ?string $siteId = null): bool
    {
        return self::getPendingDocuments($userId, $siteId) !== [];
    }

    /**
     * @param int[] $versionIds
     */
    public static function acceptDocuments(int $userId, array $versionIds, ?string $siteId = null): void
    {
        $userId = (int)$userId;
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Требуется авторизация');
        }

        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        $pending = self::getPendingDocuments($userId, $siteId);
        if ($pending === []) {
            return;
        }

        $pendingByVersionId = [];
        foreach ($pending as $item) {
            $pendingByVersionId[(int)$item['VERSION_ID']] = $item;
        }

        $versionIds = array_values(array_unique(array_filter(array_map('intval', $versionIds))));
        if ($versionIds === []) {
            throw new \InvalidArgumentException('Отметьте все обязательные документы');
        }

        foreach ($pendingByVersionId as $versionId => $item) {
            if (!in_array($versionId, $versionIds, true)) {
                throw new \InvalidArgumentException('Необходимо подтвердить все обязательные документы');
            }
        }

        foreach ($versionIds as $versionId) {
            if (!isset($pendingByVersionId[$versionId])) {
                throw new \InvalidArgumentException('Некорректный идентификатор версии документа');
            }

            $item = $pendingByVersionId[$versionId];
            DocumentAcceptanceRepository::record(
                $userId,
                (int)$item['DOCUMENT_ID'],
                $versionId,
                $siteId
            );
        }
    }

    /**
     * @param int[] $acceptedVersionIds
     */
    public static function isVersionAccepted(int $versionId, array $acceptedVersionIds): bool
    {
        return in_array($versionId, $acceptedVersionIds, true);
    }

    /**
     * @param array<string, mixed> $document
     * @param array<string, mixed> $version
     * @return array<string, mixed>
     */
    public static function buildPendingItem(array $document, array $version): array
    {
        return [
            'DOCUMENT_ID' => (int)$document['ID'],
            'VERSION_ID' => (int)$version['ID'],
            'VERSION' => (string)$version['VERSION'],
            'TITLE' => (string)$document['TITLE'],
            'CODE' => (string)$document['CODE'],
            'FILE_URL' => DocumentVersionService::resolveFileUrl($version),
            'FILE_EXT' => DocumentVersionService::resolveFileExtension($version),
            'FILE_NAME' => DocumentVersionService::resolveFileName($version, (string)$document['TITLE']),
            'BODY_HTML' => (string)($version['BODY_HTML'] ?? ''),
            'DATE_PUBLISH' => RequiredDocumentVersionRepository::getPublishDateFormatted($version, false),
        ];
    }
}

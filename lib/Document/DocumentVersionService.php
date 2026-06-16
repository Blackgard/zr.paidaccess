<?php

namespace Zr\PaidAccess\Document;

use Bitrix\Main\Type\DateTime;
use Zr\PaidAccess\PaidAccessCore;

class DocumentVersionService
{
    public static function getNextVersionNumber(int $documentId): int
    {
        return RequiredDocumentVersionRepository::getMaxVersionNumber($documentId) + 1;
    }

    /**
     * @param array<string, mixed> $data FILE_ID, BODY_HTML, CREATED_BY
     */
    public static function publishVersion(int $documentId, array $data): int
    {
        $document = RequiredDocumentRepository::getById($documentId);
        if ($document === null) {
            throw new \RuntimeException('Документ не найден');
        }

        $fileId = isset($data['FILE_ID']) ? (int)$data['FILE_ID'] : 0;
        $bodyHtml = trim((string)($data['BODY_HTML'] ?? ''));
        if ($fileId <= 0 && $bodyHtml === '') {
            throw new \InvalidArgumentException('Укажите файл или текст документа');
        }

        $versionNumber = self::getNextVersionNumber($documentId);
        $now = new DateTime();

        RequiredDocumentVersionRepository::clearCurrentFlag($documentId);

        $versionId = RequiredDocumentVersionRepository::add([
            'DOCUMENT_ID' => $documentId,
            'VERSION' => $versionNumber,
            'FILE_ID' => $fileId > 0 ? $fileId : null,
            'BODY_HTML' => $bodyHtml !== '' ? $bodyHtml : null,
            'IS_CURRENT' => 'Y',
            'DATE_PUBLISH' => $now,
            'CREATED_BY' => isset($data['CREATED_BY']) ? (int)$data['CREATED_BY'] : null,
        ]);

        RequiredDocumentRepository::update($documentId, ['DATE_UPDATE' => $now]);

        return $versionId;
    }

    public static function resolveFileUrl(?array $version): ?string
    {
        if ($version === null) {
            return null;
        }

        $fileId = (int)($version['FILE_ID'] ?? 0);
        if ($fileId <= 0 || !class_exists(\CFile::class)) {
            return null;
        }

        $path = (string)\CFile::GetPath($fileId);

        return $path !== '' ? $path : null;
    }
}

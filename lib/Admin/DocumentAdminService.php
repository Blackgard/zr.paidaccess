<?php

namespace Zr\PaidAccess\Admin;

use Bitrix\Main\Type\DateTime;
use Zr\PaidAccess\Document\DocumentVersionService;
use Zr\PaidAccess\Document\RequiredDocumentRepository;
use Zr\PaidAccess\Document\RequiredDocumentVersionRepository;
use Zr\PaidAccess\Log\AuditLogService;
use Zr\PaidAccess\PaidAccessCore;

class DocumentAdminService
{
    /**
     * @param array<string, mixed> $filterData
     * @return array<string, mixed>
     */
    public static function buildDocumentFilter(array $filterData): array
    {
        $filter = [];

        if (!empty($filterData['SITE_ID'])) {
            $filter['=SITE_ID'] = PaidAccessCore::normalizeSiteId((string)$filterData['SITE_ID']);
        }
        if (!empty($filterData['CODE'])) {
            $filter['%CODE'] = (string)$filterData['CODE'];
        }
        if (!empty($filterData['TITLE'])) {
            $filter['%TITLE'] = (string)$filterData['TITLE'];
        }
        if (!empty($filterData['ACTIVE'])) {
            $filter['=ACTIVE'] = (string)$filterData['ACTIVE'];
        }
        if (!empty($filterData['IS_REQUIRED'])) {
            $filter['=IS_REQUIRED'] = (string)$filterData['IS_REQUIRED'];
        }

        return $filter;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function saveDocument(int $id, array $data): int
    {
        $siteId = PaidAccessCore::normalizeSiteId((string)($data['SITE_ID'] ?? ''));
        $code = trim((string)($data['CODE'] ?? ''));
        $title = trim((string)($data['TITLE'] ?? ''));

        if ($code === '' || $title === '') {
            throw new \InvalidArgumentException('Заполните код и название документа');
        }

        if (!preg_match('/^[a-z0-9_\-]+$/i', $code)) {
            throw new \InvalidArgumentException('Код документа: только латиница, цифры, _ и -');
        }

        $existing = RequiredDocumentRepository::getByCode($code, $siteId);
        if ($existing !== null && (int)$existing['ID'] !== $id) {
            throw new \InvalidArgumentException('Документ с таким кодом уже существует на сайте');
        }

        $fields = [
            'SITE_ID' => $siteId,
            'CODE' => $code,
            'TITLE' => $title,
            'SORT' => max(0, (int)($data['SORT'] ?? 500)),
            'ACTIVE' => ($data['ACTIVE'] ?? 'Y') === 'Y' ? 'Y' : 'N',
            'IS_REQUIRED' => ($data['IS_REQUIRED'] ?? 'Y') === 'Y' ? 'Y' : 'N',
        ];

        if ($id > 0) {
            $before = RequiredDocumentRepository::getById($id);
            RequiredDocumentRepository::update($id, $fields);
            AuditLogService::log(
                'required_document',
                $id,
                'update',
                AuditLogService::encodeSnapshot($before),
                AuditLogService::encodeSnapshot(array_merge($before ?? [], $fields))
            );

            return $id;
        }

        $newId = RequiredDocumentRepository::create($fields);
        AuditLogService::log(
            'required_document',
            $newId,
            'create',
            null,
            AuditLogService::encodeSnapshot(array_merge($fields, ['ID' => $newId]))
        );

        return $newId;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function publishVersion(int $documentId, array $data, ?int $adminUserId = null): int
    {
        global $USER;

        if ($adminUserId === null && is_object($USER) && $USER->IsAuthorized()) {
            $adminUserId = (int)$USER->GetID();
        }

        $fileId = 0;
        if (!empty($data['FILE_ID'])) {
            $fileId = (int)$data['FILE_ID'];
        } elseif (!empty($_FILES['VERSION_FILE']['tmp_name'])) {
            $fileId = self::saveUploadedFile($_FILES['VERSION_FILE']);
        }

        $versionId = DocumentVersionService::publishVersion($documentId, [
            'FILE_ID' => $fileId,
            'BODY_HTML' => (string)($data['BODY_HTML'] ?? ''),
            'CREATED_BY' => $adminUserId,
        ]);

        $version = RequiredDocumentVersionRepository::getById($versionId);
        AuditLogService::log(
            'required_document_version',
            $versionId,
            'publish',
            null,
            AuditLogService::encodeSnapshot($version),
            'Опубликована версия #' . ($version['VERSION'] ?? ''),
            $adminUserId
        );

        return $versionId;
    }

    /**
     * @param array<string, mixed> $file
     */
    private static function saveUploadedFile(array $file): int
    {
        if (!class_exists(\CFile::class)) {
            throw new \RuntimeException('CFile недоступен');
        }

        $file['MODULE_ID'] = PaidAccessCore::MODULE_ID;
        $fileId = (int)\CFile::SaveFile($file, 'zr.paidaccess/documents');
        if ($fileId <= 0) {
            throw new \RuntimeException('Не удалось сохранить файл документа');
        }

        return $fileId;
    }

    public static function getCurrentVersionLabel(int $documentId): string
    {
        $version = RequiredDocumentVersionRepository::getCurrentByDocumentId($documentId);
        if ($version === null) {
            return '—';
        }

        return 'v' . (int)$version['VERSION'] . ' (' . RequiredDocumentVersionRepository::getPublishDateFormatted($version) . ')';
    }
}

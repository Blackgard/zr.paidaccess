<?php

namespace Zr\PaidAccess\Document;

use Zr\PaidAccess\PaidAccessCore;

class RequiredDocumentService
{
    /**
     * Опубликованные документы сайта (активные, с текущей версией).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getPublishedList(?string $siteId = null, bool $onlyRequired = false): array
    {
        $rows = RequiredDocumentRepository::getActiveWithCurrentVersion($siteId, $onlyRequired);
        $items = [];

        foreach ($rows as $row) {
            $version = $row['CURRENT_VERSION'] ?? null;
            if (!is_array($version)) {
                continue;
            }

            $items[] = self::buildPublicItem($row, $version);
        }

        return $items;
    }

    /**
     * Документ по символьному коду (активный, с текущей версией).
     *
     * @return array<string, mixed>|null
     */
    public static function getByCode(string $code, ?string $siteId = null, string $detailUrlTemplate = ''): ?array
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $document = RequiredDocumentRepository::getByCode($code, $siteId);
        if ($document === null || ($document['ACTIVE'] ?? 'N') !== 'Y') {
            return null;
        }

        $version = RequiredDocumentVersionRepository::getCurrentByDocumentId((int)$document['ID']);
        if ($version === null) {
            return null;
        }

        return self::buildPublicItem($document, $version, $detailUrlTemplate);
    }

    /**
     * Документ по ID (активный, с текущей версией).
     *
     * @return array<string, mixed>|null
     */
    public static function getById(int $id, ?string $siteId = null, string $detailUrlTemplate = ''): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        $document = RequiredDocumentRepository::getById($id);
        if ($document === null || ($document['SITE_ID'] ?? '') !== $siteId || ($document['ACTIVE'] ?? 'N') !== 'Y') {
            return null;
        }

        $version = RequiredDocumentVersionRepository::getCurrentByDocumentId($id);
        if ($version === null) {
            return null;
        }

        return self::buildPublicItem($document, $version, $detailUrlTemplate);
    }

    /**
     * @param array<string, mixed> $document
     * @param array<string, mixed> $version
     * @return array<string, mixed>
     */
    public static function buildPublicItem(array $document, array $version, string $detailUrlTemplate = ''): array
    {
        $fileUrl = DocumentVersionService::resolveFileUrl($version);
        $bodyHtml = trim((string)($version['BODY_HTML'] ?? ''));
        $title = (string)($document['TITLE'] ?? '');
        $code = (string)($document['CODE'] ?? '');

        $item = [
            'ID' => (int)$document['ID'],
            'CODE' => $code,
            'TITLE' => $title,
            'SORT' => (int)($document['SORT'] ?? 500),
            'IS_REQUIRED' => ($document['IS_REQUIRED'] ?? 'N') === 'Y',
            'VERSION_ID' => (int)$version['ID'],
            'VERSION' => (string)$version['VERSION'],
            'VERSION_LABEL' => DocumentVersionService::formatVersionLabel((string)$version['VERSION']),
            'FILE_URL' => $fileUrl,
            'FILE_EXT' => DocumentVersionService::resolveFileExtension($version),
            'FILE_NAME' => DocumentVersionService::resolveFileName($version, $title),
            'BODY_HTML' => $bodyHtml,
            'DATE_PUBLISH' => RequiredDocumentVersionRepository::getPublishDateFormatted($version, false),
            'HAS_FILE' => $fileUrl !== null && $fileUrl !== '',
            'HAS_BODY' => $bodyHtml !== '',
        ];

        $item['URL'] = self::resolvePublicUrl($item, $detailUrlTemplate);
        $item['OPEN_IN_NEW_TAB'] = $item['HAS_FILE'];

        return $item;
    }

    /**
     * @param array<string, mixed> $item
     */
    public static function resolvePublicUrl(array $item, string $detailUrlTemplate = ''): string
    {
        $fileUrl = (string)($item['FILE_URL'] ?? '');
        if ($fileUrl !== '') {
            return $fileUrl;
        }

        $detailUrl = self::resolveDetailUrl($detailUrlTemplate, $item);
        if ($detailUrl !== '') {
            return $detailUrl;
        }

        if (!empty($item['HAS_BODY'])) {
            return '#zr-doc-' . rawurlencode((string)($item['CODE'] ?? ''));
        }

        return '';
    }

    /**
     * @param array<string, mixed> $item
     */
    public static function resolveDetailUrl(string $template, array $item): string
    {
        $template = trim($template);
        if ($template === '') {
            return '';
        }

        return str_replace(
            ['#CODE#', '#ID#', '#VERSION_ID#'],
            [
                rawurlencode((string)($item['CODE'] ?? '')),
                (string)(int)($item['ID'] ?? 0),
                (string)(int)($item['VERSION_ID'] ?? 0),
            ],
            $template
        );
    }
}

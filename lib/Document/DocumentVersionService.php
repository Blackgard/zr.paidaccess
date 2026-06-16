<?php

namespace Zr\PaidAccess\Document;

use Bitrix\Main\Type\DateTime;
use Zr\PaidAccess\PaidAccessCore;

class DocumentVersionService
{
    public const VERSION_MAX_LENGTH = 32;

    public static function normalizeVersionLabel(string $input): string
    {
        $label = trim($input);
        if ($label === '') {
            return '';
        }

        if (strncasecmp($label, 'v', 1) === 0) {
            $label = ltrim(substr($label, 1));
        }

        return $label;
    }

    public static function validateVersionLabel(string $label): void
    {
        $label = self::normalizeVersionLabel($label);
        if ($label === '') {
            throw new \InvalidArgumentException('Укажите номер версии');
        }

        if (strlen($label) > self::VERSION_MAX_LENGTH) {
            throw new \InvalidArgumentException('Номер версии слишком длинный');
        }

        if (!preg_match('/^[0-9]+(?:\.[0-9]+)*$/', $label)) {
            throw new \InvalidArgumentException('Номер версии: только цифры и точки (например 1, 1.01, 2.0)');
        }
    }

    public static function formatVersionLabel(string $version): string
    {
        $version = self::normalizeVersionLabel($version);
        if ($version === '') {
            return '';
        }

        return 'v' . $version;
    }

    public static function getSuggestedVersion(int $documentId): string
    {
        if ($documentId <= 0) {
            return '1';
        }

        $latest = RequiredDocumentVersionRepository::getLatestByDocumentId($documentId);
        if ($latest === null) {
            return '1';
        }

        return self::getSuggestedVersionFromLabel((string)($latest['VERSION'] ?? ''));
    }

    public static function getSuggestedVersionFromLabel(string $label): string
    {
        $label = self::normalizeVersionLabel($label);
        if ($label === '') {
            return '1';
        }

        if (preg_match('/^(\d+)$/', $label, $matches)) {
            return (string)((int)$matches[1] + 1);
        }

        if (preg_match('/^(\d+)\.(\d+)$/', $label, $matches)) {
            $minor = (int)$matches[2] + 1;
            $width = strlen($matches[2]);

            return $matches[1] . '.' . str_pad((string)$minor, $width, '0', STR_PAD_LEFT);
        }

        return $label . '.1';
    }

    /**
     * @param array<string, mixed> $data VERSION, FILE_ID, BODY_HTML, CREATED_BY
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

        $versionLabel = array_key_exists('VERSION', $data)
            ? self::normalizeVersionLabel((string)$data['VERSION'])
            : self::getSuggestedVersion($documentId);
        self::validateVersionLabel($versionLabel);

        if (RequiredDocumentVersionRepository::versionExists($documentId, $versionLabel)) {
            throw new \InvalidArgumentException(
                'Версия ' . self::formatVersionLabel($versionLabel) . ' уже существует для этого документа'
            );
        }

        $now = new DateTime();

        RequiredDocumentVersionRepository::clearCurrentFlag($documentId);

        $versionId = RequiredDocumentVersionRepository::add([
            'DOCUMENT_ID' => $documentId,
            'VERSION' => $versionLabel,
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

    public static function resolveFileExtension(?array $version): string
    {
        $url = self::resolveFileUrl($version);
        if ($url === null) {
            return '';
        }

        $path = (string)(parse_url($url, PHP_URL_PATH) ?? '');
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $ext !== '' ? $ext : 'file';
    }

    public static function resolveFileName(?array $version, string $fallbackTitle = ''): string
    {
        $url = self::resolveFileUrl($version);
        if ($url !== null) {
            $path = (string)(parse_url($url, PHP_URL_PATH) ?? '');
            $name = basename($path);
            if ($name !== '' && $name !== '/') {
                return $name;
            }
        }

        return $fallbackTitle;
    }
}

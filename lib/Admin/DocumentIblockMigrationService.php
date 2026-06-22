<?php

namespace Zr\PaidAccess\Admin;

use Bitrix\Main\Type\DateTime;
use Zr\PaidAccess\Document\DocumentVersionService;
use Zr\PaidAccess\Document\RequiredDocumentRepository;
use Zr\PaidAccess\Log\AuditLogService;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Utility\DocumentMigrationMapping;
use Zr\PaidAccess\Utility\DocumentMigrationTargetFields;
use Zr\PaidAccess\Utility\DocumentMigrationValueResolver;
use Zr\PaidAccess\Utility\IblockIntrospectionService;

class DocumentIblockMigrationService
{
    /**
     * @param array<string, string> $mapping
     * @return array{items: array<int, array<string, mixed>>, total: int, errors: string[]}
     */
    public static function preview(
        int $iblockId,
        ?string $siteId,
        array $mapping,
        int $limit = 10
    ): array {
        $errors = DocumentMigrationMapping::validate($mapping);
        if ($errors !== []) {
            return ['items' => [], 'total' => 0, 'errors' => $errors];
        }

        if (!IblockIntrospectionService::isAvailable()) {
            return ['items' => [], 'total' => 0, 'errors' => ['Модуль iblock не установлен']];
        }

        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        $mergedMapping = DocumentMigrationMapping::mergeWithDefaults($mapping);
        $elements = self::loadElements($iblockId);
        $items = [];

        foreach (array_slice($elements, 0, max(1, $limit)) as $element) {
            $items[] = self::buildPreviewRow($element, $siteId, $mergedMapping);
        }

        return [
            'items' => $items,
            'total' => count($elements),
            'errors' => [],
        ];
    }

    /**
     * @param array<string, string> $mapping
     * @return array{
     *     created_documents: int,
     *     created_versions: int,
     *     skipped: int,
     *     errors: string[]
     * }
     */
    public static function migrate(
        int $iblockId,
        ?string $siteId,
        array $mapping,
        bool $skipExistingByCode = true,
        ?int $adminUserId = null
    ): array {
        $errors = DocumentMigrationMapping::validate($mapping);
        if ($errors !== []) {
            return self::emptyResult($errors);
        }

        if (!IblockIntrospectionService::isAvailable()) {
            return self::emptyResult(['Модуль iblock не установлен']);
        }

        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        $mergedMapping = DocumentMigrationMapping::mergeWithDefaults($mapping);

        $result = [
            'created_documents' => 0,
            'created_versions' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach (self::loadElements($iblockId) as $element) {
            try {
                $outcome = self::migrateElement($element, $siteId, $mergedMapping, $skipExistingByCode, $adminUserId);
                if ($outcome === 'created') {
                    $result['created_documents']++;
                    $result['created_versions']++;
                } elseif ($outcome === 'skipped') {
                    $result['skipped']++;
                }
            } catch (\Throwable $e) {
                $elementId = (int)($element['FIELDS']['ID'] ?? 0);
                $result['errors'][] = 'Элемент #' . $elementId . ': ' . $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * @param array<string, string> $mapping
     * @return array<string, mixed>
     */
    protected static function buildPreviewRow(array $element, string $siteId, array $mapping): array
    {
        $payload = self::mapElement($element, $siteId, $mapping);

        return [
            'ELEMENT_ID' => (int)($element['FIELDS']['ID'] ?? 0),
            'TITLE' => (string)($payload['document']['TITLE'] ?? ''),
            'CODE' => (string)($payload['document']['CODE'] ?? ''),
            'FILE_ID' => (int)($payload['version']['FILE_ID'] ?? 0),
            'HAS_BODY' => trim((string)($payload['version']['BODY_HTML'] ?? '')) !== '',
            'DATE_CREATE' => self::formatDate($payload['document']['DATE_CREATE'] ?? null),
            'DATE_PUBLISH' => self::formatDate($payload['version']['DATE_PUBLISH'] ?? null),
        ];
    }

    /**
     * @param array<string, string> $mapping
     *
     * @return 'created'|'skipped'
     */
    protected static function migrateElement(
        array $element,
        string $siteId,
        array $mapping,
        bool $skipExistingByCode,
        ?int $adminUserId
    ): string {
        $payload = self::mapElement($element, $siteId, $mapping);
        $documentFields = $payload['document'];
        $versionFields = $payload['version'];

        $code = (string)($documentFields['CODE'] ?? '');
        $title = (string)($documentFields['TITLE'] ?? '');
        if ($title === '') {
            throw new \RuntimeException('Пустое название документа');
        }

        $existing = RequiredDocumentRepository::getByCode($code, $siteId);
        if ($existing !== null) {
            if ($skipExistingByCode) {
                return 'skipped';
            }

            throw new \RuntimeException('Документ с кодом ' . $code . ' уже существует');
        }

        $fileId = (int)($versionFields['FILE_ID'] ?? 0);
        $bodyHtml = trim((string)($versionFields['BODY_HTML'] ?? ''));
        if ($fileId <= 0 && $bodyHtml === '') {
            throw new \RuntimeException('Нет файла и текста версии');
        }

        $documentId = RequiredDocumentRepository::create([
            'SITE_ID' => $siteId,
            'CODE' => $code,
            'TITLE' => $title,
            'SORT' => (int)($documentFields['SORT'] ?? 500),
            'ACTIVE' => (string)($documentFields['ACTIVE'] ?? 'Y'),
            'IS_REQUIRED' => (string)($documentFields['IS_REQUIRED'] ?? 'Y'),
            'DATE_CREATE' => $documentFields['DATE_CREATE'] ?? new DateTime(),
            'DATE_UPDATE' => $documentFields['DATE_CREATE'] ?? new DateTime(),
        ]);

        $versionData = [
            'VERSION' => (string)($versionFields['VERSION'] ?? '1'),
            'FILE_ID' => $fileId,
            'BODY_HTML' => $bodyHtml,
            'CREATED_BY' => $adminUserId,
        ];

        if (!empty($versionFields['DATE_PUBLISH']) && $versionFields['DATE_PUBLISH'] instanceof DateTime) {
            $versionData['DATE_PUBLISH'] = $versionFields['DATE_PUBLISH'];
        }

        $versionId = DocumentVersionService::publishVersion($documentId, $versionData);

        AuditLogService::log(
            'document_iblock_migration',
            $documentId,
            'import',
            null,
            AuditLogService::encodeSnapshot([
                'elementId' => (int)($element['FIELDS']['ID'] ?? 0),
                'versionId' => $versionId,
                'code' => $code,
            ]),
            'Импорт из инфоблока',
            $adminUserId
        );

        return 'created';
    }

    /**
     * @param array<string, string> $mapping
     * @return array{document: array<string, mixed>, version: array<string, mixed>}
     */
    protected static function mapElement(array $element, string $siteId, array $mapping): array
    {
        $fields = $element['FIELDS'];
        $properties = $element['PROPERTIES'];
        $elementId = (int)($fields['ID'] ?? 0);

        $rawCode = DocumentMigrationValueResolver::resolveString(
            DocumentMigrationValueResolver::resolve($mapping['document.code'] ?? '', $fields, $properties)
        );
        $title = DocumentMigrationValueResolver::resolveString(
            DocumentMigrationValueResolver::resolve($mapping['document.title'] ?? '', $fields, $properties)
        );
        $code = DocumentMigrationValueResolver::buildDocumentCode($rawCode, $title, $elementId);

        $fileValue = DocumentMigrationValueResolver::resolve($mapping['version.file'] ?? '', $fields, $properties);
        $bodyValue = DocumentMigrationValueResolver::resolve($mapping['version.body_html'] ?? '', $fields, $properties);
        $versionValue = DocumentMigrationValueResolver::resolve($mapping['version.version'] ?? '', $fields, $properties);

        return [
            'document' => [
                'SITE_ID' => $siteId,
                'CODE' => $code,
                'TITLE' => $title,
                'SORT' => DocumentMigrationValueResolver::resolveInt(
                    DocumentMigrationValueResolver::resolve($mapping['document.sort'] ?? '', $fields, $properties),
                    500
                ),
                'ACTIVE' => DocumentMigrationValueResolver::resolveYn(
                    DocumentMigrationValueResolver::resolve($mapping['document.active'] ?? '', $fields, $properties)
                ),
                'IS_REQUIRED' => DocumentMigrationValueResolver::resolveYn(
                    DocumentMigrationValueResolver::resolve($mapping['document.is_required'] ?? '', $fields, $properties)
                ),
                'DATE_CREATE' => DocumentMigrationValueResolver::resolveDateTime(
                    DocumentMigrationValueResolver::resolve($mapping['document.date_create'] ?? '', $fields, $properties)
                ),
            ],
            'version' => [
                'FILE_ID' => DocumentMigrationValueResolver::resolveFileId($fileValue),
                'BODY_HTML' => DocumentMigrationValueResolver::resolveString($bodyValue),
                'VERSION' => DocumentMigrationValueResolver::resolveString($versionValue) !== ''
                    ? DocumentMigrationValueResolver::resolveString($versionValue)
                    : '1',
                'DATE_PUBLISH' => DocumentMigrationValueResolver::resolveDateTime(
                    DocumentMigrationValueResolver::resolve($mapping['version.date_publish'] ?? '', $fields, $properties)
                ),
            ],
        ];
    }

    /**
     * @return array<int, array{FIELDS: array<string, mixed>, PROPERTIES: array<string, mixed>}>
     */
    protected static function loadElements(int $iblockId): array
    {
        $elements = [];
        $result = \CIBlockElement::GetList(
            ['ID' => 'ASC'],
            ['IBLOCK_ID' => $iblockId, 'CHECK_PERMISSIONS' => 'N'],
            false,
            false,
            [
                'ID',
                'IBLOCK_ID',
                'NAME',
                'CODE',
                'XML_ID',
                'SORT',
                'ACTIVE',
                'DATE_CREATE',
                'TIMESTAMP_X',
                'ACTIVE_FROM',
                'ACTIVE_TO',
                'DETAIL_TEXT',
                'PREVIEW_TEXT',
            ]
        );

        while ($ob = $result->GetNextElement()) {
            $fields = $ob->GetFields();
            $properties = [];
            foreach ($ob->GetProperties() as $code => $property) {
                $properties[$code] = $property;
            }

            $elements[] = [
                'FIELDS' => $fields,
                'PROPERTIES' => $properties,
            ];
        }

        return $elements;
    }

    /**
     * @param mixed $value
     */
    protected static function formatDate($value): string
    {
        if ($value instanceof DateTime) {
            return $value->format('d.m.Y H:i');
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d.m.Y H:i');
        }

        return '';
    }

    /**
     * @param string[] $errors
     * @return array{created_documents: int, created_versions: int, skipped: int, errors: string[]}
     */
    protected static function emptyResult(array $errors): array
    {
        return [
            'created_documents' => 0,
            'created_versions' => 0,
            'skipped' => 0,
            'errors' => $errors,
        ];
    }
}

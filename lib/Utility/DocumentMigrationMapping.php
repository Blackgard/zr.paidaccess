<?php

namespace Zr\PaidAccess\Utility;

class DocumentMigrationMapping
{
    /**
     * @param array<string, string> $sources
     * @return array<string, string>
     */
    public static function mergeWithDefaults(array $sources): array
    {
        return array_merge(DocumentMigrationTargetFields::getDefaultSources(), $sources);
    }

    /**
     * @param array<string, string> $mapping
     * @return string[]
     */
    public static function validate(array $mapping): array
    {
        $errors = [];
        $targets = DocumentMigrationTargetFields::getAll();
        $merged = self::mergeWithDefaults($mapping);

        foreach ($targets as $targetCode => $meta) {
            if (empty($meta['required'])) {
                continue;
            }

            $source = trim((string)($merged[$targetCode] ?? ''));
            if ($source === '') {
                $errors[] = 'Не задан источник для поля: ' . (string)$meta['label'];
            }
        }

        $fileSource = trim((string)($merged['version.file'] ?? ''));
        $bodySource = trim((string)($merged['version.body_html'] ?? ''));
        if ($fileSource === '' && $bodySource === '') {
            $errors[] = 'Укажите источник файла или HTML-текста версии документа';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $requestData
     * @return array<string, string>
     */
    public static function fromRequest(array $requestData): array
    {
        $mapping = [];
        foreach (DocumentMigrationTargetFields::getAll() as $targetCode => $meta) {
            $key = 'map_' . str_replace('.', '_', $targetCode);
            if (!array_key_exists($key, $requestData)) {
                continue;
            }

            $value = trim((string)$requestData[$key]);
            if ($value !== '') {
                $mapping[$targetCode] = $value;
            }
        }

        return $mapping;
    }
}

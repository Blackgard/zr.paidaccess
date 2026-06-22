<?php

namespace Zr\PaidAccess\Utility;

use Bitrix\Main\Type\DateTime;

class DocumentMigrationValueResolver
{
    /**
     * @param array<string, mixed> $elementFields
     * @param array<string, mixed> $properties keyed by property CODE
     */
    public static function resolve(string $source, array $elementFields, array $properties)
    {
        $source = trim($source);
        if ($source === '') {
            return null;
        }

        if (strpos($source, 'const:') === 0) {
            return substr($source, 6);
        }

        if (strpos($source, 'field:') === 0) {
            $field = substr($source, 6);

            return $elementFields[$field] ?? null;
        }

        if (strpos($source, 'property:') === 0) {
            $code = substr($source, 9);
            if ($code === '' || !isset($properties[$code])) {
                return null;
            }

            return self::normalizePropertyValue($properties[$code]);
        }

        return null;
    }

    /**
     * @param mixed $property
     */
    public static function normalizePropertyValue($property)
    {
        if (!is_array($property)) {
            return $property;
        }

        if (array_key_exists('VALUE', $property)) {
            $value = $property['VALUE'];
            if (is_array($value)) {
                return $value[0] ?? null;
            }

            return $value;
        }

        if (isset($property[0]) && is_array($property[0]) && array_key_exists('VALUE', $property[0])) {
            return $property[0]['VALUE'];
        }

        return $property;
    }

    /**
       * @param mixed $value
       */
    public static function resolveFileId($value): int
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $fileId = self::resolveFileId($item);
                if ($fileId > 0) {
                    return $fileId;
                }
            }

            return 0;
        }

        return (int)$value;
    }

    /**
     * @param mixed $value
     */
    public static function resolveString($value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof DateTime) {
            return $value->format('d.m.Y H:i:s');
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d.m.Y H:i:s');
        }

        if (is_scalar($value)) {
            return trim((string)$value);
        }

        return '';
    }

    /**
     * @param mixed $value
     */
    public static function resolveYn($value, string $default = 'Y'): string
    {
        $string = self::resolveString($value);
        if ($string === '') {
            return $default === 'N' ? 'N' : 'Y';
        }

        $upper = strtoupper($string);

        return in_array($upper, ['Y', 'N'], true) ? $upper : ($default === 'N' ? 'N' : 'Y');
    }

    /**
     * @param mixed $value
     */
    public static function resolveInt($value, int $default = 0): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return (int)$value;
    }

    /**
     * @param mixed $value
     */
    public static function resolveDateTime($value): ?DateTime
    {
        if ($value instanceof DateTime) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return DateTime::createFromPhp(\DateTime::createFromInterface($value));
        }

        $string = self::resolveString($value);
        if ($string === '') {
            return null;
        }

        try {
            return new DateTime($string);
        } catch (\Exception $e) {
            $timestamp = strtotime($string);

            return $timestamp !== false ? DateTime::createFromTimestamp($timestamp) : null;
        }
    }

    public static function buildDocumentCode(string $rawCode, string $title, int $elementId): string
    {
        $code = strtolower(trim($rawCode));
        $code = preg_replace('/[^a-z0-9_\-]+/i', '-', $code) ?? '';
        $code = trim($code, '-');

        if ($code !== '') {
            return $code;
        }

        $fromTitle = self::translit($title);
        if ($fromTitle !== '') {
            return $fromTitle;
        }

        return 'doc-' . $elementId;
    }

    public static function translit(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (class_exists(\CUtil::class)) {
            return (string)\CUtil::translit($value, 'ru', [
                'max_len' => 64,
                'change_case' => 'L',
                'replace_space' => '-',
                'replace_other' => '-',
                'delete_repeat_replace' => true,
            ]);
        }

        $slug = preg_replace('/[^a-z0-9_\-]+/i', '-', $value) ?? '';

        return strtolower(trim($slug, '-'));
    }
}

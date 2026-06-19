<?php

namespace Zr\PaidAccess\Utility;

use Bitrix\Main\Loader;

class IblockIntrospectionService
{
    public static function isAvailable(): bool
    {
        return Loader::includeModule('iblock');
    }

    /**
     * @return array<int, string>
     */
    public static function getActiveIblockOptions(): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        $options = [];
        $result = \CIBlock::GetList(['SORT' => 'ASC', 'NAME' => 'ASC'], ['ACTIVE' => 'Y']);
        while ($row = $result->Fetch()) {
            $id = (int)($row['ID'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $label = '[' . $id . '] ' . (string)($row['NAME'] ?? '');
            if (!empty($row['CODE'])) {
                $label .= ' (' . (string)$row['CODE'] . ')';
            }
            $options[$id] = $label;
        }

        return $options;
    }

    /**
     * @return array{
     *     iblock: array<string, mixed>|null,
     *     fields: array<int, array{id: string, label: string, type: string}>,
     *     properties: array<int, array{id: string, code: string, label: string, type: string, multiple: bool}>,
     *     sources: array<string, string>
     * }
     */
    public static function getSchema(int $iblockId): array
    {
        $empty = [
            'iblock' => null,
            'fields' => [],
            'properties' => [],
            'sources' => [],
        ];

        if (!self::isAvailable() || $iblockId <= 0) {
            return $empty;
        }

        $iblock = \CIBlock::GetByID($iblockId)->Fetch();
        if (!is_array($iblock)) {
            return $empty;
        }

        $fields = self::getElementFieldSources();
        $properties = self::getPropertySources($iblockId);
        $sources = [];

        foreach ($fields as $field) {
            $sources[$field['id']] = $field['label'];
        }
        foreach ($properties as $property) {
            $sources[$property['id']] = $property['label'];
        }
        $sources['const:Y'] = 'Константа: Y';
        $sources['const:N'] = 'Константа: N';
        $sources['const:1'] = 'Константа: 1';

        return [
            'iblock' => $iblock,
            'fields' => $fields,
            'properties' => $properties,
            'sources' => $sources,
        ];
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    public static function getElementFieldSources(): array
    {
        return [
            ['id' => 'field:NAME', 'label' => 'Поле элемента: Название (NAME)', 'type' => 'string'],
            ['id' => 'field:CODE', 'label' => 'Поле элемента: Символьный код (CODE)', 'type' => 'string'],
            ['id' => 'field:XML_ID', 'label' => 'Поле элемента: Внешний код (XML_ID)', 'type' => 'string'],
            ['id' => 'field:SORT', 'label' => 'Поле элемента: Сортировка (SORT)', 'type' => 'number'],
            ['id' => 'field:ACTIVE', 'label' => 'Поле элемента: Активность (ACTIVE)', 'type' => 'yn'],
            ['id' => 'field:DATE_CREATE', 'label' => 'Поле элемента: Дата создания (DATE_CREATE)', 'type' => 'datetime'],
            ['id' => 'field:TIMESTAMP_X', 'label' => 'Поле элемента: Дата изменения (TIMESTAMP_X)', 'type' => 'datetime'],
            ['id' => 'field:ACTIVE_FROM', 'label' => 'Поле элемента: Начало активности (ACTIVE_FROM)', 'type' => 'datetime'],
            ['id' => 'field:ACTIVE_TO', 'label' => 'Поле элемента: Окончание активности (ACTIVE_TO)', 'type' => 'datetime'],
            ['id' => 'field:DETAIL_TEXT', 'label' => 'Поле элемента: Детальный текст (DETAIL_TEXT)', 'type' => 'html'],
            ['id' => 'field:PREVIEW_TEXT', 'label' => 'Поле элемента: Анонс (PREVIEW_TEXT)', 'type' => 'html'],
        ];
    }

    /**
     * @return array<int, array{id: string, code: string, label: string, type: string, multiple: bool}>
     */
    public static function getPropertySources(int $iblockId): array
    {
        if (!self::isAvailable() || $iblockId <= 0) {
            return [];
        }

        $items = [];
        $result = \CIBlockProperty::GetList(
            ['SORT' => 'ASC', 'NAME' => 'ASC'],
            ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y']
        );

        while ($row = $result->Fetch()) {
            $code = trim((string)($row['CODE'] ?? ''));
            if ($code === '') {
                continue;
            }

            $propertyType = (string)($row['PROPERTY_TYPE'] ?? '');
            $userType = (string)($row['USER_TYPE'] ?? '');
            $items[] = [
                'id' => 'property:' . $code,
                'code' => $code,
                'label' => 'Свойство: ' . (string)($row['NAME'] ?? $code) . ' (' . $code . ', ' . $propertyType . ')',
                'type' => $userType !== '' ? $userType : $propertyType,
                'multiple' => ((string)($row['MULTIPLE'] ?? 'N')) === 'Y',
            ];
        }

        return $items;
    }
}

<?php

namespace Zr\PaidAccess\Utility;

/**
 * Целевые поля модуля для сопоставления с инфоблоком.
 */
final class DocumentMigrationTargetFields
{
    public const GROUP_DOCUMENT = 'document';
    public const GROUP_VERSION = 'version';

    /**
     * @return array<string, array{code: string, label: string, group: string, required: bool}>
     */
    public static function getAll(): array
    {
        return [
            'document.code' => [
                'code' => 'document.code',
                'label' => 'Код документа (CODE)',
                'group' => self::GROUP_DOCUMENT,
                'required' => true,
            ],
            'document.title' => [
                'code' => 'document.title',
                'label' => 'Название документа',
                'group' => self::GROUP_DOCUMENT,
                'required' => true,
            ],
            'document.sort' => [
                'code' => 'document.sort',
                'label' => 'Сортировка',
                'group' => self::GROUP_DOCUMENT,
                'required' => false,
            ],
            'document.active' => [
                'code' => 'document.active',
                'label' => 'Активность',
                'group' => self::GROUP_DOCUMENT,
                'required' => false,
            ],
            'document.is_required' => [
                'code' => 'document.is_required',
                'label' => 'Обязателен для согласия',
                'group' => self::GROUP_DOCUMENT,
                'required' => false,
            ],
            'document.date_create' => [
                'code' => 'document.date_create',
                'label' => 'Дата создания документа',
                'group' => self::GROUP_DOCUMENT,
                'required' => false,
            ],
            'version.file' => [
                'code' => 'version.file',
                'label' => 'Файл версии',
                'group' => self::GROUP_VERSION,
                'required' => false,
            ],
            'version.body_html' => [
                'code' => 'version.body_html',
                'label' => 'Текст версии (HTML)',
                'group' => self::GROUP_VERSION,
                'required' => false,
            ],
            'version.date_publish' => [
                'code' => 'version.date_publish',
                'label' => 'Дата публикации версии',
                'group' => self::GROUP_VERSION,
                'required' => false,
            ],
            'version.version' => [
                'code' => 'version.version',
                'label' => 'Номер версии',
                'group' => self::GROUP_VERSION,
                'required' => false,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getDefaultSources(): array
    {
        return [
            'document.code' => 'field:CODE',
            'document.title' => 'field:NAME',
            'document.sort' => 'field:SORT',
            'document.active' => 'field:ACTIVE',
            'document.is_required' => 'const:Y',
            'document.date_create' => 'field:DATE_CREATE',
            'version.file' => 'property:FILE',
            'version.body_html' => 'field:DETAIL_TEXT',
            'version.date_publish' => 'field:DATE_CREATE',
            'version.version' => 'const:1',
        ];
    }
}

<?php

namespace Zr\PaidAccess\Utility;

/**
 * Реестр групп и утилит админки. Новая утилита — запись в getGroups().
 */
final class UtilitiesRegistry
{
    public const GROUP_MIGRATION = 'migration';

    /**
     * @return array<string, array{
     *     code: string,
     *     title: string,
     *     description: string,
     *     sort: int,
     *     utilities: array<string, array{
     *         code: string,
     *         title: string,
     *         description: string,
     *         page: string,
     *         sort: int
     *     }>
     * }>
     */
    public static function getGroups(): array
    {
        $groups = [
            self::GROUP_MIGRATION => [
                'code' => self::GROUP_MIGRATION,
                'title' => 'Миграция данных',
                'description' => 'Перенос данных из legacy-источников сайта (инфоблоки и др.) в таблицы модуля.',
                'sort' => 100,
                'utilities' => [
                    'document_iblock' => [
                        'code' => 'document_iblock',
                        'title' => 'Документы из инфоблока',
                        'description' => 'Импорт элементов инфоблока в обязательные документы модуля с сопоставлением полей и свойств.',
                        'page' => 'zr_paidaccess_util_document_iblock.php',
                        'sort' => 100,
                    ],
                ],
            ],
        ];

        foreach ($groups as &$group) {
            uasort($group['utilities'], static function (array $a, array $b): int {
                return $a['sort'] <=> $b['sort'];
            });
        }
        unset($group);

        uasort($groups, static function (array $a, array $b): int {
            return $a['sort'] <=> $b['sort'];
        });

        return $groups;
    }

    /**
     * @return array<string, array<string, mixed>>|null
     */
    public static function findGroup(string $groupCode): ?array
    {
        $groups = self::getGroups();

        return $groups[$groupCode] ?? null;
    }

    /**
     * @return array{group: array<string, mixed>, utility: array<string, mixed>}|null
     */
    public static function findUtility(string $groupCode, string $utilityCode): ?array
    {
        $group = self::findGroup($groupCode);
        if ($group === null) {
            return null;
        }

        $utility = $group['utilities'][$utilityCode] ?? null;
        if ($utility === null) {
            return null;
        }

        return [
            'group' => $group,
            'utility' => $utility,
        ];
    }

    public static function buildUtilityUrl(string $groupCode, string $utilityCode, string $lang = LANGUAGE_ID): ?string
    {
        $found = self::findUtility($groupCode, $utilityCode);
        if ($found === null) {
            return null;
        }

        return (string)$found['utility']['page'] . '?lang=' . rawurlencode($lang);
    }

    public static function buildIndexUrl(string $lang = LANGUAGE_ID): string
    {
        return 'zr_paidaccess_utilities.php?lang=' . rawurlencode($lang);
    }
}

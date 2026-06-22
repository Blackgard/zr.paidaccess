<?php

namespace Zr\PaidAccess\PublicUi;

final class PanelSectionRegistry
{
    public const TYPE_NATIVE = 'native';
    public const TYPE_HUB = 'hub';
    public const TYPE_PLANNED = 'planned';

    public const LEGACY_BASE_PATH = '/__panel_old';

    private const PROJECT_PLANNED_FEATURES = [
        'Список проектов',
        'Добавление и редактирование проекта',
        'Текстовый блок проекта',
        'Учёт дохода проекта (дивиденды)',
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getSections(): array
    {
        return [
            'news' => [
                'code' => 'news',
                'title' => 'Новости и голосования',
                'description' => 'Публикации и голосования на сайте.',
                'menu' => true,
                'type' => self::TYPE_HUB,
                'tiles' => [
                    ['label' => 'Список', 'url' => self::legacyUrl('table_news')],
                    ['label' => 'Добавить', 'url' => self::legacyUrl('add_news')],
                ],
            ],
            'users' => [
                'code' => 'users',
                'title' => 'Пользователи',
                'description' => 'Учётные записи, верификация и роли.',
                'menu' => true,
                'type' => self::TYPE_HUB,
                'tiles' => [
                    ['label' => 'Список пользователей', 'url' => self::legacyUrl('table_users')],
                    ['label' => 'Верифицировать', 'url' => self::legacyUrl('verification_users')],
                    ['label' => 'Забанить', 'url' => self::legacyUrl('ban_users')],
                    ['label' => 'Назначить роль', 'url' => self::legacyUrl('role_users')],
                ],
            ],
            'payments' => [
                'code' => 'payments',
                'title' => 'Транзакции',
                'description' => 'Платежи и статусы оплаты участников.',
                'menu' => true,
                'type' => self::TYPE_NATIVE,
                'parent' => null,
            ],
            'payment' => [
                'code' => 'payment',
                'title' => 'Редактирование платежа',
                'menu' => false,
                'type' => self::TYPE_NATIVE,
                'parent' => 'payments',
            ],
            'projects' => [
                'code' => 'projects',
                'title' => 'Проекты',
                'description' => 'Инвестиционные проекты платформы.',
                'menu' => true,
                'type' => self::TYPE_PLANNED,
                'planned_message' => 'Раздел проектов будет перенесён в модуль zr.paidaccess. Пока используйте legacy-инструменты или дождитесь обновления.',
                'tiles' => [
                    ['label' => 'Список проектов', 'planned' => true],
                    ['label' => 'Добавление проекта', 'planned' => true],
                    ['label' => 'Редактировать текст', 'planned' => true],
                ],
            ],
            'texts' => [
                'code' => 'texts',
                'title' => 'Тексты',
                'description' => 'Редактируемые текстовые блоки сайта.',
                'menu' => true,
                'type' => self::TYPE_HUB,
                'tiles' => [
                    ['label' => 'Список', 'url' => self::legacyUrl('table_texts')],
                ],
            ],
            'chat' => [
                'code' => 'chat',
                'title' => 'Чат',
                'description' => 'Комнаты и модерация чата.',
                'menu' => true,
                'type' => self::TYPE_HUB,
                'tiles' => [
                    ['label' => 'Список комнат', 'url' => self::legacyUrl('table_roms')],
                    ['label' => 'Добавить комнату', 'url' => self::legacyUrl('add_rom')],
                ],
            ],
            'documents' => [
                'code' => 'documents',
                'title' => 'Документы',
                'description' => 'Обязательные документы и опубликованные версии.',
                'menu' => true,
                'type' => self::TYPE_NATIVE,
            ],
            'mail' => [
                'code' => 'mail',
                'title' => 'Почта',
                'description' => 'Почтовые шаблоны уведомлений.',
                'menu' => true,
                'type' => self::TYPE_HUB,
                'tiles' => [
                    ['label' => 'Почтовый шаблон (регистрация)', 'url' => self::legacyUrl('template_new_user')],
                    ['label' => 'Почтовый шаблон (подтверждение)', 'url' => self::legacyUrl('template_confirm_user')],
                ],
            ],
            'support' => [
                'code' => 'support',
                'title' => 'Поддержка',
                'description' => 'Текст раздела обращений в поддержку.',
                'menu' => true,
                'type' => self::TYPE_HUB,
                'tiles' => [
                    ['label' => 'Редактировать текст', 'url' => self::legacyUrl('edit_text_support')],
                ],
            ],
            'members' => [
                'code' => 'members',
                'title' => 'Участники',
                'description' => 'Статус подписки и оплаты по участникам.',
                'menu' => true,
                'type' => self::TYPE_NATIVE,
            ],
            'index' => [
                'code' => 'index',
                'title' => 'Главная',
                'description' => 'Обзор разделов панели модератора.',
                'menu' => true,
                'type' => self::TYPE_HUB,
            ],
        ];
    }

    /**
     * Разделы для главной страницы (как в старой панели).
     *
     * @return list<array<string, mixed>>
     */
    public static function getIndexSections(string $basePath): array
    {
        $sections = [];
        foreach (self::getSections() as $code => $section) {
            if ($code === 'index' || $code === 'payment') {
                continue;
            }

            $sections[] = [
                'CODE' => $code,
                'TITLE' => (string)$section['title'],
                'TILES' => self::resolveTiles($section, $basePath),
            ];
        }

        return $sections;
    }

    /**
     * @param array<string, mixed> $section
     * @return list<array<string, mixed>>
     */
    public static function resolveTiles(array $section, string $basePath): array
    {
        $type = (string)($section['type'] ?? self::TYPE_HUB);
        $code = (string)($section['code'] ?? '');

        if ($type === self::TYPE_NATIVE) {
            return [
                [
                    'label' => (string)($section['description'] ?? 'Открыть раздел'),
                    'url' => self::buildPanelUrl($basePath, $code),
                ],
            ];
        }

        $tiles = [];
        foreach ($section['tiles'] ?? [] as $tile) {
            if (!empty($tile['planned'])) {
                $tiles[] = [
                    'label' => (string)$tile['label'],
                    'planned' => true,
                    'url' => '',
                ];
                continue;
            }

            $url = (string)($tile['url'] ?? '');
            if ($url === '' && $code !== '') {
                $url = self::buildPanelUrl($basePath, $code);
            }

            $tiles[] = [
                'label' => (string)$tile['label'],
                'url' => $url,
                'planned' => false,
            ];
        }

        return $tiles;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getMenuSections(): array
    {
        $menu = [];
        foreach (self::getSections() as $code => $section) {
            if (empty($section['menu'])) {
                continue;
            }
            $menu[$code] = $section;
        }

        return $menu;
    }

    public static function getSection(string $code): ?array
    {
        $sections = self::getSections();

        return $sections[$code] ?? null;
    }

    public static function isValidPage(string $code): bool
    {
        return isset(self::getSections()[$code]);
    }

    public static function legacyUrl(string $path): string
    {
        return rtrim(self::LEGACY_BASE_PATH, '/') . '/' . ltrim($path, '/') . '/';
    }

    public static function buildPanelUrl(string $basePath, string $page, array $query = []): string
    {
        $basePath = rtrim($basePath, '/') . '/';
        if ($page !== 'index' && $page !== '') {
            $query['page'] = $page;
        }

        $filtered = [];
        foreach ($query as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $filtered[$key] = $value;
        }

        return $filtered === [] ? $basePath : $basePath . '?' . http_build_query($filtered);
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildHubContent(array $section, string $basePath): array
    {
        $type = (string)($section['type'] ?? self::TYPE_HUB);

        $content = [
            'SECTION_CODE' => (string)($section['code'] ?? ''),
            'SECTION_TITLE' => (string)($section['title'] ?? ''),
            'TILES' => self::resolveTiles($section, $basePath),
        ];

        if ($type === self::TYPE_PLANNED) {
            $content['PLANNED'] = true;
            $content['PLANNED_MESSAGE'] = (string)($section['planned_message'] ?? '');
            $content['PLANNED_FEATURES'] = self::PROJECT_PLANNED_FEATURES;
        }

        return $content;
    }
}

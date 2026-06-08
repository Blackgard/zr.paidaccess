<?php

/**
 * @link https://alexandr-zr.ru/
 * @author Alexandr Drachenin <alexdrachenin98@gmail.com>
 * @copyright Copyright (c) 2020-2026 ZR studio (https://alexandr-zr.ru/)
 */

namespace Zr\PaidAccess\Access;

use Zr\PaidAccess\PaidAccessCore;

/**
 * Шаблоны страницы блокировки (/local/php_interface/zr.paidaccess/template_*.php).
 */
class AccessTemplate
{
    public static function getTemplatesDirectory(): string
    {
        return rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . PaidAccessCore::TEMPLATES_RELATIVE_PATH;
    }

    public static function getTemplatePath(?string $siteId = null): string
    {
        $fileName = self::resolveTemplateFileName($siteId);

        return self::getTemplatesDirectory() . '/' . $fileName;
    }

    /**
     * @return array<string, string> filename => label
     */
    public static function getAvailableTemplates(): array
    {
        $dir = self::getTemplatesDirectory();
        $templates = [];

        if (!is_dir($dir)) {
            return [PaidAccessCore::DEFAULT_BLOCK_TEMPLATE => PaidAccessCore::DEFAULT_BLOCK_TEMPLATE];
        }

        foreach (glob($dir . '/template_*.php') ?: [] as $filePath) {
            $name = basename($filePath);
            $templates[$name] = $name;
        }

        if ($templates === []) {
            $templates[PaidAccessCore::DEFAULT_BLOCK_TEMPLATE] = PaidAccessCore::DEFAULT_BLOCK_TEMPLATE;
        }

        ksort($templates);

        return $templates;
    }

    public static function isValidTemplateName(string $name): bool
    {
        return (bool)preg_match('/^template_[a-z0-9_\-]+\.php$/i', $name);
    }

    protected static function resolveTemplateFileName(?string $siteId = null): string
    {
        $template = PaidAccessCore::getAccessBlockTemplate($siteId);

        if ($template === '' || !self::isValidTemplateName($template)) {
            return PaidAccessCore::DEFAULT_BLOCK_TEMPLATE;
        }

        return $template;
    }
}

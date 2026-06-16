<?php

namespace Zr\PaidAccess\Access;

use Zr\PaidAccess\PaidAccessCore;

class DocumentConsentTemplate
{
    public static function getTemplatesDirectory(): string
    {
        return AccessTemplate::getTemplatesDirectory();
    }

    public static function getTemplatePath(?string $siteId = null): string
    {
        $fileName = self::resolveTemplateFileName($siteId);

        return self::getTemplatesDirectory() . '/' . $fileName;
    }

    /**
     * @return array<string, string>
     */
    public static function getAvailableTemplates(): array
    {
        $templates = [];
        $dir = self::getTemplatesDirectory();

        if (!is_dir($dir)) {
            return [PaidAccessCore::DEFAULT_DOCUMENT_CONSENT_BLOCK_TEMPLATE => PaidAccessCore::DEFAULT_DOCUMENT_CONSENT_BLOCK_TEMPLATE];
        }

        foreach (glob($dir . '/template_*consent*.php') ?: [] as $filePath) {
            $name = basename($filePath);
            $templates[$name] = $name;
        }

        if ($templates === []) {
            $templates[PaidAccessCore::DEFAULT_DOCUMENT_CONSENT_BLOCK_TEMPLATE] = PaidAccessCore::DEFAULT_DOCUMENT_CONSENT_BLOCK_TEMPLATE;
        }

        ksort($templates);

        return $templates;
    }

    public static function isValidTemplateName(string $name): bool
    {
        return AccessTemplate::isValidTemplateName($name);
    }

    protected static function resolveTemplateFileName(?string $siteId = null): string
    {
        $template = PaidAccessCore::getDocumentConsentBlockTemplate($siteId);

        if ($template === '' || !self::isValidTemplateName($template)) {
            return PaidAccessCore::DEFAULT_DOCUMENT_CONSENT_BLOCK_TEMPLATE;
        }

        return $template;
    }
}

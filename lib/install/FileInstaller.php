<?php

namespace Zr\PaidAccess\Install;

use Bitrix\Main\Application;
use Zr\PaidAccess\PaidAccessCore;

class FileInstaller
{
    private const ACCESS_TEMPLATE_DIST_SUFFIX = '.dist';

    /**
     * @var array<string, string>
     */
    private const MANAGED_FILES = [
        '/admin/zr_paidaccess_subscribers.php' => '/bitrix/admin/zr_paidaccess_subscribers.php',
        '/admin/zr_paidaccess_payments.php' => '/bitrix/admin/zr_paidaccess_payments.php',
        '/admin/zr_paidaccess_payment_edit.php' => '/bitrix/admin/zr_paidaccess_payment_edit.php',
        '/admin/zr_paidaccess_funds.php' => '/bitrix/admin/zr_paidaccess_funds.php',
        '/admin/zr_paidaccess_fund_edit.php' => '/bitrix/admin/zr_paidaccess_fund_edit.php',
        '/admin/zr_paidaccess_fund_movement_edit.php' => '/bitrix/admin/zr_paidaccess_fund_movement_edit.php',
        '/admin/zr_paidaccess_fund_expense_view.php' => '/bitrix/admin/zr_paidaccess_fund_expense_view.php',
        '/admin/zr_paidaccess_documents.php' => '/bitrix/admin/zr_paidaccess_documents.php',
        '/admin/zr_paidaccess_document_edit.php' => '/bitrix/admin/zr_paidaccess_document_edit.php',
        '/admin/zr_paidaccess_document_version_edit.php' => '/bitrix/admin/zr_paidaccess_document_version_edit.php',
        '/admin/zr_paidaccess_utilities.php' => '/bitrix/admin/zr_paidaccess_utilities.php',
        '/admin/zr_paidaccess_util_document_iblock.php' => '/bitrix/admin/zr_paidaccess_util_document_iblock.php',
        '/admin/zr_paidaccess_util_tinkoff_init.php' => '/bitrix/admin/zr_paidaccess_util_tinkoff_init.php',
        '/admin/zr_paidaccess_gateways.php' => '/bitrix/admin/zr_paidaccess_gateways.php',
        '/admin/zr_paidaccess_gateway_edit.php' => '/bitrix/admin/zr_paidaccess_gateway_edit.php',
        '/admin/zr_paidaccess_gateway_import.php' => '/bitrix/admin/zr_paidaccess_gateway_import.php',
        '/admin/zr_paidaccess_logs.php' => '/bitrix/admin/zr_paidaccess_logs.php',
        '/themes/.default/zr.paidaccess.css' => '/bitrix/themes/.default/zr.paidaccess.css',
        '/components/zr/personal.subscription' => '/local/components/zr/personal.subscription',
        '/components/zr/member.payment.list' => '/local/components/zr/member.payment.list',
        '/components/zr/fund.wallet' => '/local/components/zr/fund.wallet',
        '/components/zr/document.consent' => '/local/components/zr/document.consent',
        '/components/zr/document.list' => '/local/components/zr/document.list',
        '/components/zr/panel' => '/local/components/zr/panel',
    ];

    /**
     * @return array<string, string>
     */
    public static function getManagedFiles(): array
    {
        return self::MANAGED_FILES;
    }

    /**
     * @return string[]
     */
    public static function ensureFiles(): array
    {
        return array_merge(
            self::copyManagedFiles(),
            self::ensureAccessTemplates()
        );
    }

    public static function uninstallFiles(): void
    {
        $documentRoot = Application::getDocumentRoot();
        foreach (self::MANAGED_FILES as $to) {
            DeleteDirFilesEx($documentRoot . $to);
        }
    }

    /**
     * @return string[]
     */
    private static function copyManagedFiles(): array
    {
        $documentRoot = Application::getDocumentRoot();
        $errors = [];

        foreach (self::MANAGED_FILES as $from => $to) {
            $sourcePath = dirname(__DIR__, 2) . '/install' . $from;
            $destPath = $documentRoot . $to;

            if (!CopyDirFiles($sourcePath, $destPath, true, true)) {
                $errors[] = $from . ':' . $to;
            }
        }

        return $errors;
    }

    /**
     * @return string[]
     */
    private static function ensureAccessTemplates(): array
    {
        $targetDir = Application::getDocumentRoot() . PaidAccessCore::TEMPLATES_RELATIVE_PATH;
        if (!is_dir($targetDir)) {
            CheckDirPath($targetDir . '/');
        }

        $errors = [];
        foreach (self::getAccessTemplateFiles() as $templateFile) {
            $sourceFile = dirname(__DIR__, 2) . '/install/templates/' . $templateFile;
            $targetFile = $targetDir . '/' . $templateFile;

            if (!is_file($sourceFile)) {
                $errors[] = 'missing-template:' . $templateFile;
                continue;
            }

            if (!is_file($targetFile)) {
                if (!copy($sourceFile, $targetFile)) {
                    $errors[] = 'template:' . $templateFile;
                }
                continue;
            }

            if (md5_file($sourceFile) !== md5_file($targetFile)) {
                $distFile = $targetFile . self::ACCESS_TEMPLATE_DIST_SUFFIX;
                if (!copy($sourceFile, $distFile)) {
                    $errors[] = 'template-dist:' . $templateFile;
                }
            }
        }

        return $errors;
    }

    /**
     * @return string[]
     */
    private static function getAccessTemplateFiles(): array
    {
        return [
            PaidAccessCore::DEFAULT_BLOCK_TEMPLATE,
            PaidAccessCore::DEFAULT_DOCUMENT_CONSENT_BLOCK_TEMPLATE,
        ];
    }
}

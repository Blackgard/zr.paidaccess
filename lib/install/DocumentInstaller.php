<?php

namespace Zr\PaidAccess\Install;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Tables\DocumentAcceptanceTable;
use Zr\PaidAccess\Tables\RequiredDocumentTable;
use Zr\PaidAccess\Tables\RequiredDocumentVersionTable;

class DocumentInstaller
{
    private const OPTION_SCHEMA_INDEXES = 'SCHEMA_DOCUMENT_INDEXES';

    public static function ensureSchema(): void
    {
        self::ensureIndexes();
    }

    private static function ensureIndexes(): void
    {
        if (Option::get(PaidAccessCore::MODULE_ID, self::OPTION_SCHEMA_INDEXES, 'N') === 'Y') {
            return;
        }

        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();

        $versionTable = RequiredDocumentVersionTable::getTableName();
        if ($connection->isTableExists($versionTable)) {
            $indexName = 'ux_zr_pa_doc_ver_doc_version';
            if (!self::indexExists($versionTable, $indexName)) {
                $sql = 'CREATE UNIQUE INDEX ' . $helper->quote($indexName)
                    . ' ON ' . $helper->quote($versionTable)
                    . ' (' . $helper->quote('DOCUMENT_ID') . ', ' . $helper->quote('VERSION') . ')';
                $connection->queryExecute($sql);
            }
        }

        $acceptanceTable = DocumentAcceptanceTable::getTableName();
        if ($connection->isTableExists($acceptanceTable)) {
            $uniqueName = 'ux_zr_pa_doc_acc_user_version';
            if (!self::indexExists($acceptanceTable, $uniqueName)) {
                $sql = 'CREATE UNIQUE INDEX ' . $helper->quote($uniqueName)
                    . ' ON ' . $helper->quote($acceptanceTable)
                    . ' (' . $helper->quote('USER_ID') . ', ' . $helper->quote('VERSION_ID') . ')';
                $connection->queryExecute($sql);
            }

            $historyName = 'ix_zr_pa_doc_acc_user_doc_date';
            if (!self::indexExists($acceptanceTable, $historyName)) {
                $sql = 'CREATE INDEX ' . $helper->quote($historyName)
                    . ' ON ' . $helper->quote($acceptanceTable)
                    . ' (' . $helper->quote('USER_ID') . ', ' . $helper->quote('DOCUMENT_ID') . ', ' . $helper->quote('DATE_ACCEPT') . ')';
                $connection->queryExecute($sql);
            }
        }

        Option::set(PaidAccessCore::MODULE_ID, self::OPTION_SCHEMA_INDEXES, 'Y');
    }

    private static function indexExists(string $table, string $indexName): bool
    {
        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();
        $sql = 'SHOW INDEX FROM ' . $helper->quote($table) . ' WHERE Key_name = \'' . $connection->getSqlHelper()->forSql($indexName) . '\'';
        $result = $connection->query($sql);

        return (bool)$result->fetch();
    }
}

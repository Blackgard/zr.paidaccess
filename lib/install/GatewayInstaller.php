<?php

namespace Zr\PaidAccess\Install;

use Bitrix\Main\Application;
use Zr\PaidAccess\Tables\GatewayTable;

class GatewayInstaller
{
    public static function ensureSchema(): void
    {
        $connection = Application::getConnection();
        $table = GatewayTable::getTableName();

        if (!$connection->isTableExists($table)) {
            return;
        }

        $helper = $connection->getSqlHelper();
        $existingFields = $connection->getTableFields($table);
        $columns = [
            'IS_TEST' => "CHAR(1) NOT NULL DEFAULT 'N'",
            'TEST_PASSED' => "CHAR(1) NOT NULL DEFAULT 'N'",
            'TEST_PASSED_AT' => 'DATETIME NULL',
            'TEST_MODULE_PAYMENT_ID' => 'INT NULL',
        ];

        foreach ($columns as $name => $definition) {
            if (array_key_exists($name, $existingFields)) {
                continue;
            }

            $sql = 'ALTER TABLE ' . $helper->quote($table)
                . ' ADD ' . $helper->quote($name) . ' ' . $definition;
            $connection->queryExecute($sql);
            $existingFields[$name] = true;
        }
    }
}

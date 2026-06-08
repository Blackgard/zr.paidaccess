<?php

namespace Zr\PaidAccess\Install;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Tables\GatewayTransactionTable;

class GatewayTransactionInstaller
{
    private const OPTION_SCHEMA_GATEWAY_ID = 'SCHEMA_GATEWAY_TX_GATEWAY_ID';

    public static function ensureSchema(): void
    {
        $connection = Application::getConnection();
        $table = GatewayTransactionTable::getTableName();

        if (!$connection->isTableExists($table)) {
            return;
        }

        if (Option::get(PaidAccessCore::MODULE_ID, self::OPTION_SCHEMA_GATEWAY_ID, 'N') === 'Y') {
            return;
        }

        $helper = $connection->getSqlHelper();
        $fields = $connection->getTableFields($table);

        if (!isset($fields['GATEWAY_ID'])) {
            $sql = 'ALTER TABLE ' . $helper->quote($table)
                . ' ADD ' . $helper->quote('GATEWAY_ID') . ' INT NULL DEFAULT 0';
            $connection->queryExecute($sql);
        }

        Option::set(PaidAccessCore::MODULE_ID, self::OPTION_SCHEMA_GATEWAY_ID, 'Y');
    }
}

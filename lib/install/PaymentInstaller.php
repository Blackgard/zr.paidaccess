<?php

namespace Zr\PaidAccess\Install;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Tables\PaymentTable;

class PaymentInstaller
{
    private const OPTION_SCHEMA_PERIOD = 'SCHEMA_PAYMENT_BILLING_PERIOD_16';
    private const OPTION_SCHEMA_PAYMENT_URL = 'SCHEMA_PAYMENT_GATEWAY_URL_512';

    public static function ensureSchema(): void
    {
        $connection = Application::getConnection();
        $table = PaymentTable::getTableName();

        if (!$connection->isTableExists($table)) {
            return;
        }

        $helper = $connection->getSqlHelper();

        if (Option::get(PaidAccessCore::MODULE_ID, self::OPTION_SCHEMA_PERIOD, 'N') !== 'Y') {
            $sql = 'ALTER TABLE ' . $helper->quote($table)
                . ' MODIFY ' . $helper->quote('BILLING_PERIOD') . ' VARCHAR(16) NULL';

            $connection->queryExecute($sql);
            Option::set(PaidAccessCore::MODULE_ID, self::OPTION_SCHEMA_PERIOD, 'Y');
        }

        if (Option::get(PaidAccessCore::MODULE_ID, self::OPTION_SCHEMA_PAYMENT_URL, 'N') === 'Y') {
            return;
        }

        if (!$connection->isTableExists($table)) {
            return;
        }

        $fields = $connection->getTableFields($table);
        if (!isset($fields['GATEWAY_PAYMENT_URL'])) {
            $sql = 'ALTER TABLE ' . $helper->quote($table)
                . ' ADD ' . $helper->quote('GATEWAY_PAYMENT_URL') . ' VARCHAR(512) NULL';

            $connection->queryExecute($sql);
        }

        Option::set(PaidAccessCore::MODULE_ID, self::OPTION_SCHEMA_PAYMENT_URL, 'Y');
    }
}

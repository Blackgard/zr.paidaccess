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
    private const OPTION_SCHEMA_AMOUNT_BREAKDOWN = 'SCHEMA_PAYMENT_AMOUNT_BREAKDOWN';

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

        if (Option::get(PaidAccessCore::MODULE_ID, self::OPTION_SCHEMA_PAYMENT_URL, 'N') !== 'Y') {
            $fields = $connection->getTableFields($table);
            if (!isset($fields['GATEWAY_PAYMENT_URL'])) {
                $sql = 'ALTER TABLE ' . $helper->quote($table)
                    . ' ADD ' . $helper->quote('GATEWAY_PAYMENT_URL') . ' VARCHAR(512) NULL';

                $connection->queryExecute($sql);
            }

            Option::set(PaidAccessCore::MODULE_ID, self::OPTION_SCHEMA_PAYMENT_URL, 'Y');
        }

        if (Option::get(PaidAccessCore::MODULE_ID, self::OPTION_SCHEMA_AMOUNT_BREAKDOWN, 'N') === 'Y') {
            return;
        }

        $fields = $connection->getTableFields($table);
        foreach (['FUND_AMOUNT', 'TAX_AMOUNT', 'MAINTENANCE_AMOUNT'] as $column) {
            if (!isset($fields[$column])) {
                $sql = 'ALTER TABLE ' . $helper->quote($table)
                    . ' ADD ' . $helper->quote($column) . ' DOUBLE NULL';

                $connection->queryExecute($sql);
            }
        }

        self::backfillPaymentAmountBreakdown();

        Option::set(PaidAccessCore::MODULE_ID, self::OPTION_SCHEMA_AMOUNT_BREAKDOWN, 'Y');
    }

    private static function backfillPaymentAmountBreakdown(): void
    {
        $result = PaymentTable::getList([
            'select' => ['ID', 'AMOUNT', 'FUND_AMOUNT'],
        ]);

        while ($row = $result->fetch()) {
            $fund = $row['FUND_AMOUNT'] ?? null;
            if ($fund !== null && $fund !== '' && (float)$fund > 0) {
                continue;
            }

            $amount = (float)($row['AMOUNT'] ?? 0);
            PaymentTable::update((int)$row['ID'], [
                'FUND_AMOUNT' => $amount,
                'TAX_AMOUNT' => 0.0,
                'MAINTENANCE_AMOUNT' => 0.0,
            ]);
        }
    }
}

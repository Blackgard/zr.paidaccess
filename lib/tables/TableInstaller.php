<?php

namespace Zr\PaidAccess\Tables;

use Bitrix\Main\Application;
use Bitrix\Main\ORM\Data\DataManager;
use Zr\PaidAccess\Install\GatewayInstaller;
use Zr\PaidAccess\Install\GatewayTransactionInstaller;
use Zr\PaidAccess\Install\PaymentInstaller;

class TableInstaller
{
    /**
     * Для обновления модуля без переустановки: создать таблицу шлюзов, если её ещё нет.
     */
    public static function ensureGatewayTable(): void
    {
        self::createTableIfNotExists(GatewayTable::class);
        GatewayInstaller::ensureSchema();
    }

    public static function ensurePaymentTable(): void
    {
        self::createTableIfNotExists(PaymentTable::class);
        PaymentInstaller::ensureSchema();
    }

    public static function ensureFundTables(): void
    {
        self::createTableIfNotExists(FundTable::class);
        self::createTableIfNotExists(FundMovementTable::class);
    }

    public static function install(): void
    {
        self::createTableIfNotExists(GatewayTable::class);
        GatewayInstaller::ensureSchema();
        self::createTableIfNotExists(PaymentTable::class);
        PaymentInstaller::ensureSchema();
        self::createTableIfNotExists(GatewayTransactionTable::class);
        GatewayTransactionInstaller::ensureSchema();
        self::createTableIfNotExists(SubscriptionTable::class);
        self::ensureFundTables();
    }

    public static function uninstall(): void
    {
        $connection = Application::getConnection();

        if ($connection->isTableExists(GatewayTransactionTable::getTableName())) {
            $connection->dropTable(GatewayTransactionTable::getTableName());
        }

        if ($connection->isTableExists(PaymentTable::getTableName())) {
            $connection->dropTable(PaymentTable::getTableName());
        }

        if ($connection->isTableExists(GatewayTable::getTableName())) {
            $connection->dropTable(GatewayTable::getTableName());
        }

        if ($connection->isTableExists(SubscriptionTable::getTableName())) {
            $connection->dropTable(SubscriptionTable::getTableName());
        }

        if ($connection->isTableExists(FundMovementTable::getTableName())) {
            $connection->dropTable(FundMovementTable::getTableName());
        }

        if ($connection->isTableExists(FundTable::getTableName())) {
            $connection->dropTable(FundTable::getTableName());
        }
    }

    /**
     * @param class-string<DataManager> $dataClass
     */
    private static function createTableIfNotExists($dataClass): void
    {
        $connection = Application::getConnection();
        $tableName = $dataClass::getTableName();

        if ($connection->isTableExists($tableName)) {
            return;
        }

        $dataClass::getEntity()->createDbTable();
    }
}

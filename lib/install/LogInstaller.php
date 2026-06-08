<?php

namespace Zr\PaidAccess\Install;

use Bitrix\Main\Application;
use Zr\PaidAccess\Tables\AuditLogTable;
use Zr\PaidAccess\Tables\EventLogTable;
use Zr\PaidAccess\Tables\NotificationLogTable;

class LogInstaller
{
    public static function ensureTables(): void
    {
        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();

        if (!$connection->isTableExists(EventLogTable::getTableName())) {
            $connection->queryExecute(
                'CREATE TABLE ' . $sqlHelper->quote(EventLogTable::getTableName()) . ' (
                    ID INT NOT NULL AUTO_INCREMENT,
                    LEVEL VARCHAR(16) NOT NULL,
                    CODE VARCHAR(64) NOT NULL,
                    MESSAGE TEXT NOT NULL,
                    CONTEXT TEXT NULL,
                    PAYMENT_ID INT NULL,
                    USER_ID INT NULL,
                    DATE_CREATE DATETIME NOT NULL,
                    PRIMARY KEY (ID),
                    KEY IX_ZR_PA_EVENT_LEVEL (LEVEL),
                    KEY IX_ZR_PA_EVENT_DATE (DATE_CREATE),
                    KEY IX_ZR_PA_EVENT_PAYMENT (PAYMENT_ID)
                )'
            );
        }

        if (!$connection->isTableExists(AuditLogTable::getTableName())) {
            $connection->queryExecute(
                'CREATE TABLE ' . $sqlHelper->quote(AuditLogTable::getTableName()) . ' (
                    ID INT NOT NULL AUTO_INCREMENT,
                    ENTITY_TYPE VARCHAR(32) NOT NULL,
                    ENTITY_ID INT NOT NULL,
                    ACTION VARCHAR(64) NOT NULL,
                    OLD_VALUE TEXT NULL,
                    NEW_VALUE TEXT NULL,
                    ADMIN_USER_ID INT NULL,
                    IP VARCHAR(45) NULL,
                    MESSAGE TEXT NULL,
                    DATE_CREATE DATETIME NOT NULL,
                    PRIMARY KEY (ID),
                    KEY IX_ZR_PA_AUDIT_ENTITY (ENTITY_TYPE, ENTITY_ID),
                    KEY IX_ZR_PA_AUDIT_DATE (DATE_CREATE)
                )'
            );
        }

        if (!$connection->isTableExists(NotificationLogTable::getTableName())) {
            $connection->queryExecute(
                'CREATE TABLE ' . $sqlHelper->quote(NotificationLogTable::getTableName()) . ' (
                    ID INT NOT NULL AUTO_INCREMENT,
                    USER_ID INT NOT NULL,
                    NOTIFY_TYPE VARCHAR(32) NOT NULL,
                    CONTEXT_KEY VARCHAR(64) NOT NULL,
                    DATE_CREATE DATETIME NOT NULL,
                    PRIMARY KEY (ID),
                    UNIQUE KEY UX_ZR_PA_NOTIFY (USER_ID, NOTIFY_TYPE, CONTEXT_KEY)
                )'
            );
        }
    }

    public static function dropTables(): void
    {
        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();

        foreach ([
            NotificationLogTable::getTableName(),
            AuditLogTable::getTableName(),
            EventLogTable::getTableName(),
        ] as $table) {
            if ($connection->isTableExists($table)) {
                $connection->queryExecute('DROP TABLE ' . $sqlHelper->quote($table));
            }
        }
    }
}

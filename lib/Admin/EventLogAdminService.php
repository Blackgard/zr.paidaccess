<?php

namespace Zr\PaidAccess\Admin;

use Bitrix\Main\Type\DateTime;
use Zr\PaidAccess\Enum\ModuleLogLevel;
use Zr\PaidAccess\Tables\AuditLogTable;
use Zr\PaidAccess\Tables\EventLogTable;

class EventLogAdminService
{
  /**
     * @return array<int, string>
     */
    public static function getLevelTitles(): array
    {
        return [
            ModuleLogLevel::DEBUG => 'Debug',
            ModuleLogLevel::INFO => 'Info',
            ModuleLogLevel::WARNING => 'Warning',
            ModuleLogLevel::ERROR => 'Ошибка',
        ];
    }

    /**
     * @param array<string, mixed> $filter
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public static function getEventLogRows(array $filter, int $limit, int $offset, array $order = ['ID' => 'DESC']): array
    {
        $ormFilter = self::buildEventFilter($filter);

        $total = (int)EventLogTable::getCount($ormFilter);
        $rows = [];

        $result = EventLogTable::getList([
            'filter' => $ormFilter,
            'order' => $order,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        while ($row = $result->fetch()) {
            $rows[] = self::formatEventRow($row);
        }

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * @param array<string, mixed> $filter
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public static function getAuditLogRows(array $filter, int $limit, int $offset, array $order = ['ID' => 'DESC']): array
    {
        $ormFilter = self::buildAuditFilter($filter);

        $total = (int)AuditLogTable::getCount($ormFilter);
        $rows = [];

        $result = AuditLogTable::getList([
            'filter' => $ormFilter,
            'order' => $order,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        while ($row = $result->fetch()) {
            $rows[] = self::formatAuditRow($row);
        }

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * @param array<string, mixed> $filter
     */
    public static function clearEventLog(array $filter): int
    {
        return LogCleanupAdminService::deleteByFilter(EventLogTable::class, self::buildEventFilter($filter));
    }

    /**
     * @param array<string, mixed> $filter
     */
    public static function clearAuditLog(array $filter): int
    {
        return LogCleanupAdminService::deleteByFilter(AuditLogTable::class, self::buildAuditFilter($filter));
    }

    /**
     * @param array<string, mixed> $filter
     * @return array<string, mixed>
     */
    protected static function buildEventFilter(array $filter): array
    {
        $ormFilter = [];

        if (!empty($filter['LEVEL'])) {
            $ormFilter['=LEVEL'] = (string)$filter['LEVEL'];
        }
        if (!empty($filter['CODE'])) {
            $ormFilter['%CODE'] = (string)$filter['CODE'];
        }
        if (!empty($filter['PAYMENT_ID'])) {
            $ormFilter['=PAYMENT_ID'] = (int)$filter['PAYMENT_ID'];
        }
        if (!empty($filter['USER_ID'])) {
            $ormFilter['=USER_ID'] = (int)$filter['USER_ID'];
        }

        return $ormFilter;
    }

    /**
     * @param array<string, mixed> $filter
     * @return array<string, mixed>
     */
    protected static function buildAuditFilter(array $filter): array
    {
        $ormFilter = [];

        if (!empty($filter['ENTITY_TYPE'])) {
            $ormFilter['=ENTITY_TYPE'] = (string)$filter['ENTITY_TYPE'];
        }
        if (!empty($filter['ENTITY_ID'])) {
            $ormFilter['=ENTITY_ID'] = (int)$filter['ENTITY_ID'];
        }
        if (!empty($filter['ACTION'])) {
            $ormFilter['%ACTION'] = (string)$filter['ACTION'];
        }

        return $ormFilter;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected static function formatEventRow(array $row): array
    {
        $dateCreate = $row['DATE_CREATE'] ?? null;
        if ($dateCreate instanceof DateTime) {
            $dateCreate = $dateCreate->toString();
        }

        $context = (string)($row['CONTEXT'] ?? '');
        if ($context !== '' && strlen($context) > 200) {
            $context = mb_substr($context, 0, 200) . '…';
        }

        return [
            'ID' => (int)$row['ID'],
            'LEVEL' => (string)$row['LEVEL'],
            'CODE' => (string)$row['CODE'],
            'MESSAGE' => (string)$row['MESSAGE'],
            'CONTEXT' => $context,
            'PAYMENT_ID' => (int)($row['PAYMENT_ID'] ?? 0),
            'USER_ID' => (int)($row['USER_ID'] ?? 0),
            'DATE_CREATE' => (string)$dateCreate,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected static function formatAuditRow(array $row): array
    {
        $dateCreate = $row['DATE_CREATE'] ?? null;
        if ($dateCreate instanceof DateTime) {
            $dateCreate = $dateCreate->toString();
        }

        return [
            'ID' => (int)$row['ID'],
            'ENTITY_TYPE' => (string)$row['ENTITY_TYPE'],
            'ENTITY_ID' => (int)$row['ENTITY_ID'],
            'ACTION' => (string)$row['ACTION'],
            'OLD_VALUE' => (string)($row['OLD_VALUE'] ?? ''),
            'NEW_VALUE' => (string)($row['NEW_VALUE'] ?? ''),
            'MESSAGE' => (string)($row['MESSAGE'] ?? ''),
            'ADMIN_USER_ID' => (int)($row['ADMIN_USER_ID'] ?? 0),
            'IP' => (string)($row['IP'] ?? ''),
            'DATE_CREATE' => (string)$dateCreate,
        ];
    }
}

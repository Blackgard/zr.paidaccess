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
     * @return list<array<string, mixed>>
     */
    public static function exportEventRows(array $filter, int $limit = LogExportAdminService::EXPORT_LIMIT): array
    {
        return self::fetchEventRows($filter, $limit);
    }

    /**
     * @param array<string, mixed> $filter
     * @return list<array<string, mixed>>
     */
    public static function exportAuditRows(array $filter, int $limit = LogExportAdminService::EXPORT_LIMIT): array
    {
        return self::fetchAuditRows($filter, $limit);
    }

    /**
     * @param array<string, mixed> $filter
     * @return list<array<string, mixed>>
     */
    protected static function fetchEventRows(array $filter, int $limit): array
    {
        $rows = [];
        $result = EventLogTable::getList([
            'filter' => self::buildEventFilter($filter),
            'order' => ['ID' => 'DESC'],
            'limit' => max(1, $limit),
        ]);

        while ($row = $result->fetch()) {
            if (!is_array($row)) {
                continue;
            }

            $formatted = self::formatEventRow($row);
            $formatted['context'] = self::decodeJsonField((string)($formatted['CONTEXT'] ?? ''));
            unset($formatted['CONTEXT']);
            $rows[] = $formatted;
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $filter
     * @return list<array<string, mixed>>
     */
    protected static function fetchAuditRows(array $filter, int $limit): array
    {
        $rows = [];
        $result = AuditLogTable::getList([
            'filter' => self::buildAuditFilter($filter),
            'order' => ['ID' => 'DESC'],
            'limit' => max(1, $limit),
        ]);

        while ($row = $result->fetch()) {
            if (!is_array($row)) {
                continue;
            }

            $formatted = self::formatAuditRow($row);
            $formatted['oldValue'] = self::decodeJsonField((string)($formatted['OLD_VALUE'] ?? ''));
            $formatted['newValue'] = self::decodeJsonField((string)($formatted['NEW_VALUE'] ?? ''));
            unset($formatted['OLD_VALUE'], $formatted['NEW_VALUE']);
            $rows[] = $formatted;
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|list<mixed>|string|null
     */
    protected static function decodeJsonField(string $raw)
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
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

        return [
            'ID' => (int)$row['ID'],
            'LEVEL' => (string)$row['LEVEL'],
            'CODE' => (string)$row['CODE'],
            'MESSAGE' => (string)$row['MESSAGE'],
            'CONTEXT' => (string)($row['CONTEXT'] ?? ''),
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

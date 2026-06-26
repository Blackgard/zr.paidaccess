<?php

namespace Zr\PaidAccess\Admin;

use Zr\PaidAccess\Enum\GatewayEventType;
use Zr\PaidAccess\Tables\GatewayTransactionTable;

class GatewayTransactionAdminService
{
    /**
     * @return array<string, string>
     */
    public static function getEventTypeTitles(): array
    {
        return [
            GatewayEventType::INIT => 'Init',
            GatewayEventType::GET_QR => 'GetQr',
            GatewayEventType::STATUS_CHECK => 'GetState',
            GatewayEventType::WEBHOOK => 'Webhook',
            GatewayEventType::REFUND => 'Возврат',
            GatewayEventType::CANCEL => 'Отмена',
            GatewayEventType::ADMIN_MANUAL => 'Админка',
            GatewayEventType::RECEIPT_EMAIL => 'Чек (email)',
            GatewayEventType::RECEIPT_FISCAL_GATEWAY => 'Чек (шлюз)',
        ];
    }

    public static function eventTypeTitle(string $eventType): string
    {
        $eventType = strtolower(trim($eventType));

        return self::getEventTypeTitles()[$eventType] ?? $eventType;
    }

    /**
     * @param array<string, mixed> $filter
     * @return list<array<string, mixed>>
     */
    public static function exportRows(array $filter, int $limit = LogExportAdminService::EXPORT_LIMIT): array
    {
        $ormFilter = self::buildFilter($filter);
        $rows = [];

        $result = GatewayTransactionTable::getList([
            'filter' => $ormFilter,
            'order' => ['ID' => 'DESC'],
            'limit' => max(1, $limit),
        ]);

        while ($row = $result->fetch()) {
            if (!is_array($row)) {
                continue;
            }

            $formatted = self::formatRow($row);
            $formatted['request'] = self::decodeJsonField((string)($formatted['REQUEST_DATA'] ?? ''));
            $formatted['response'] = self::decodeJsonField((string)($formatted['RESPONSE_DATA'] ?? ''));
            unset($formatted['REQUEST_DATA'], $formatted['RESPONSE_DATA']);
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
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public static function getRows(array $filter, int $limit, int $offset, array $order = ['ID' => 'DESC']): array
    {
        $ormFilter = self::buildFilter($filter);
        $total = (int)GatewayTransactionTable::getCount($ormFilter);
        $rows = [];

        $result = GatewayTransactionTable::getList([
            'filter' => $ormFilter,
            'order' => $order,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        while ($row = $result->fetch()) {
            $rows[] = self::formatRow(is_array($row) ? $row : []);
        }

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * @param array<string, mixed> $filter
     */
    public static function clearLog(array $filter): int
    {
        return LogCleanupAdminService::deleteByFilter(GatewayTransactionTable::class, self::buildFilter($filter));
    }

    /**
     * @param array<string, mixed> $filter
     * @return array<string, mixed>
     */
    protected static function buildFilter(array $filter): array
    {
        $ormFilter = [];

        if (!empty($filter['GATEWAY_CODE'])) {
            $ormFilter['=GATEWAY_CODE'] = (string)$filter['GATEWAY_CODE'];
        }

        if (!empty($filter['GATEWAY_ID'])) {
            $ormFilter['=GATEWAY_ID'] = (int)$filter['GATEWAY_ID'];
        }

        if (!empty($filter['EVENT_TYPE'])) {
            $ormFilter['=EVENT_TYPE'] = (string)$filter['EVENT_TYPE'];
        }

        if (!empty($filter['PAYMENT_ID'])) {
            $ormFilter['=PAYMENT_ID'] = (int)$filter['PAYMENT_ID'];
        }

        if (!empty($filter['SUCCESS'])) {
            $ormFilter['=SUCCESS'] = (string)$filter['SUCCESS'] === 'Y' ? 'Y' : 'N';
        }

        return $ormFilter;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected static function formatRow(array $row): array
    {
        $dateCreate = $row['DATE_CREATE'] ?? null;
        if ($dateCreate instanceof \Bitrix\Main\Type\DateTime) {
            $dateCreate = $dateCreate->format('d.m.Y H:i:s');
        }

        return [
            'ID' => (int)($row['ID'] ?? 0),
            'DATE_CREATE' => (string)$dateCreate,
            'GATEWAY_CODE' => (string)($row['GATEWAY_CODE'] ?? ''),
            'GATEWAY_ID' => (int)($row['GATEWAY_ID'] ?? 0),
            'EVENT_TYPE' => (string)($row['EVENT_TYPE'] ?? ''),
            'PAYMENT_ID' => (int)($row['PAYMENT_ID'] ?? 0),
            'GATEWAY_STATUS' => (string)($row['GATEWAY_STATUS'] ?? ''),
            'INTERNAL_STATUS' => (string)($row['INTERNAL_STATUS'] ?? ''),
            'HTTP_CODE' => (int)($row['HTTP_CODE'] ?? 0),
            'SUCCESS' => (string)($row['SUCCESS'] ?? 'N'),
            'ERROR_MESSAGE' => (string)($row['ERROR_MESSAGE'] ?? ''),
            'REQUEST_DATA' => (string)($row['REQUEST_DATA'] ?? ''),
            'RESPONSE_DATA' => (string)($row['RESPONSE_DATA'] ?? ''),
        ];
    }
}

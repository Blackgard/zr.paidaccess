<?php

namespace Zr\PaidAccess\Admin;

class LogExportAdminService
{
    public const EXPORT_LIMIT = 5000;

    /**
     * @param array<string, mixed> $filter
     */
    public static function exportToJson(string $tab, array $filter): string
    {
        $rows = self::loadRows($tab, $filter);

        $payload = [
            'module' => 'zr.paidaccess',
            'tab' => $tab,
            'exportedAt' => date('c'),
            'filter' => $filter,
            'limit' => self::EXPORT_LIMIT,
            'exportedCount' => count($rows),
            'rows' => $rows,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return $json !== false ? $json : '{}';
    }

    public static function buildFileName(string $tab): string
    {
        return 'zr-paidaccess-logs-' . $tab . '-' . date('Y-m-d_His') . '.json';
    }

    /**
     * @param array<string, mixed> $filter
     * @return list<array<string, mixed>>
     */
    protected static function loadRows(string $tab, array $filter): array
    {
        if ($tab === 'gateway') {
            return GatewayTransactionAdminService::exportRows($filter, self::EXPORT_LIMIT);
        }

        if ($tab === 'audit') {
            return EventLogAdminService::exportAuditRows($filter, self::EXPORT_LIMIT);
        }

        return EventLogAdminService::exportEventRows($filter, self::EXPORT_LIMIT);
    }
}

<?php

namespace Zr\PaidAccess\Log;

use Bitrix\Main\Context;
use Bitrix\Main\Type\DateTime;
use Zr\PaidAccess\Tables\AuditLogTable;

/**
 * Аудит ручных правок в админке (F8).
 */
class AuditLogService
{
    public static function log(
        string $entityType,
        int $entityId,
        string $action,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?string $message = null,
        ?int $adminUserId = null
    ): void {
        global $USER;

        if ($adminUserId === null && is_object($USER) && $USER->IsAuthorized()) {
            $adminUserId = (int)$USER->GetID();
        }

        $request = Context::getCurrent()->getRequest();
        $ip = (string)$request->getRemoteAddress();

        try {
            AuditLogTable::add([
                'ENTITY_TYPE' => mb_substr($entityType, 0, 32),
                'ENTITY_ID' => $entityId,
                'ACTION' => mb_substr($action, 0, 64),
                'OLD_VALUE' => $oldValue,
                'NEW_VALUE' => $newValue,
                'ADMIN_USER_ID' => $adminUserId,
                'IP' => $ip !== '' ? mb_substr($ip, 0, 45) : null,
                'MESSAGE' => $message,
            ]);
        } catch (\Throwable $e) {
            ModuleEventLogService::warning(
                'audit_log_failed',
                'Не удалось записать аудит: ' . $e->getMessage(),
                ['entityType' => $entityType, 'entityId' => $entityId]
            );
        }
    }

    /**
     * Сериализация снимка сущности для аудита (даты и объекты → строки).
     *
     * @param array<string, mixed>|null $row
     */
    public static function encodeSnapshot(?array $row): ?string
    {
        if ($row === null) {
            return null;
        }

        $normalized = self::normalizeRow($row);
        $json = json_encode($normalized, JSON_UNESCAPED_UNICODE);

        return $json === false ? null : $json;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function normalizeRow(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            if (is_array($value)) {
                $normalized[$key] = self::normalizeRow($value);
                continue;
            }

            if ($value instanceof DateTime) {
                $normalized[$key] = method_exists($value, 'toString')
                    ? $value->toString()
                    : $value->format('Y-m-d H:i:s');
                continue;
            }

            if ($value instanceof \DateTimeInterface) {
                $normalized[$key] = $value->format('Y-m-d H:i:s');
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }
}

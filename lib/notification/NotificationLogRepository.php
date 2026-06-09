<?php

namespace Zr\PaidAccess\Notification;

use Zr\PaidAccess\Tables\NotificationLogTable;

class NotificationLogRepository
{
    /** Системный USER_ID для админских уведомлений (не привязан к пользователю сайта). */
    public const ADMIN_USER_ID = 0;

    public static function wasSent(int $userId, string $notifyType, string $contextKey): bool
    {
        $row = NotificationLogTable::getList([
            'filter' => [
                '=USER_ID' => $userId,
                '=NOTIFY_TYPE' => $notifyType,
                '=CONTEXT_KEY' => $contextKey,
            ],
            'select' => ['ID'],
            'limit' => 1,
        ])->fetch();

        return (bool)$row;
    }

    public static function markSent(int $userId, string $notifyType, string $contextKey): void
    {
        if (self::wasSent($userId, $notifyType, $contextKey)) {
            return;
        }

        try {
            NotificationLogTable::add([
                'USER_ID' => $userId,
                'NOTIFY_TYPE' => $notifyType,
                'CONTEXT_KEY' => $contextKey,
            ]);
        } catch (\Throwable $e) {
            // duplicate race — ignore
        }
    }
}

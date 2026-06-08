<?php

namespace Zr\PaidAccess\Log;

use Bitrix\Main\Mail\Event;
use Zr\PaidAccess\Enum\ModuleLogLevel;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Tables\EventLogTable;
use Zr\PaidAccess\Tools\Logger;

/**
 * Журнал событий модуля (F8): ошибки оплаты, webhook, инициализация шлюза.
 */
class ModuleEventLogService
{
    public static function debug(
        string $code,
        string $message,
        array $context = [],
        ?int $paymentId = null,
        ?int $userId = null,
        ?string $siteId = null
    ): void {
        self::log(ModuleLogLevel::DEBUG, $code, $message, $context, $paymentId, $userId, $siteId);
    }

    public static function info(
        string $code,
        string $message,
        array $context = [],
        ?int $paymentId = null,
        ?int $userId = null,
        ?string $siteId = null
    ): void {
        self::log(ModuleLogLevel::INFO, $code, $message, $context, $paymentId, $userId, $siteId);
    }

    public static function warning(
        string $code,
        string $message,
        array $context = [],
        ?int $paymentId = null,
        ?int $userId = null,
        ?string $siteId = null
    ): void {
        self::log(ModuleLogLevel::WARNING, $code, $message, $context, $paymentId, $userId, $siteId);
    }

    public static function error(
        string $code,
        string $message,
        array $context = [],
        ?int $paymentId = null,
        ?int $userId = null,
        ?string $siteId = null
    ): void {
        self::log(ModuleLogLevel::ERROR, $code, $message, $context, $paymentId, $userId, $siteId);
    }

    public static function log(
        string $level,
        string $code,
        string $message,
        array $context = [],
        ?int $paymentId = null,
        ?int $userId = null,
        ?string $siteId = null
    ): void {
        $level = strtolower($level);
        if (!in_array($level, ModuleLogLevel::ALL, true)) {
            $level = ModuleLogLevel::INFO;
        }

        $contextJson = $context !== [] ? json_encode($context, JSON_UNESCAPED_UNICODE) : null;

        if (PaidAccessCore::isLoggingActive($siteId) || $level === ModuleLogLevel::ERROR || $level === ModuleLogLevel::WARNING) {
            try {
                EventLogTable::add([
                    'LEVEL' => $level,
                    'CODE' => mb_substr($code, 0, 64),
                    'MESSAGE' => $message,
                    'CONTEXT' => $contextJson,
                    'PAYMENT_ID' => $paymentId,
                    'USER_ID' => $userId,
                ]);
            } catch (\Throwable $e) {
                // fallback to file only
            }
        }

        $loggerContext = array_merge($context, array_filter([
            'paymentId' => $paymentId,
            'userId' => $userId,
        ]));

        if ($level === ModuleLogLevel::DEBUG) {
            Logger::debug($message, $loggerContext, $code, $siteId);
        } elseif ($level === ModuleLogLevel::INFO) {
            Logger::info($message, $loggerContext, $code, $siteId);
        } elseif ($level === ModuleLogLevel::WARNING) {
            Logger::warning($message, $loggerContext, $code, $siteId);
        } else {
            Logger::error($message, $loggerContext, $code, $siteId);
        }

        if ($level === ModuleLogLevel::ERROR) {
            self::notifyAdminOnError($code, $message, $contextJson, $paymentId, $userId, $siteId);
        }
    }

    protected static function notifyAdminOnError(
        string $code,
        string $message,
        ?string $contextJson,
        ?int $paymentId,
        ?int $userId,
        ?string $siteId
    ): void {
        if (!PaidAccessCore::isErrorNotifyEnabled($siteId)) {
            return;
        }

        $emails = PaidAccessCore::getErrorNotifyEmails($siteId);
        if ($emails === '') {
            return;
        }

        Event::send([
            'EVENT_NAME' => PaidAccessCore::MAIL_EVENT_ADMIN_ERROR,
            'LID' => PaidAccessCore::normalizeSiteId($siteId),
            'C_FIELDS' => PaidAccessCore::enrichMailFields([
                'EMAIL' => $emails,
                'ERROR_CODE' => $code,
                'ERROR_MESSAGE' => $message,
                'CONTEXT' => $contextJson ?? '',
                'PAYMENT_ID' => $paymentId ? (string)$paymentId : '',
                'USER_ID' => $userId ? (string)$userId : '',
                'DATE' => date('d.m.Y H:i:s'),
            ], $siteId),
        ]);
    }
}

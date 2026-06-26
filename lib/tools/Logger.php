<?php

/**
 * @link https://alexandr-zr.ru/
 * @author Alexandr Drachenin <alexdrachenin98@gmail.com>
 * @copyright Copyright (c) 2020-2026 ZR studio (https://alexandr-zr.ru/)
 */

namespace Zr\PaidAccess\Tools;

use Zr\PaidAccess\PaidAccessCore;

class Logger
{
    private const LEVEL_DEBUG = 'debug';
    private const LEVEL_INFO = 'info';
    private const LEVEL_WARNING = 'warning';
    private const LEVEL_ERROR = 'error';

    private const MAX_BODY_LENGTH = 8000;

    /** @var array<string, int> */
    private static array $levelPriority = [
        self::LEVEL_DEBUG => 0,
        self::LEVEL_INFO => 1,
        self::LEVEL_WARNING => 2,
        self::LEVEL_ERROR => 3,
    ];

    public static function isEnabled(?string $siteId = null): bool
    {
        return PaidAccessCore::isLoggingActive($siteId);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function debug(string $message, array $context = [], ?string $category = null, ?string $siteId = null): void
    {
        self::write(self::LEVEL_DEBUG, $message, $context, $category, $siteId);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function info(string $message, array $context = [], ?string $category = null, ?string $siteId = null): void
    {
        self::write(self::LEVEL_INFO, $message, $context, $category, $siteId);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function warning(string $message, array $context = [], ?string $category = null, ?string $siteId = null): void
    {
        self::write(self::LEVEL_WARNING, $message, $context, $category, $siteId);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function error(string $message, array $context = [], ?string $category = null, ?string $siteId = null): void
    {
        self::write(self::LEVEL_ERROR, $message, $context, $category, $siteId);
    }

    /**
     * @param array<string, mixed> $requestParams
     * @param array<string, mixed>|string|null $response
     * @param array<string, mixed> $extra
     */
    public static function logHttpExchange(
        string $provider,
        string $method,
        string $url,
        array $requestParams,
        $response,
        int $httpStatus,
        float $durationMs,
        $httpError = null,
        array $extra = [],
        ?string $siteId = null
    ): void {
        if (!self::isEnabled($siteId)) {
            return;
        }

        $level = self::LEVEL_INFO;
        if ($httpStatus >= 400 || self::normalizeHttpError($httpError) !== '') {
            $level = $httpStatus >= 500 ? self::LEVEL_ERROR : self::LEVEL_WARNING;
        }

        $responseBody = is_array($response)
            ? $response
            : ['raw' => self::truncate((string)$response)];

        $context = array_merge([
            'method' => $method,
            'url' => $url,
            'httpStatus' => $httpStatus,
            'httpCode' => $httpStatus,
            'durationMs' => round($durationMs, 2),
            'httpError' => self::normalizeHttpError($httpError),
            'request' => self::sanitizeParams($requestParams),
            'response' => self::sanitizeParams($responseBody),
            'environment' => self::collectEnvironmentContext(),
        ], $extra);

        if ($httpStatus === 403) {
            $isTestMode = !empty($extra['testMode']);
            $context['hint'] = $isTestMode
                ? 'HTTP 403 от rest-api-test.tinkoff.ru: тестовая среда T-Bank доступна только после добавления IP в whitelist (openapi@tbank.ru). '
                    . 'Либо отключите тестовый режим шлюза и используйте боевой терминал на securepay.tinkoff.ru.'
                : 'HTTP 403 от securepay.tinkoff.ru: проверьте TerminalKey/SecretKey и IP-ограничения в личном кабинете T-Bank.';
        }

        self::write($level, sprintf('%s %s → HTTP %d', $method, $url, $httpStatus), $context, $provider, $siteId);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function sanitizeParams(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeParams($value);
                continue;
            }

            $keyStr = (string)$key;
            if (in_array($keyStr, ['Token', 'Password', 'SecretKey', 'secret_key'], true)) {
                $sanitized[$keyStr] = '***';
                continue;
            }

            if ($keyStr === 'TerminalKey' || $keyStr === 'terminal_key') {
                $sanitized[$keyStr] = self::maskTerminalKey((string)$value);
                continue;
            }

            if (is_string($value) && strlen($value) > self::MAX_BODY_LENGTH) {
                $sanitized[$keyStr] = self::truncate($value);
                continue;
            }

            $sanitized[$keyStr] = $value;
        }

        return $sanitized;
    }

    /**
     * @return array<string, mixed>
     */
    public static function collectEnvironmentContext(): array
    {
        $https = $_SERVER['HTTPS'] ?? '';
        $isHttps = $https !== '' && $https !== 'off';

        return [
            'host' => (string)($_SERVER['HTTP_HOST'] ?? ''),
            'scheme' => $isHttps ? 'https' : 'http',
            'requestUri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
            'remoteAddr' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            'siteId' => defined('SITE_ID') ? (string)SITE_ID : '',
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function write(
        string $level,
        string $message,
        array $context = [],
        ?string $category = null,
        ?string $siteId = null
    ): void {
        if (!self::isEnabled($siteId)) {
            return;
        }

        $level = strtolower($level);
        if (!isset(self::$levelPriority[$level])) {
            $level = self::LEVEL_INFO;
        }

        $configuredLevel = PaidAccessCore::getLogLevel($siteId);
        if (self::$levelPriority[$level] < self::$levelPriority[$configuredLevel]) {
            return;
        }

        $logPath = $_SERVER['DOCUMENT_ROOT'] . PaidAccessCore::getLogPath($siteId);
        $logDir = dirname($logPath);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $categoryLabel = trim((string)$category);
        if ($categoryLabel === '') {
            $categoryLabel = 'module';
        }

        $contextStr = $context !== [] ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $line = sprintf(
            "[%s] [%s] [%s] %s%s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $categoryLabel,
            $message,
            $contextStr
        );

        file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
    }

    protected static function maskTerminalKey(string $terminalKey): string
    {
        $terminalKey = trim($terminalKey);
        if ($terminalKey === '') {
            return '';
        }

        if (strlen($terminalKey) <= 4) {
            return '***';
        }

        return substr($terminalKey, 0, 4) . '***' . substr($terminalKey, -2);
    }

    protected static function truncate(string $value): string
    {
        if (strlen($value) <= self::MAX_BODY_LENGTH) {
            return $value;
        }

        return mb_substr($value, 0, self::MAX_BODY_LENGTH) . '…';
    }

    /**
     * @param mixed $httpError
     */
    protected static function normalizeHttpError($httpError): string
    {
        if ($httpError === null || $httpError === '') {
            return '';
        }

        if (is_array($httpError)) {
            return json_encode($httpError, JSON_UNESCAPED_UNICODE) ?: 'Array';
        }

        return (string)$httpError;
    }
}

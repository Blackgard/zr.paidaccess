<?php

namespace Zr\PaidAccess\Admin;

use Zr\PaidAccess\Tools\Logger;

/**
 * Отображение JSON-контекста записей журнала событий в админке.
 */
class EventLogContextRenderer
{
    /** @var array<string, string> */
    private const FIELD_LABELS = [
        'source' => 'Источник',
        'requestMethod' => 'Метод',
        'requestUrl' => 'URL',
        'requestUri' => 'URI',
        'remoteAddr' => 'IP',
        'userAgent' => 'User-Agent',
        'capturedAt' => 'Время запроса',
        'gatewayId' => 'Шлюз',
        'orderId' => 'Заказ',
        'bankStatus' => 'Статус в банке',
        'paymentId' => 'PaymentId банка',
        'gatewayStatus' => 'Статус шлюза',
        'httpCode' => 'HTTP-код',
        'bankPaymentId' => 'PaymentId банка',
        'payloadTerminalKey' => 'TerminalKey',
        'scope' => 'Область',
        'deleted' => 'Удалено записей',
    ];

    public static function render(string $code, string $rawContext): string
    {
        $rawContext = trim($rawContext);
        if ($rawContext === '') {
            return '<span class="zr-paidaccess-muted">—</span>';
        }

        $decoded = json_decode($rawContext, true);
        if (!is_array($decoded)) {
            return '<pre class="zr-paidaccess-audit-context__json">' . htmlspecialcharsbx($rawContext) . '</pre>';
        }

        $html = '<div class="zr-paidaccess-audit-context">';
        $summary = self::renderSummary($code, $decoded);
        if ($summary !== '') {
            $html .= $summary;
        }

        $pretty = self::prettyJson(Logger::sanitizeParams($decoded));
        if ($pretty !== '') {
            $html .= '<details class="zr-paidaccess-audit-context__details">'
                . '<summary>Полный контекст</summary>'
                . '<pre class="zr-paidaccess-audit-context__json">' . htmlspecialcharsbx($pretty) . '</pre>'
                . '</details>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @param array<string, mixed> $context
     */
    protected static function renderSummary(string $code, array $context): string
    {
        $priorityKeys = self::priorityKeysForCode($code);
        $facts = [];

        foreach ($priorityKeys as $key) {
            if (!array_key_exists($key, $context)) {
                continue;
            }

            $value = $context[$key];
            if ($value === null || $value === '') {
                continue;
            }

            $label = self::FIELD_LABELS[$key] ?? $key;
            $facts[] = '<dt>' . htmlspecialcharsbx($label) . '</dt><dd>' . self::formatValue($key, $value) . '</dd>';
        }

        if ($facts === []) {
            return '';
        }

        return '<dl class="zr-paidaccess-audit-context__facts">' . implode('', $facts) . '</dl>';
    }

    /**
     * @return string[]
     */
    protected static function priorityKeysForCode(string $code): array
    {
        $webhookKeys = [
            'source',
            'gatewayId',
            'orderId',
            'bankStatus',
            'paymentId',
            'bankPaymentId',
            'gatewayStatus',
            'requestUrl',
            'remoteAddr',
        ];

        if (strpos($code, 'webhook_') === 0 || strpos($code, 'payment_webhook_') === 0) {
            return $webhookKeys;
        }

        if (strpos($code, 'payment_') === 0) {
            return array_merge(['httpCode', 'orderId', 'gatewayCode', 'gatewayPaymentId'], $webhookKeys);
        }

        return array_merge($webhookKeys, ['scope', 'deleted']);
    }

    /**
     * @param mixed $value
     */
    protected static function formatValue(string $key, $value): string
    {
        if ($key === 'requestUrl' || $key === 'requestUri') {
            return '<code>' . htmlspecialcharsbx((string)$value) . '</code>';
        }

        if (is_scalar($value)) {
            return htmlspecialcharsbx((string)$value);
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE);

        return htmlspecialcharsbx($json !== false ? $json : '');
    }

    /**
     * @param array<string, mixed> $data
     */
    protected static function prettyJson(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return $json === false ? '' : $json;
    }
}
